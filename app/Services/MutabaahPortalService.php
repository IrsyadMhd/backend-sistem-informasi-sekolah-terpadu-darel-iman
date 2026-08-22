<?php

namespace App\Services;

use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahParentSignature;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MutabaahPortalService
{
    public function children(User $user, array $filters = [])
    {
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            return $this->parentStudents($parent)->with(['educationUnit:id,name', 'kelas:id,name', 'schoolClass:id,name'])
                ->orderBy('full_name')->get()->map(fn (Student $student) => [
                    'id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis,
                    'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name,
                    'class_name' => $student->kelas?->name || $student->schoolClass?->name,
                    'unit_id' => $student->unit_id,
                    'class_id' => $student->kelas_id || $student->class_id,
                ]);
        }

        // Fallback untuk Kepala Sekolah / Admin / Teacher / Musyrif / TU: tampilkan seluruh santri aktif dari database
        $query = Student::with(['educationUnit:id,name', 'kelas:id,name', 'schoolClass:id,name'])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            });

        if (! empty($filters['unit_id'])) {
            $unitId = $filters['unit_id'];
            $query->where('unit_id', $unitId);
        }
        if (! empty($filters['class_id'])) {
            $classId = $filters['class_id'];
            $query->where(function ($q) use ($classId) {
                $q->where('kelas_id', $classId)->orWhere('class_id', $classId);
            });
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('full_name')
            ->limit(100)
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis,
                'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name,
                'class_name' => $student->kelas?->name || $student->schoolClass?->name,
                'unit_id' => $student->unit_id,
                'class_id' => $student->kelas_id || $student->class_id,
            ]);
    }

    public function parentStudent(User $user, string $studentId): Student
    {
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            return $this->parentStudents($parent)->with(['educationUnit:id,name', 'schoolClass:id,name'])->findOrFail($studentId);
        }

        // Fallback untuk Admin / Teacher / TU: cari santri langsung berdasarkan ID
        return Student::with(['educationUnit:id,name', 'schoolClass:id,name'])->findOrFail($studentId);
    }

    public function ownStudent(User $user): Student
    {
        return Student::with(['educationUnit:id,name', 'schoolClass:id,name'])->where('user_id', $user->id)->firstOrFail();
    }

    public function overview(Student $student, array $filters): array
    {
        $date = Carbon::parse($filters['date'] ?? now())->toDateString();
        $header = $this->visibleHeaders($student->id)->whereDate('h.activity_date', $date)->first();
        $details = $header ? DB::table('mutabaah_daily_details as d')
            ->join('mutabaah_template_items as ti', 'ti.id', '=', 'd.template_item_id')
            ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
            ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
            ->where('d.daily_header_id', $header->id)->orderBy('ti.sort_order')
            ->get(['d.id', 'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes', 'a.name', 'a.input_type', 'c.name as category'])
            : collect();
        $signature = $header ? MutabaahParentSignature::where('daily_header_id', $header->id)->latest('signed_at')->first() : null;
        [$weekly, $monthly] = [$this->periodSummary($student->id, Carbon::parse($date)->startOfWeek(), Carbon::parse($date)->endOfWeek()), $this->periodSummary($student->id, Carbon::parse($date)->startOfMonth(), Carbon::parse($date)->endOfMonth())];

        return [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis, 'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name, 'class_name' => $student->schoolClass?->name],
            'date' => $date,
            'today' => $header ? [
                'id' => $header->id, 'status' => $header->status, 'score' => $header->score,
                'total_items' => $header->total_items, 'good_count' => $header->good_count,
                'less_count' => $header->less_count, 'not_done_count' => $header->not_done_count,
                'na_count' => $header->na_count, 'notes' => $header->supervisor_notes,
                'finalized_at' => $header->finalized_at, 'details' => $details, 'signature' => $signature,
            ] : null,
            'weekly' => $weekly, 'monthly' => $monthly,
        ];
    }

    public function history(Student $student, array $filters): array
    {
        $until = Carbon::parse($filters['until'] ?? now())->endOfDay();
        $from = Carbon::parse($filters['from'] ?? $until->copy()->subDays(30))->startOfDay();
        $rows = $this->visibleHeaders($student->id)->whereBetween('h.activity_date', [$from->toDateString(), $until->toDateString()])
            ->leftJoin('mutabaah_parent_signatures as ps', 'ps.daily_header_id', '=', 'h.id')
            ->selectRaw('h.id, h.activity_date, h.status, h.score, h.good_count, h.less_count, h.not_done_count, h.na_count, h.supervisor_notes, MAX(ps.signed_at) signed_at, MAX(ps.signature_status) signature_status')
            ->groupBy('h.id', 'h.activity_date', 'h.status', 'h.score', 'h.good_count', 'h.less_count', 'h.not_done_count', 'h.na_count', 'h.supervisor_notes')
            ->orderByDesc('h.activity_date')->paginate(min((int) ($filters['per_page'] ?? 20), 60));

        return ['rows' => $rows, 'weekly' => $this->periodSummary($student->id, $until->copy()->startOfWeek(), $until->copy()->endOfWeek()), 'monthly' => $this->periodSummary($student->id, $until->copy()->startOfMonth(), $until->copy()->endOfMonth())];
    }

    public function sign(User $user, string $headerId, array $data, Request $request): MutabaahParentSignature
    {
        return DB::transaction(function () use ($user, $headerId, $data, $request) {
            $header = MutabaahDailyHeader::lockForUpdate()->findOrFail($headerId);
            $this->parentStudent($user, $header->student_id);
            abort_unless(in_array($header->status->value, ['finalized', 'parent_reviewed', 'parent_signed', 'follow_up'], true), 409, 'Data belum difinalisasi pembimbing.');
            $pinHash = data_get($user->metadata, 'pin_hash');
            if ($pinHash) {
                abort_unless(! empty($data['pin']) && Hash::check($data['pin'], $pinHash), 422, 'PIN akun tidak valid.');
            }
            $signature = MutabaahParentSignature::updateOrCreate(
                ['daily_header_id' => $header->id, 'parent_user_id' => $user->id],
                ['signature_status' => $data['signature_status'], 'comment' => $data['comment'] ?? null,
                    'signed_at' => now(), 'ip_address' => $request->ip(),
                    'device_info' => ['user_agent' => $request->userAgent(), 'platform' => $data['device_info']['platform'] ?? null, 'app' => $data['device_info']['app'] ?? 'web']]
            );
            $header->update(['status' => $data['signature_status'] === 'approved' ? 'parent_signed' : 'follow_up', 'updated_by' => $user->id]);

            return $signature->fresh();
        });
    }

    private function parentStudents(ParentModel $parent): Builder
    {
        return Student::query()->active()->where(function ($query) use ($parent) {
            $query->where('parent_id', $parent->id);
            if (Schema::hasTable('student_parents')) {
                $query->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('student_parents as sp')->whereColumn('sp.student_id', 'students.id')->where('sp.parent_id', $parent->id));
            }
        });
    }

    private function visibleHeaders(string $studentId)
    {
        return DB::table('mutabaah_daily_headers as h')->where('h.student_id', $studentId)->whereNull('h.deleted_at');
    }

    private function periodSummary(string $studentId, Carbon $from, Carbon $to): array
    {
        $row = $this->visibleHeaders($studentId)->whereBetween('h.activity_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('ROUND(AVG(h.score),2) score, COUNT(*) days, SUM(h.good_count) good, SUM(h.less_count) less, SUM(h.not_done_count) not_done, SUM(h.na_count) na')->first();

        return ['score' => (float) ($row->score ?? 0), 'days' => (int) ($row->days ?? 0), 'good' => (int) ($row->good ?? 0), 'less' => (int) ($row->less ?? 0), 'not_done' => (int) ($row->not_done ?? 0), 'na' => (int) ($row->na ?? 0)];
    }
}
