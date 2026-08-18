<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\MutabaahActivityLog;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MutabaahDailyService
{
    public function assignments(User $user, string $date): Collection
    {
        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');
        abort_unless($employeeId || $user->hasRole('Super Admin'), 403, 'Akun tidak terhubung dengan data pembimbing.');

        return MutabaahSupervisorAssignment::query()
            ->with(['employee:id,nama_lengkap', 'educationUnit:id,name', 'kelas:id,name,level', 'rombel:id,nama_kelas', 'template.items.agendaItem.category'])
            ->active()->byDate($date)
            ->when(! $user->hasRole('Super Admin'), fn (Builder $q) => $q->where('employee_id', $employeeId))
            ->orderByDesc('is_primary')->get();
    }

    public function context(User $user, array $filters): array
    {
        $date = Carbon::parse($filters['date'] ?? now())->toDateString();
        $assignments = $this->assignments($user, $date);
        if ($id = $filters['supervisor_assignment_id'] ?? null) {
            $assignments = $assignments->where('id', $id)->values();
        }
        $assignment = $assignments->first();
        $template = $assignment ? $this->resolveTemplate($assignment, $date) : null;

        return [
            'date' => $date,
            'assignments' => $assignments->map(fn ($item) => $this->assignmentData($item)),
            'selected_assignment_id' => $assignment?->id,
            'template' => $template ? $this->templateData($template) : null,
            'can_reopen' => $user->hasRole('Super Admin') || $user->can('mutabaah.daily.reopen'),
        ];
    }

    public function students(User $user, array $filters): array
    {
        $assignment = $this->ownedAssignment($user, $filters['supervisor_assignment_id'], $filters['date']);
        $template = $this->resolveTemplate($assignment, $filters['date']);
        abort_unless($template, 422, 'Template aktif tidak ditemukan untuk assignment dan tanggal ini.');

        $query = $this->studentScope($assignment)->with(['educationUnit:id,name', 'schoolClass:id,name,level']);
        if ($search = $filters['search'] ?? null) {
            $query->where(fn ($q) => $q->where('full_name', 'ilike', "%{$search}%")->orWhere('nis', 'ilike', "%{$search}%"));
        }
        $students = $query->orderBy('full_name')->get();
        $headers = MutabaahDailyHeader::query()->withCount('details')->whereIn('student_id', $students->pluck('id'))
            ->where('template_id', $template->id)->byDate($filters['date'])->get()->keyBy('student_id');

        return [
            'template' => $this->templateData($template),
            'students' => $students->map(function (Student $student) use ($headers) {
                $header = $headers->get($student->id);

                return [
                    'id' => $student->id, 'nis' => $student->nis, 'name' => $student->full_name,
                    'photo' => data_get($student->metadata, 'photo'), 'class_name' => $student->schoolClass?->name,
                    'rombel_name' => data_get($student->metadata, 'rombel_name'),
                    'header_id' => $header?->id, 'status' => $header?->status?->value ?? 'draft',
                    'progress' => $header && $header->total_items ? round(($header->details_count / $header->total_items) * 100) : 0,
                    'score' => $header?->score, 'notes' => $header?->supervisor_notes,
                ];
            })->values(),
        ];
    }

    public function detail(User $user, string $studentId, array $filters): array
    {
        $assignment = $this->ownedAssignment($user, $filters['supervisor_assignment_id'], $filters['date']);
        $student = $this->studentScope($assignment)->with('schoolClass')->findOrFail($studentId);
        $template = $this->resolveTemplate($assignment, $filters['date']);
        abort_unless($template, 422, 'Template aktif tidak ditemukan.');
        $header = MutabaahDailyHeader::with('details')->where([
            'student_id' => $studentId, 'template_id' => $template->id,
        ])->byDate($filters['date'])->first();
        $history = MutabaahDailyHeader::query()->where('student_id', $studentId)
            ->whereDate('activity_date', '<=', $filters['date'])->latest('activity_date')->limit(7)
            ->get(['activity_date', 'score', 'status', 'good_count', 'less_count', 'not_done_count', 'na_count']);

        return [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis, 'photo' => data_get($student->metadata, 'photo'), 'class_name' => $student->schoolClass?->name],
            'header' => $header,
            'values' => $header?->details->keyBy('template_item_id') ?? collect(),
            'history' => $history,
        ];
    }

    public function saveCell(User $user, array $data, Request $request): MutabaahDailyHeader
    {
        return DB::transaction(function () use ($user, $data) {
            $assignment = $this->ownedAssignment($user, $data['supervisor_assignment_id'], $data['activity_date']);
            abort_unless($assignment->can_input || $assignment->can_edit, 403, 'Assignment tidak memiliki hak input.');
            $student = $this->studentScope($assignment)->findOrFail($data['student_id']);
            $template = $this->resolveTemplate($assignment, $data['activity_date']);
            $item = $template?->items()->whereKey($data['template_item_id'])->where('is_active', true)->first();
            abort_unless($template && $item, 422, 'Item tidak termasuk template aktif.');
            $header = $this->header($student, $assignment, $template, $data['activity_date'], $user->id);
            abort_if($header->status->value !== 'draft', 409, 'Data sudah difinalisasi dan harus dibuka kembali sebelum diubah.');
            $detail = MutabaahDailyDetail::updateOrCreate(
                ['daily_header_id' => $header->id, 'template_item_id' => $item->id],
                ['agenda_item_id' => $item->agenda_item_id, 'status_value' => $data['status_value'] ?? null,
                    'numeric_value' => $data['numeric_value'] ?? null, 'text_value' => $data['text_value'] ?? null,
                    'notes' => $data['notes'] ?? null, 'input_by' => $user->id, 'input_at' => now()]
            );
            $this->recalculate($header);

            return $header->fresh('details');
        });
    }

    public function bulkSave(User $user, array $data, Request $request): int
    {
        return DB::transaction(function () use ($user, $data, $request) {
            foreach (array_unique($data['student_ids']) as $studentId) {
                $this->saveCell($user, [
                    'student_id' => $studentId, 'activity_date' => $data['activity_date'],
                    'supervisor_assignment_id' => $data['supervisor_assignment_id'],
                    'template_item_id' => $data['template_item_id'],
                    'status_value' => $data['value']['status_value'] ?? null,
                    'numeric_value' => $data['value']['numeric_value'] ?? null,
                    'text_value' => $data['value']['text_value'] ?? null,
                ], $request);
            }

            return count(array_unique($data['student_ids']));
        });
    }

    public function copyPrevious(User $user, array $data, Request $request): int
    {
        $previous = Carbon::parse($data['activity_date'])->subDay()->toDateString();

        return DB::transaction(function () use ($user, $data, $request, $previous) {
            $count = 0;
            foreach ($data['student_ids'] as $studentId) {
                $source = MutabaahDailyHeader::with('details')->where('student_id', $studentId)->byDate($previous)->first();
                if (! $source) {
                    continue;
                }
                foreach ($source->details as $detail) {
                    $this->saveCell($user, [
                        'student_id' => $studentId, 'activity_date' => $data['activity_date'],
                        'supervisor_assignment_id' => $data['supervisor_assignment_id'],
                        'template_item_id' => $detail->template_item_id, 'status_value' => $detail->status_value?->value,
                        'numeric_value' => $detail->numeric_value, 'text_value' => $detail->text_value, 'notes' => $detail->notes,
                    ], $request);
                }
                $count++;
            }

            return $count;
        });
    }

    public function finalize(User $user, array $data): int
    {
        return DB::transaction(function () use ($user, $data) {
            $assignment = $this->ownedAssignment($user, $data['supervisor_assignment_id'], $data['activity_date']);
            abort_unless($assignment->can_finalize, 403, 'Assignment tidak memiliki hak finalisasi.');
            $template = $this->resolveTemplate($assignment, $data['activity_date']);
            $headers = MutabaahDailyHeader::whereIn('student_id', $data['student_ids'])
                ->where('template_id', $template->id)->byDate($data['activity_date'])->lockForUpdate()->get();
            abort_if($headers->count() !== count(array_unique($data['student_ids'])), 422, 'Sebagian siswa belum memiliki data draft.');
            foreach ($headers as $header) {
                $this->studentScope($assignment)->findOrFail($header->student_id);
                abort_if($header->status->value !== 'draft', 409, 'Sebagian data sudah difinalisasi.');
                $header->update(['status' => 'finalized', 'finalized_by' => $user->id, 'finalized_at' => now(), 'updated_by' => $user->id]);
            }

            return $headers->count();
        });
    }

    public function reopen(User $user, array $data, Request $request): MutabaahDailyHeader
    {
        abort_unless($user->hasRole('Super Admin') || $user->can('mutabaah.daily.reopen'), 403, 'Tidak memiliki permission membuka finalisasi.');

        return DB::transaction(function () use ($user, $data, $request) {
            $assignment = $this->ownedAssignment($user, $data['supervisor_assignment_id'], $data['activity_date']);
            $template = $this->resolveTemplate($assignment, $data['activity_date']);
            $header = MutabaahDailyHeader::where('student_id', $data['student_id'])->where('template_id', $template->id)->byDate($data['activity_date'])->lockForUpdate()->firstOrFail();
            $old = $header->only(['status', 'finalized_by', 'finalized_at']);
            $header->update(['status' => 'draft', 'finalized_by' => null, 'finalized_at' => null, 'updated_by' => $user->id]);
            MutabaahActivityLog::create(['user_id' => $user->id, 'subject_type' => MutabaahDailyHeader::class, 'subject_id' => $header->id,
                'event' => 'reopened', 'old_values' => $old, 'new_values' => ['status' => 'draft', 'reason' => $data['reason'] ?? null],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now()]);

            return $header->fresh();
        });
    }

    private function ownedAssignment(User $user, string $id, string $date): MutabaahSupervisorAssignment
    {
        $assignment = $this->assignments($user, $date)->firstWhere('id', $id);
        abort_unless($assignment, 403, 'Assignment tidak aktif atau berada di luar scope pengguna.');

        return $assignment;
    }

    private function studentScope(MutabaahSupervisorAssignment $assignment): Builder
    {
        return Student::query()->active()->where('unit_id', $assignment->education_unit_id)
            ->when($assignment->kelas_id, fn ($q, $id) => $q->where('class_id', $id))
            ->when($assignment->rombel_id, fn ($q, $id) => $q->where('class_id', $id))
            ->when($assignment->mentoring_group, fn ($q, $group) => $q->where('metadata->mentoring_group', $group))
            ->when($assignment->dormitory_id, fn ($q, $id) => $q->where('metadata->dormitory_id', $id))
            ->when($assignment->room_id, fn ($q, $id) => $q->where('metadata->room_id', $id));
    }

    private function resolveTemplate(MutabaahSupervisorAssignment $assignment, string $date): ?MutabaahTemplate
    {
        if ($assignment->template_id) {
            return MutabaahTemplate::with('items.agendaItem.category')->active()->byDate($date)->find($assignment->template_id);
        }
        $matched = MutabaahTemplateAssignment::query()->active()->byDate($date)
            ->where('education_unit_id', $assignment->education_unit_id)
            ->where('academic_year_id', $assignment->academic_year_id)->where('semester_id', $assignment->semester_id)
            ->where(fn ($q) => $q->whereNull('kelas_id')->orWhere('kelas_id', $assignment->kelas_id))
            ->where(fn ($q) => $q->whereNull('rombel_id')->orWhere('rombel_id', $assignment->rombel_id))
            ->orderByDesc('priority')->first();

        return $matched?->template()->with('items.agendaItem.category')->first();
    }

    private function header(Student $student, MutabaahSupervisorAssignment $assignment, MutabaahTemplate $template, string $date, string $userId): MutabaahDailyHeader
    {
        return MutabaahDailyHeader::firstOrCreate(
            ['student_id' => $student->id, 'activity_date' => Carbon::parse($date)->startOfDay(), 'template_id' => $template->id],
            ['supervisor_assignment_id' => $assignment->id, 'education_unit_id' => $assignment->education_unit_id,
                'kelas_id' => $assignment->kelas_id, 'rombel_id' => $assignment->rombel_id,
                'academic_year_id' => $assignment->academic_year_id, 'semester_id' => $assignment->semester_id,
                'status' => 'draft', 'total_items' => $template->items->where('is_active', true)->count(),
                'created_by' => $userId, 'updated_by' => $userId]
        );
    }

    private function recalculate(MutabaahDailyHeader $header): void
    {
        $counts = $header->details()->selectRaw('status_value, count(*) total')->groupBy('status_value')->pluck('total', 'status_value');
        $total = max(1, $header->total_items);
        $good = (int) ($counts['good'] ?? 0);
        $less = (int) ($counts['less'] ?? 0);
        $notDone = (int) ($counts['not_done'] ?? 0);
        $na = (int) ($counts['na'] ?? 0);
        $denominator = max(1, $total - $na);
        $header->update(['good_count' => $good, 'less_count' => $less, 'not_done_count' => $notDone, 'na_count' => $na,
            'score' => round((($good + ($less * .5)) / $denominator) * 100, 2)]);
    }

    private function assignmentData($item): array
    {
        return ['id' => $item->id, 'type' => $item->supervisor_type->value, 'unit_id' => $item->education_unit_id,
            'unit_name' => $item->educationUnit?->name, 'kelas_id' => $item->kelas_id, 'kelas_name' => $item->kelas?->name,
            'rombel_id' => $item->rombel_id, 'rombel_name' => $item->rombel?->nama_kelas,
            'dormitory_id' => $item->dormitory_id, 'room_id' => $item->room_id, 'mentoring_group' => $item->mentoring_group,
            'can_input' => $item->can_input, 'can_finalize' => $item->can_finalize];
    }

    private function templateData(MutabaahTemplate $template): array
    {
        return ['id' => $template->id, 'name' => $template->name, 'items' => $template->items->where('is_active', true)->values()->map(fn ($item) => [
            'id' => $item->id, 'agenda_item_id' => $item->agenda_item_id, 'name' => $item->agendaItem?->name,
            'category' => $item->agendaItem?->category?->name ?? 'Mutabaah Yaumiyyah',
            'input_type' => $item->agendaItem?->input_type?->value, 'weight' => $item->weight, 'required' => $item->is_required,
        ])];
    }
}
