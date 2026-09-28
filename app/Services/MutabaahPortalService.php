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
            return $this->parentStudents($parent)->with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])
                ->orderBy('full_name')->get()->map(fn (Student $student) => [
                    'id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis,
                    'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name,
                    'class_name' => $student->kelas?->nama_kelas ?? $student->kelas?->name ?? $student->schoolClass?->name,
                    'unit_id' => $student->unit_id,
                    'class_id' => $student->kelas_id ?? $student->class_id,
                ]);
        }

        // Fallback untuk Kepala Sekolah / Admin / Teacher / Musyrif / TU: tampilkan seluruh santri aktif dari database
        $query = Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])
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
                'class_name' => $student->kelas?->nama_kelas ?? $student->kelas?->name ?? $student->schoolClass?->name,
                'unit_id' => $student->unit_id,
                'class_id' => $student->kelas_id ?? $student->class_id,
            ]);
    }

    public function parentStudent(User $user, string $studentId): Student
    {
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            return $this->parentStudents($parent)->with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])->findOrFail($studentId);
        }

        // Fallback untuk Admin / Teacher / TU: cari santri langsung berdasarkan ID
        return Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])->findOrFail($studentId);
    }

    public function ownStudent(User $user): Student
    {
        return Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])->where('user_id', $user->id)->firstOrFail();
    }

    public function overview(Student $student, array $filters): array
    {
        $date = Carbon::parse($filters['date'] ?? now())->toDateString();
        $header = $this->visibleHeaders($student->id)->where('h.activity_date', $date)->first();
        $details = $header ? DB::table('mutabaah_daily_details as d')
            ->join('mutabaah_template_items as ti', 'ti.id', '=', 'd.template_item_id')
            ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
            ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
            ->where('d.daily_header_id', $header->id)->orderBy('ti.sort_order')
            ->get(['d.id', 'd.agenda_item_id', 'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes', 'd.input_source', 'd.input_location', 'd.verification_status', 'a.code as agenda_code', 'a.name', 'a.input_type', 'c.name as category'])
            : collect();

        // Jika belum ada header atau details kosong, inisialisasi otomatis dari template aktif
        if (! $header || $details->isEmpty()) {
            $template = \App\Models\MutabaahTemplate::where('code', 'TPL-MUTABAAH-TERPADU')->first()
                ?? \App\Models\MutabaahTemplate::where('education_unit_id', $student->unit_id)->first()
                ?? \App\Models\MutabaahTemplate::where('is_active', true)->first();

            if ($template) {
                $academicYear = \App\Models\AcademicYear::where('is_active', true)->first()
                    ?? \App\Models\AcademicYear::latest('start_date')->first();
                $semester = \App\Models\Semester::where('academic_year_id', $academicYear?->id)->where('is_active', true)->first()
                    ?? \App\Models\Semester::first();
                $supervisor = \App\Models\MutabaahSupervisorAssignment::where('education_unit_id', $student->unit_id)->first()
                    ?? \App\Models\MutabaahSupervisorAssignment::first();

                if (! $header && $supervisor && $academicYear && $semester) {
                    $headerId = (string) \Illuminate\Support\Str::uuid();
                    $templateItems = $template->items()->where('is_active', true)->get();
                    $validUserId = $student->user_id
                        ?? $student->parent?->user_id
                        ?? \App\Models\User::where('email', 'superadmin@dareliman.sch.id')->value('id')
                        ?? \App\Models\User::value('id');

                    DB::table('mutabaah_daily_headers')->insert([
                        'id' => $headerId,
                        'student_id' => $student->id,
                        'template_id' => $template->id,
                        'supervisor_assignment_id' => $supervisor->id,
                        'education_unit_id' => $student->unit_id ?? $supervisor->education_unit_id,
                        'kelas_id' => $student->class_id,
                        'rombel_id' => $student->kelas_id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                        'activity_date' => $date,
                        'status' => 'draft',
                        'total_items' => $templateItems->count(),
                        'good_count' => 0,
                        'less_count' => 0,
                        'not_done_count' => 0,
                        'na_count' => 0,
                        'score' => null,
                        'created_by' => $validUserId,
                        'updated_by' => $validUserId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $header = $this->visibleHeaders($student->id)->where('h.activity_date', $date)->first();
                }

                if ($header) {
                    $validUserId = $student->user_id
                        ?? $student->parent?->user_id
                        ?? \App\Models\User::where('email', 'superadmin@dareliman.sch.id')->value('id')
                        ?? \App\Models\User::value('id');

                    $isBoardingInit = \Illuminate\Support\Str::contains(strtoupper(($student->educationUnit?->name ?? '') . ' ' . ($student->educationUnit?->code ?? '') . ' ' . ($student->educationUnit?->level ?? '')), ['PONPES', 'MAHAD', 'PESANTREN']);
                    $programTypeInit = $isBoardingInit ? 'boarding' : 'fullday';

                    $rulesInit = \Illuminate\Support\Facades\Cache::remember("mutabaah_input_rules_{$programTypeInit}", 3600, function () use ($programTypeInit) {
                        return DB::table('mutabaah_input_rules')
                            ->where('program_type', $programTypeInit)
                            ->where('is_active', true)
                            ->get()
                            ->keyBy('agenda_item_id');
                    });

                    $templateItems = $template->items()->with('agendaItem')->where('is_active', true)->get();
                    $detailRows = $templateItems->map(function ($tItem) use ($header, $validUserId, $rulesInit, $isBoardingInit, $programTypeInit) {
                        $rule = $rulesInit->get($tItem->agenda_item_id);
                        $isHomeItem = $programTypeInit === 'fullday' && (($rule && $rule->input_source === 'parent') || in_array($tItem->agendaItem?->code, [
                            'TAHAJUD-WITIR', 'SUBUH', 'SHOLAT-SUNNAH-FAJAR', 'WUDHU-SEBELUM-TIDUR',
                            'DOA-ZIKIR-SEBELUM-TIDUR', 'DOA-BANGUN-TIDUR', 'SALAM-ORANG-TUA',
                            'DOA-KELUAR-RUMAH', 'DOA-NAIK-KENDARAAN', 'ZIKIR-PETANG', 'BAKDIYAH-MAGRIB',
                            'MAGHRIB', 'ISYA', 'MENDOAKAN-ORTU', 'MURAJAAH-DENGAN-ORTU',
                            'BACA-SURAT-ALKAHFI', 'PUASA-SUNNAH'
                        ]));

                        return [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'daily_header_id' => $header->id,
                            'template_item_id' => $tItem->id,
                            'agenda_item_id' => $tItem->agenda_item_id,
                            'status_value' => null,
                            'input_source' => 'system',
                            'input_location' => $isHomeItem ? 'home' : ($isBoardingInit ? 'boarding' : 'school'),
                            'verification_status' => 'pending',
                            'input_by' => $validUserId,
                            'input_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->all();

                    DB::table('mutabaah_daily_details')->upsert(
                        $detailRows,
                        ['daily_header_id', 'template_item_id'],
                        ['agenda_item_id', 'status_value', 'input_source', 'input_location', 'verification_status', 'input_by', 'input_at', 'updated_at']
                    );

                    $details = DB::table('mutabaah_daily_details as d')
                        ->join('mutabaah_template_items as ti', 'ti.id', '=', 'd.template_item_id')
                        ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
                        ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
                        ->where('d.daily_header_id', $header->id)->orderBy('ti.sort_order')
                        ->get(['d.id', 'd.agenda_item_id', 'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes', 'd.input_source', 'd.input_location', 'd.verification_status', 'a.code as agenda_code', 'a.name', 'a.input_type', 'c.name as category']);
                }
            }
        }

        // Tentukan Program Sekolah (Fullday vs Boarding)
        $isBoarding = \Illuminate\Support\Str::contains(strtoupper(($student->educationUnit?->name ?? '') . ' ' . ($student->educationUnit?->code ?? '') . ' ' . ($student->educationUnit?->level ?? '')), ['PONPES', 'MAHAD', 'PESANTREN']);
        $programType = $isBoarding ? 'boarding' : 'fullday';

        // Ambil aturan mutabaah_input_rules sesuai program (dicache 1 jam untuk performa instan)
        $rules = \Illuminate\Support\Facades\Cache::remember("mutabaah_input_rules_{$programType}", 3600, function () use ($programType) {
            return DB::table('mutabaah_input_rules')
                ->where('program_type', $programType)
                ->where('is_active', true)
                ->get()
                ->keyBy('agenda_item_id');
        });

        $enrichedDetails = $details->map(function ($d) use ($programType, $isBoarding, $rules, $header) {
            $rule = $rules->get($d->agenda_item_id);
            $isHome = $programType === 'fullday' && (($rule && $rule->input_source === 'parent') || $d->input_location === 'home' || in_array($d->agenda_code, [
                'TAHAJUD-WITIR', 'SUBUH', 'SHOLAT-SUNNAH-FAJAR', 'WUDHU-SEBELUM-TIDUR',
                'DOA-ZIKIR-SEBELUM-TIDUR', 'DOA-BANGUN-TIDUR', 'SALAM-ORANG-TUA',
                'DOA-KELUAR-RUMAH', 'DOA-NAIK-KENDARAAN', 'ZIKIR-PETANG', 'BAKDIYAH-MAGRIB',
                'MAGHRIB', 'ISYA', 'MENDOAKAN-ORTU', 'MURAJAAH-DENGAN-ORTU',
                'BACA-SURAT-ALKAHFI', 'PUASA-SUNNAH'
            ]));

            // Jika amalan rumah dan input_source bukan 'parent', berarti orang tua BELUM mengisi!
            $isParentFilled = $d->input_source === 'parent';
            // Jika amalan sekolah / asrama dan input_source adalah 'system' (placeholder) atau belum diisi guru/musyrif, berarti belum diisi!
            $isStaffFilled = in_array($d->input_source, ['teacher', 'supervisor', 'school', 'admin', 'guru', 'musyrif']);
            $effectiveStatus = $isHome
                ? ($isParentFilled ? $d->status_value : null)
                : ($isStaffFilled ? $d->status_value : null);

            return (object) array_merge((array) $d, [
                'status_value' => $effectiveStatus,
                'input_source' => $isParentFilled ? 'parent' : ($isStaffFilled ? $d->input_source : null),
                'scope' => $isBoarding ? 'boarding' : ($isHome ? 'home' : 'school'),
                'responsible_role' => $isBoarding ? 'musyrif' : ($isHome ? 'parent' : 'teacher'),
                'requires_verification' => (bool) ($rule?->requires_verification ?? false),
                'can_parent_edit' => $isHome && (! $header || $header->status === 'draft'),
            ]);
        });

        $enrichedDetails = $enrichedDetails->sortBy(function ($item) {
            return $item->scope === 'home' ? 0 : 1;
        })->values();

        $signature = $header ? MutabaahParentSignature::where('daily_header_id', $header->id)->latest('signed_at')->first() : null;
        [$weekly, $monthly] = [$this->periodSummary($student->id, Carbon::parse($date)->startOfWeek(), Carbon::parse($date)->endOfWeek()), $this->periodSummary($student->id, Carbon::parse($date)->startOfMonth(), Carbon::parse($date)->endOfMonth())];

        $todayGoodCount = $enrichedDetails->filter(fn ($d) => $d->status_value === 'good')->count();
        $todayLessCount = $enrichedDetails->filter(fn ($d) => $d->status_value === 'less')->count();
        $todayNotDoneCount = $enrichedDetails->filter(fn ($d) => $d->status_value === 'not_done')->count();
        $todayNaCount = $enrichedDetails->filter(fn ($d) => $d->status_value === 'na')->count();

        return [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis, 'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name, 'class_name' => $student->kelas?->nama_kelas ?? $student->kelas?->name ?? $student->schoolClass?->name],
            'date' => $date,
            'program' => $programType,
            'care_location' => $isBoarding ? 'boarding' : 'home',
            'today' => $header ? [
                'id' => $header->id, 'status' => $header->status, 'score' => $header->score,
                'total_items' => $header->total_items,
                'good_count' => $todayGoodCount,
                'less_count' => $todayLessCount,
                'not_done_count' => $todayNotDoneCount,
                'na_count' => $todayNaCount,
                'notes' => $header->supervisor_notes,
                'finalized_at' => $header->finalized_at, 'details' => $enrichedDetails, 'signature' => $signature,
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
        $cacheKey = "mutabaah_period_{$studentId}_{$from->toDateString()}_{$to->toDateString()}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($studentId, $from, $to) {
            $row = $this->visibleHeaders($studentId)->whereBetween('h.activity_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('ROUND(AVG(h.score),2) score, COUNT(*) days, SUM(h.good_count) good, SUM(h.less_count) less, SUM(h.not_done_count) not_done, SUM(h.na_count) na')->first();

            return ['score' => (float) ($row->score ?? 0), 'days' => (int) ($row->days ?? 0), 'good' => (int) ($row->good ?? 0), 'less' => (int) ($row->less ?? 0), 'not_done' => (int) ($row->not_done ?? 0), 'na' => (int) ($row->na ?? 0)];
        });
    }
}
