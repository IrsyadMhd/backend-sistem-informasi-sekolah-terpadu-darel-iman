<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplateItem;
use App\Models\Student;
use App\Services\MutabaahAssessmentService;
use App\Services\MutabaahPortalService;
use App\Services\TahfizhAchievementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParentWorshipInputController extends Controller
{
    public function __construct(
        private readonly MutabaahPortalService $portal,
        private readonly MutabaahAssessmentService $assessment,
        private readonly TahfizhAchievementService $tahfizh,
    ) {}

    public function context(Request $request, string $studentId): JsonResponse
    {
        $student = $this->portal->parentStudent($request->user(), $studentId);
        $date = Carbon::parse($request->query('date', now()))->toDateString();
        $program = $this->assessment->program($student, $date);
        $period = $this->assessment->period($student, $date);
        $rules = $this->assessment->parentRules($student, $date);
        $header = MutabaahDailyHeader::query()
            ->where('student_id', $student->id)
            ->whereDate('activity_date', $date)
            ->latest('created_at')
            ->first();
        $existing = $header
            ? MutabaahDailyDetail::query()->where('daily_header_id', $header->id)->get()->keyBy('agenda_item_id')
            : collect();
        $canEdit = ! $header || $header->status->value === 'draft';
        $canParentInput = $rules->isNotEmpty() && $canEdit && $request->user()->can('mutabaah.parent.input_home');

        return response()->json(['data' => [
            'student' => ['id' => $student->id, 'name' => $student->full_name], 'date' => $date,
            'program' => $program?->program_type ?? 'fullday',
            'care_location' => $rules->isEmpty() ? ($program?->program_type === 'boarding' ? 'boarding' : 'school') : 'home',
            'period' => $period ? ['id' => $period->id, 'name' => $period->name, 'type' => $period->period_type] : null,
            'input_mode' => $rules->isEmpty() ? 'report_only' : 'parent_input',
            'can_parent_input' => $canParentInput,
            'can_edit' => $canEdit,
            'report_only' => $rules->isEmpty() || ! $canParentInput,
            'header_status' => $header?->status?->value,
            'items' => $rules->map(function ($rule) use ($existing) {
                $value = $existing->get($rule->agenda_item_id);
                $isParentFilled = $value && $value->input_source === 'parent';
                return [
                    'agenda_item_id' => $rule->agenda_item_id,
                    'code' => $rule->agendaItem?->code,
                    'name' => $rule->agendaItem?->name,
                    'location' => $rule->location,
                    'requires_verification' => $rule->requires_verification,
                    'status_value' => $isParentFilled ? $value?->status_value?->value : null,
                    'numeric_value' => $isParentFilled ? $value?->numeric_value : null,
                    'notes' => $isParentFilled ? $value?->notes : null,
                    'verification_status' => $isParentFilled ? $value?->verification_status : 'pending',
                ];
            })->values(),
        ]]);
    }

    public function store(Request $request, string $studentId): JsonResponse
    {
        abort_unless($request->user()->can('mutabaah.parent.input_home'), 403);
        $student = $this->portal->parentStudent($request->user(), $studentId);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'], 'items' => ['required', 'array', 'min:1'],
            'items.*.agenda_item_id' => ['required', 'uuid', 'distinct', 'exists:mutabaah_agenda_items,id'],
            'items.*.status_value' => ['required', Rule::in(['good', 'less', 'not_done', 'na'])],
            'items.*.numeric_value' => ['nullable', 'numeric', 'min:0'], 'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $rules = $this->assessment->parentRules($student, $data['date'])->keyBy('agenda_item_id');
        foreach ($data['items'] as $item) abort_unless($rules->has($item['agenda_item_id']), 403, 'Aktivitas bukan tanggung jawab input orang tua pada tanggal ini.');

        $period = $this->assessment->period($student, $data['date']);
        $assignment = MutabaahSupervisorAssignment::active()->byDate($data['date'])->where('education_unit_id', $student->unit_id)
            ->where(fn ($q) => $q->whereNull('rombel_id')->orWhere('rombel_id', $student->kelas_id))->latest()->first();
        abort_unless($assignment, 422, 'Pembimbing Mutabaah aktif belum ditetapkan.');
        $templateId = $period?->template_id ?: $assignment->template_id;
        abort_unless($templateId, 422, 'Template Mutabaah aktif belum ditetapkan.');

        $header = DB::transaction(function () use ($request, $student, $data, $assignment, $templateId, $rules) {
            $header = MutabaahDailyHeader::firstOrCreate(
                ['student_id' => $student->id, 'activity_date' => Carbon::parse($data['date'])->startOfDay(), 'template_id' => $templateId],
                ['supervisor_assignment_id' => $assignment->id, 'education_unit_id' => $student->unit_id,
                    'rombel_id' => $student->kelas_id, 'academic_year_id' => $assignment->academic_year_id,
                    'semester_id' => $assignment->semester_id, 'status' => 'draft',
                    'total_items' => MutabaahTemplateItem::where('template_id', $templateId)->where('is_active', true)->count(),
                    'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]
            );
            abort_if($header->status->value !== 'draft', 409, 'Data sudah difinalisasi dan tidak dapat diubah orang tua.');
            foreach ($data['items'] as $value) {
                $templateItem = MutabaahTemplateItem::where('template_id', $templateId)->where('agenda_item_id', $value['agenda_item_id'])->where('is_active', true)->first();
                abort_unless($templateItem, 422, 'Aktivitas belum dimasukkan ke template aktif.');
                $rule = $rules->get($value['agenda_item_id']);
                MutabaahDailyDetail::updateOrCreate(
                    ['daily_header_id' => $header->id, 'template_item_id' => $templateItem->id],
                    ['agenda_item_id' => $value['agenda_item_id'], 'status_value' => $value['status_value'],
                        'numeric_value' => $value['numeric_value'] ?? null, 'notes' => $value['notes'] ?? null,
                        'input_source' => 'parent', 'input_location' => 'home',
                        'verification_status' => $rule->requires_verification ? 'pending' : 'not_required',
                        'input_by' => $request->user()->id, 'input_at' => now()]
                );
            }
            $this->assessment->recalculate($header);
            return $header->fresh('details');
        });
        return response()->json(['message' => 'Mutabaah rumah berhasil disimpan.', 'data' => $header]);
    }

    public function tahfizhSummary(Request $request, string $studentId): JsonResponse
    {
        $student = $this->portal->parentStudent($request->user(), $studentId);
        return response()->json(['data' => $this->tahfizh->summary($student)]);
    }
}
