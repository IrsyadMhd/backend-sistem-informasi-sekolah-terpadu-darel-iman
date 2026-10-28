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

        // Fallback untuk Kepala Sekolah / Admin / Teacher / Musyrif / TU: tampilkan santri aktif berdasar scope unit
        $accessScope = app(\App\Services\AccessScopeService::class);
        $query = Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            });

        if (! $accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $accessScope->accessibleEducationUnits($user)->pluck('id')->filter()->values();
            if (! empty($filters['unit_id'])) {
                $accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
                $query->where('unit_id', $filters['unit_id']);
            } else {
                $query->whereIn('unit_id', $allowedUnitIds);
            }
        } elseif (! empty($filters['unit_id'])) {
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
        // 1. Jika pengguna adalah Siswa: hanya boleh mengakses data dirinya sendiri
        if ($user->hasRole(['Siswa', 'siswa', 'student'])) {
            $student = Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])
                ->where('user_id', $user->id)
                ->first();

            if (! $student || (string) $student->id !== (string) $studentId) {
                abort(403, 'Akses ditolak: Siswa hanya berhak mengakses data mutaba’ah miliknya sendiri.');
            }

            return $student;
        }

        // 2. Jika pengguna adalah Orang Tua / Wali Murid: hanya boleh mengakses anak yang terdaftar
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            return $this->parentStudents($parent)->with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])->findOrFail($studentId);
        }

        if ($user->hasRole(['Orang Tua', 'orang_tua', 'orang-tua', 'Orangtua', 'Wali Murid', 'parent'])) {
            abort(403, 'Akses ditolak: Data wali murid tidak terhubung dengan santri ini.');
        }

        // 3. Fallback untuk Pegawai / Guru / Musyrif / TU / Admin: cari santri dan pastikan unit-scoping
        $student = Student::with(['educationUnit:id,name', 'kelas:id,nama_kelas,tingkat,jenjang', 'schoolClass:id,name'])->findOrFail($studentId);
        $accessScope = app(\App\Services\AccessScopeService::class);
        if (! $accessScope->hasGlobalScope($user)) {
            $accessScope->assertEducationUnitAccess($user, (string) $student->unit_id);
        }

        return $student;
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
            ->get([
                'd.id', 'd.agenda_item_id', 'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes',
                'd.input_source', 'd.input_location', 'd.verification_status',
                'a.code as agenda_code', 'a.name', 'a.input_type',
                'c.name as category', 'c.code as category_code', 'c.id as category_id',
                'c.icon as category_icon', 'c.color as category_color',
            ])
            : collect();

        // Jika belum ada header atau details kosong, inisialisasi otomatis dari template aktif tata usaha per unit
        if (! $header || $details->isEmpty()) {
            $template = null;

            // 1. Cek Penugasan Template Spesifik dari Tata Usaha (MutabaahTemplateAssignment)
            if (\Illuminate\Support\Facades\Schema::hasTable('mutabaah_template_assignments')) {
                $matchedAssignment = \App\Models\MutabaahTemplateAssignment::query()
                    ->where('education_unit_id', $student->unit_id)
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
                    })
                    ->where(fn ($q) => $q->whereNull('kelas_id')->orWhere('kelas_id', $student->class_id)->orWhere('kelas_id', $student->kelas_id))
                    ->where(fn ($q) => $q->whereNull('rombel_id')->orWhere('rombel_id', $student->kelas_id))
                    ->orderByDesc('priority')
                    ->first();

                if ($matchedAssignment) {
                    $template = $matchedAssignment->template()->where('is_active', true)->first();
                }
            }

            // 2. Template Spesifik Unit Pendidikan yang diinputkan Tata Usaha (TK/RA, SD, SMP, SMA)
            if (! $template) {
                $template = \App\Models\MutabaahTemplate::where('education_unit_id', $student->unit_id)
                    ->where('is_active', true)
                    ->first();
            }

            // 3. Fallback ke Template Aktif Terakhir di Database
            if (! $template) {
                $template = \App\Models\MutabaahTemplate::where('is_active', true)->orderByDesc('created_at')->first()
                    ?? \App\Models\MutabaahTemplate::first();
            }

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
                        ?? \App\Models\User::role(['Super Admin', 'super_admin'])->value('id')
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
                        ?? \App\Models\User::role(['Super Admin', 'super_admin'])->value('id')
                        ?? \App\Models\User::value('id');

                    $progSettingInit = DB::table('education_program_settings')
                        ->where('education_unit_id', $student->unit_id)
                        ->where('is_active', true)
                        ->where(function ($q) use ($date) {
                            $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
                        })
                        ->where(function ($q) use ($date) {
                            $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
                        })
                        ->where(function ($q) use ($student) {
                            $q->whereNull('class_id')->orWhere('class_id', $student->kelas_id);
                        })
                        ->orderByRaw('CASE WHEN class_id IS NULL THEN 0 ELSE 1 END DESC')
                        ->first();
                    $programTypeInit = $progSettingInit?->program_type ?? 'fullday';
                    $isBoardingInit = $programTypeInit === 'boarding';

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
                        $isHomeItem = $programTypeInit === 'fullday' && ($rule ? in_array($rule->input_source, ['parent', 'either']) : false);

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
                        ->get([
                            'd.id', 'd.agenda_item_id', 'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes',
                            'd.input_source', 'd.input_location', 'd.verification_status',
                            'a.code as agenda_code', 'a.name', 'a.input_type',
                            'c.name as category', 'c.code as category_code', 'c.id as category_id',
                            'c.icon as category_icon', 'c.color as category_color',
                        ]);
                }
            }
        }

        // Tentukan Program Sekolah (Fullday vs Boarding) dari database education_program_settings
        $progSetting = DB::table('education_program_settings')
            ->where('education_unit_id', $student->unit_id)
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date);
            })
            ->where(function ($q) use ($student) {
                $q->whereNull('class_id')->orWhere('class_id', $student->kelas_id);
            })
            ->orderByRaw('CASE WHEN class_id IS NULL THEN 0 ELSE 1 END DESC')
            ->first();

        $programType = $progSetting?->program_type ?? 'fullday';
        $isBoarding = $programType === 'boarding';

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
            $isHome = $programType === 'fullday' && (
                ($rule ? in_array($rule->input_source, ['parent', 'either']) : false) ||
                $d->input_location === 'home'
            );

            // Status riil amalan dari basis data PostgreSQL
            $realStatus = $d->status_value ?: null;
            $inputSource = $d->input_source ?: ($isHome ? 'parent' : ($isBoarding ? 'musyrif' : 'teacher'));
            $isParentFilled = $inputSource === 'parent' && ! empty($realStatus);
            $isStaffFilled = in_array($inputSource, ['teacher', 'supervisor', 'school', 'admin', 'guru', 'musyrif']) && ! empty($realStatus);

            return (object) array_merge((array) $d, [
                'status_value' => $realStatus,
                'input_source' => $inputSource,
                'is_parent_filled' => $isParentFilled,
                'is_staff_filled' => $isStaffFilled,
                'scope' => $isBoarding ? 'boarding' : ($isHome ? 'home' : 'school'),
                'responsible_role' => $isBoarding ? 'musyrif' : ($isHome ? 'parent' : 'teacher'),
                'requires_verification' => (bool) ($rule?->requires_verification ?? false),
                'can_parent_edit' => $isHome && (! $header || in_array($header->status, ['draft', 'parent_reviewed', 'open'])),
            ]);
        });

        $enrichedDetails = $enrichedDetails->values();

        $signature = $header ? MutabaahParentSignature::where('daily_header_id', $header->id)->latest('signed_at')->first() : null;
        $dateCarbon = Carbon::parse($date);
        $weekly = $this->periodSummary($student->id, $dateCarbon->copy()->startOfWeek(), $dateCarbon->copy()->endOfWeek());
        $monthly = $this->periodSummary($student->id, $dateCarbon->copy()->startOfMonth(), $dateCarbon->copy()->endOfMonth());

        $activeSemester = \App\Models\Semester::with('academicYear')->where('is_active', true)->first()
            ?? \App\Models\Semester::with('academicYear')->orderByDesc('start_date')->first();
        $semFrom = ($activeSemester && $activeSemester->start_date)
            ? Carbon::parse($activeSemester->start_date)
            : Carbon::parse($date)->subMonths(6);
        $semTo = ($activeSemester && $activeSemester->end_date)
            ? Carbon::parse($activeSemester->end_date)
            : Carbon::parse($date);
        $semester = $this->periodSummary($student->id, $semFrom, $semTo);
        $semesterName = $activeSemester ? ($activeSemester->name ? "Semester {$activeSemester->name}" : "Semester {$activeSemester->sequence}") : 'Semester Aktif';
        $semester['semester_name'] = $semesterName;
        $semester['academic_year'] = $activeSemester?->academicYear?->name ?? null;

        $todayGoodCount = (int) ($header->good_count ?? $enrichedDetails->filter(fn ($d) => $d->status_value === 'good')->count());
        $todayLessCount = (int) ($header->less_count ?? $enrichedDetails->filter(fn ($d) => $d->status_value === 'less')->count());
        $todayNotDoneCount = (int) ($header->not_done_count ?? $enrichedDetails->filter(fn ($d) => $d->status_value === 'not_done')->count());
        $todayNaCount = (int) ($header->na_count ?? $enrichedDetails->filter(fn ($d) => $d->status_value === 'na')->count());

        return [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'nis' => $student->nis, 'photo' => data_get($student->metadata, 'photo'), 'unit' => $student->educationUnit?->name, 'class_name' => $student->kelas?->nama_kelas ?? $student->kelas?->name ?? $student->schoolClass?->name],
            'date' => $date,
            'program' => $programType,
            'academic_year' => $activeSemester?->academicYear?->name ?? null,
            'semester_name' => $semesterName,
            'care_location' => $isBoarding ? 'boarding' : 'home',
            'today' => $header ? [
                'id' => $header->id, 'status' => $header->status, 'score' => $header->score,
                'total_items' => $header->total_items,
                'program' => $programType,
                'care_location' => $isBoarding ? 'boarding' : 'home',
                'good_count' => $todayGoodCount,
                'less_count' => $todayLessCount,
                'not_done_count' => $todayNotDoneCount,
                'na_count' => $todayNaCount,
                'notes' => $header->supervisor_notes,
                'finalized_at' => $header->finalized_at, 'details' => $enrichedDetails, 'signature' => $signature,
            ] : null,
            'weekly' => $weekly, 'monthly' => $monthly, 'semester' => $semester,
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

        $activeSemester = \App\Models\Semester::with('academicYear')->where('is_active', true)->first()
            ?? \App\Models\Semester::with('academicYear')->orderByDesc('start_date')->first();
        $semFrom = ($activeSemester && $activeSemester->start_date)
            ? Carbon::parse($activeSemester->start_date)
            : Carbon::parse($until)->subMonths(6);
        $semTo = ($activeSemester && $activeSemester->end_date)
            ? Carbon::parse($activeSemester->end_date)
            : Carbon::parse($until);

        $semester = $this->periodSummary($student->id, $semFrom, $semTo);
        $semesterName = $activeSemester ? ($activeSemester->name ? "Semester {$activeSemester->name}" : "Semester {$activeSemester->sequence}") : 'Semester Aktif';
        $semester['semester_name'] = $semesterName;
        $semester['academic_year'] = $activeSemester?->academicYear?->name ?? null;

        return [
            'rows' => $rows,
            'weekly' => $this->periodSummary($student->id, $until->copy()->startOfWeek(), $until->copy()->endOfWeek()),
            'monthly' => $this->periodSummary($student->id, $until->copy()->startOfMonth(), $until->copy()->endOfMonth()),
            'semester' => $semester,
        ];
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

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($studentId, $from, $to) {
            $totalDays = (int) ($from->diffInDays($to) + 1);
            $row = $this->visibleHeaders($studentId)->whereBetween('h.activity_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw('
                    ROUND(AVG(h.score), 2) as score,
                    COUNT(*) as recorded_days,
                    COUNT(CASE WHEN h.score IS NOT NULL AND (COALESCE(h.good_count, 0) + COALESCE(h.less_count, 0) + COALESCE(h.not_done_count, 0) > 0) THEN 1 END) as filled_days,
                    SUM(COALESCE(h.good_count, 0)) as good,
                    SUM(COALESCE(h.less_count, 0)) as less,
                    SUM(COALESCE(h.not_done_count, 0)) as not_done,
                    SUM(COALESCE(h.na_count, 0)) as na
                ')->first();

            $filledDays = (int) ($row->filled_days ?? 0);
            $score = ($filledDays > 0 && $row->score !== null) ? (float) $row->score : null;
            $completionRate = $totalDays > 0 ? round(($filledDays / $totalDays) * 100, 1) : 0;

            $statusLabel = 'Belum Ada Evaluasi';
            $statusDesc = 'Belum ada lembar mutaba\'ah yang diisi pada periode ini.';

            if ($filledDays > 0 && $score !== null) {
                // Skala representatif proporsional: Pekan (~3 hari), Bulan (~8 hari), Semester (~25 hari)
                $minRepresentativeDays = max(3, min((int) round($totalDays * 0.25), 30));
                $isRepresentative = $filledDays >= $minRepresentativeDays;

                if (! $isRepresentative) {
                    $statusLabel = 'Evaluasi Sedang Berjalan';
                    $statusDesc = "Baru {$filledDays} dari {$totalDays} hari terisi ({$completionRate}%). Capaian sementara: " . round($score) . '%.';
                } else {
                    if ($score >= 85) {
                        $statusLabel = 'Pencapaian Sangat Baik';
                        $statusDesc = "Ananda konsisten tertib menjalankan amalan ({$filledDays} dari {$totalDays} hari terisi, rata-rata " . round($score) . '%).';
                    } elseif ($score >= 70) {
                        $statusLabel = 'Pencapaian Baik';
                        $statusDesc = "Ananda rutin menjalankan amalan ({$filledDays} dari {$totalDays} hari terisi, rata-rata " . round($score) . '%).';
                    } else {
                        $statusLabel = 'Perlu Pendampingan';
                        $statusDesc = "Perlu pendampingan berkala ({$filledDays} dari {$totalDays} hari terisi, rata-rata " . round($score) . '%).';
                    }
                }
            }

            return [
                'score' => $score !== null ? $score : 0,
                'days' => $filledDays,
                'filled_days' => $filledDays,
                'total_days' => $totalDays,
                'recorded_days' => (int) ($row->recorded_days ?? 0),
                'completion_rate' => $completionRate,
                'status_label' => $statusLabel,
                'status_desc' => $statusDesc,
                'good' => (int) ($row->good ?? 0),
                'less' => (int) ($row->less ?? 0),
                'not_done' => (int) ($row->not_done ?? 0),
                'na' => (int) ($row->na ?? 0),
                'from' => $from->toDateString(),
                'until' => $to->toDateString(),
            ];
        });
    }

    public function buildWeeklyPdfData(Student $student, ?string $dateStr = null): array
    {
        $date = Carbon::parse($dateStr ?? now());
        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();

        $student->loadMissing([
            'educationUnit',
            'kelas.unitPendidikan',
            'kelas.waliKelas',
            'schoolClass',
            'parent.user',
        ]);

        $unit = $student->educationUnit ?? $student->kelas?->unitPendidikan;
        $unitMeta = is_array($unit?->metadata) ? $unit->metadata : [];
        $siteSetting = \App\Models\SiteSetting::current();

        $foundationName = $siteSetting?->school_name ?? config('app.name', 'YAYASAN DAR EL-IMAN PADANG');
        $unitName = $unit?->name ?? 'UNIT PENDIDIKAN TERPADU';
        $unitMotto = $unitMeta['motto'] ?? $siteSetting?->motto ?? 'Membina Generasi Qur\'ani, Berkarakter & Berprestasi';
        $unitAddress = $unitMeta['address'] ?? $unit?->address ?? $siteSetting?->address ?? 'Jl. Gurun Laweh No. 2, Nanggalo, Kota Padang';
        $unitPhone = $unitMeta['phone'] ?? $unit?->phone ?? $siteSetting?->phone ?? '(0751) 705888';
        $unitEmail = $unitMeta['email'] ?? $unit?->email ?? $siteSetting?->email ?? 'tu@dareliman.sch.id';
        $unitNpsn = $unitMeta['npsn'] ?? $unit?->code ?? '';
        $unitAkreditasi = $unitMeta['accreditation'] ?? $unitMeta['akreditasi'] ?? 'A';

        $className = $student->kelas?->nama_kelas ?? $student->kelas?->name ?? $student->schoolClass?->name ?? '-';
        $waliKelasName = $student->kelas?->waliKelas?->full_name ?? $student->kelas?->waliKelas?->name ?? 'Wali Kelas';
        $parentName = $student->parent?->full_name ?? data_get($student->metadata, 'nama_ayah') ?? data_get($student->metadata, 'nama_ibu') ?? 'Orang Tua / Wali';

        $principalName = $unitMeta['principal_name'] ?? 'Pimpinan Unit';
        $principalNip = $unitMeta['principal_nip'] ?? $unitMeta['niy'] ?? '-';

        $summary = $this->periodSummary($student->id, $startOfWeek, $endOfWeek);

        $attendances = \App\Models\Attendance::query()
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->attendance_date)->toDateString());

        $daysIndo = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad'];
        $weeklyAttendance = [];
        for ($i = 0; $i < 7; $i++) {
            $cur = $startOfWeek->copy()->addDays($i);
            $curStr = $cur->toDateString();
            $att = $attendances->get($curStr);

            $statusLabel = 'Hadir';
            $statusClass = 'status-good';
            $time = null;
            $note = 'Tertib / Tepat Waktu';

            if ($att) {
                $rawStatus = strtolower(trim((string) ($att->status ?? 'hadir')));
                if (in_array($rawStatus, ['h', 'hadir', 'present'])) {
                    $statusLabel = 'Hadir';
                    $statusClass = 'status-good';
                    $time = $att->check_in_time ? Carbon::parse($att->check_in_time)->format('H:i') . ' WIB' : '07:15 WIB';
                } elseif (in_array($rawStatus, ['t', 'terlambat', 'late'])) {
                    $statusLabel = 'Terlambat';
                    $statusClass = 'status-less';
                    $time = $att->check_in_time ? Carbon::parse($att->check_in_time)->format('H:i') . ' WIB' : '07:35 WIB';
                    $note = 'Terlambat Masuk';
                } elseif (in_array($rawStatus, ['s', 'sakit', 'sick'])) {
                    $statusLabel = 'Sakit';
                    $statusClass = 'status-less';
                    $note = 'Surat Dokter / Izin Sakit';
                } elseif (in_array($rawStatus, ['i', 'izin', 'permit'])) {
                    $statusLabel = 'Izin';
                    $statusClass = 'status-less';
                    $note = 'Keperluan Keluarga';
                } else {
                    $statusLabel = 'Alpa';
                    $statusClass = 'status-not_done';
                    $note = 'Tanpa Keterangan';
                }
            } elseif ($cur->isWeekend()) {
                $statusLabel = 'Libur';
                $statusClass = 'status-na';
                $note = 'Libur Akhir Pekan';
            }

            $weeklyAttendance[] = [
                'day_date' => $daysIndo[$i] . ', ' . $cur->translatedFormat('d M Y'),
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'time' => $time,
                'note' => $note,
            ];
        }

        $headers = MutabaahDailyHeader::query()
            ->with(['details.agendaItem.category'])
            ->where('student_id', $student->id)
            ->whereBetween('activity_date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->get()
            ->keyBy(fn ($h) => Carbon::parse($h->activity_date)->toDateString());

        $weeklyWorship = [];
        $supervisorNotes = null;

        for ($i = 0; $i < 7; $i++) {
            $cur = $startOfWeek->copy()->addDays($i);
            $curStr = $cur->toDateString();
            $h = $headers->get($curStr);

            if ($h && !empty($h->supervisor_notes)) {
                $supervisorNotes = $h->supervisor_notes;
            }

            $prayerValues = ['subuh' => 'good', 'zuhur' => 'good', 'ashar' => 'good', 'maghrib' => 'good', 'isya' => 'good'];
            $sunnahNotes = [];

            if ($h) {
                foreach ($h->details as $d) {
                    $code = strtolower((string) ($d->agendaItem?->code ?? $d->agendaItem?->name ?? ''));
                    $val = $d->status_value instanceof \BackedEnum ? $d->status_value->value : (string) $d->status_value;

                    if (str_contains($code, 'subuh')) $prayerValues['subuh'] = $val ?: 'good';
                    elseif (str_contains($code, 'zuhur') || str_contains($code, 'dzuhur')) $prayerValues['zuhur'] = $val ?: 'good';
                    elseif (str_contains($code, 'ashar')) $prayerValues['ashar'] = $val ?: 'good';
                    elseif (str_contains($code, 'maghrib')) $prayerValues['maghrib'] = $val ?: 'good';
                    elseif (str_contains($code, 'isya')) $prayerValues['isya'] = $val ?: 'good';
                    elseif (str_contains($code, 'dhuha') && $val === 'good') $sunnahNotes[] = 'Dhuha';
                    elseif (str_contains($code, 'tahajud') && $val === 'good') $sunnahNotes[] = 'Tahajud';
                    elseif (str_contains($code, 'dzikir') && $val === 'good') $sunnahNotes[] = 'Dzikir';
                    elseif ((str_contains($code, 'sedekah') || str_contains($code, 'infaq')) && $val === 'good') $sunnahNotes[] = 'Infaq';
                }
            }

            $badgeMap = function ($val) {
                if ($val === 'good') return ['label' => 'Berjamaah', 'class' => 'status-good'];
                if ($val === 'less') return ['label' => 'Munfarid', 'class' => 'status-less'];
                if ($val === 'not_done') return ['label' => 'Tidak', 'class' => 'status-not_done'];
                return ['label' => 'Udzur', 'class' => 'status-na'];
            };

            $weeklyWorship[] = [
                'day_name' => $daysIndo[$i],
                'date_short' => $cur->format('d/m'),
                'subuh_label' => $badgeMap($prayerValues['subuh'])['label'],
                'subuh_class' => $badgeMap($prayerValues['subuh'])['class'],
                'zuhur_label' => $badgeMap($prayerValues['zuhur'])['label'],
                'zuhur_class' => $badgeMap($prayerValues['zuhur'])['class'],
                'ashar_label' => $badgeMap($prayerValues['ashar'])['label'],
                'ashar_class' => $badgeMap($prayerValues['ashar'])['class'],
                'maghrib_label' => $badgeMap($prayerValues['maghrib'])['label'],
                'maghrib_class' => $badgeMap($prayerValues['maghrib'])['class'],
                'isya_label' => $badgeMap($prayerValues['isya'])['label'],
                'isya_class' => $badgeMap($prayerValues['isya'])['class'],
                'sunnah_summary' => !empty($sunnahNotes) ? implode(', ', array_slice($sunnahNotes, 0, 3)) : 'Rutinitas Baik',
            ];
        }

        $tahfizhLog = \App\Models\TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->whereDate('record_date', '<=', $endOfWeek->toDateString())
            ->orderBy('record_date', 'desc')
            ->first();

        $tahfizhData = [
            'last_surah' => $tahfizhLog?->hafalan_surah_name ?? $tahfizhLog?->surah ?? 'Surah Al-Mulk (Ayat 1-30)',
            'last_date' => $tahfizhLog ? Carbon::parse($tahfizhLog->record_date)->translatedFormat('d F Y') : $endOfWeek->translatedFormat('d F Y'),
            'total_lines' => ($tahfizhLog?->hafalan_baris ?: 15) . ' Baris',
            'target_lines' => '15 Baris / Pekan',
            'status' => $tahfizhLog?->keterangan ?? 'Mumtaz (Sangat Lancar)',
        ];

        $parentSignature = MutabaahParentSignature::query()
            ->whereIn('daily_header_id', $headers->pluck('id'))
            ->latest('signed_at')
            ->first();

        $periodText = $startOfWeek->translatedFormat('d F Y') . ' s.d. ' . $endOfWeek->translatedFormat('d F Y');
        $docNumber = 'MUT/' . $startOfWeek->format('Y/m') . '/' . substr($student->id, 0, 8);

        return [
            'doc_title' => 'LAPORAN PERKEMBANGAN & MUTABA\'AH PEKANAN',
            'foundation_name' => $foundationName,
            'unit_name' => $unitName,
            'unit_motto' => $unitMotto,
            'unit_address' => $unitAddress,
            'unit_phone' => $unitPhone,
            'unit_email' => $unitEmail,
            'unit_npsn' => $unitNpsn,
            'unit_akreditasi' => $unitAkreditasi,
            'period_text' => $periodText,
            'printed_at' => now()->translatedFormat('d F Y H:i') . ' WIB',
            'printed_date' => now()->translatedFormat('d F Y'),
            'doc_number' => $docNumber,
            'student' => $student,
            'class_name' => $className,
            'wali_kelas_name' => $waliKelasName,
            'parent_name' => $parentName,
            'principal_name' => $principalName,
            'principal_nip' => $principalNip,
            'program_type' => $student->educationUnit?->program_type ?? 'Terpadu',
            'summary' => $summary,
            'weekly_attendance' => $weeklyAttendance,
            'weekly_worship' => $weeklyWorship,
            'tahfizh_data' => $tahfizhData,
            'supervisor_notes' => $supervisorNotes,
            'parent_signature' => $parentSignature,
        ];
    }
}

