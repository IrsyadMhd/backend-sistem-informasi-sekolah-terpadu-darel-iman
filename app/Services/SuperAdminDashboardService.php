<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminDashboardService
{
    public function getDashboardOverview(array $filters = []): array
    {
        $unitFilter = !empty($filters['unit_id']) && $filters['unit_id'] !== 'semua' ? $filters['unit_id'] : null;
        $statusFilter = !empty($filters['status']) && $filters['status'] !== 'semua' ? $filters['status'] : null;
        $periodFilter = !empty($filters['period']) && $filters['period'] !== 'semua' ? $filters['period'] : 'minggu';
        $academicYearId = !empty($filters['academic_year_id']) && $filters['academic_year_id'] !== 'semua' ? $filters['academic_year_id'] : null;
        $semesterId = !empty($filters['semester_id']) && $filters['semester_id'] !== 'semua' ? $filters['semester_id'] : null;

        // 1. Context Information (Dynamic from DB)
        $activeAcademicYear = $academicYearId
            ? AcademicYear::find($academicYearId)
            : (AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first());

        $activeSemester = $semesterId
            ? Semester::find($semesterId)
            : (Semester::where('is_active', true)->first() ?? Semester::latest()->first());

        $availableUnits = EducationUnit::select('id', 'name', 'code', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name ?? $u->nama,
                'code' => $u->code ?? $u->kode ?? '-',
                'is_active' => (bool) $u->is_active,
            ]);

        $availableAcademicYears = AcademicYear::select('id', 'name', 'is_active')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($y) => [
                'id' => $y->id,
                'name' => $y->name ?? $y->nama,
                'is_active' => (bool) $y->is_active,
            ]);

        $availableSemesters = Semester::select('id', 'name', 'academic_year_id', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name ?? $s->nama,
                'academic_year_id' => $s->academic_year_id,
                'is_active' => (bool) $s->is_active,
            ]);

        // 2. KPI Metrics (Strict DB aggregates, scoped to unit if selected)
        if ($unitFilter) {
            $unitModel = EducationUnit::find($unitFilter);
            $totalUnits = $unitModel ? 1 : 0;
            $activeUnits = ($unitModel && $unitModel->is_active) ? 1 : 0;
            $totalEmployees = $unitModel ? $unitModel->employees()->count() : 0;
            $totalTeachers = $unitModel ? $unitModel->teachers()->count() : 0;
            $totalStudents = $unitModel ? $unitModel->students()->count() : 0;

            // Total parents of students in this unit
            $totalParents = ParentModel::whereHas('students', function ($q) use ($unitFilter) {
                $q->where('unit_id', $unitFilter);
            })->count();

            $totalClasses = $unitModel ? $unitModel->classes()->count() : 0;

            $totalRombel = (Schema::hasTable('rombels') && Schema::hasColumn('rombels', 'unit_id'))
                ? DB::table('rombels')->where('unit_id', $unitFilter)->count()
                : (Schema::hasTable('rombels') && Schema::hasColumn('rombels', 'unit_pendidikan_id')
                    ? DB::table('rombels')->where('unit_pendidikan_id', $unitFilter)->count()
                    : $totalClasses);

            $totalAlumni = $unitModel ? $unitModel->students()
                ->where(fn ($q) => $q
                    ->where('is_active', false)
                    ->orWhere('metadata->is_alumni', true)
                    ->orWhere('metadata->status_siswa', 'alumni'))->count() : 0;
        } else {
            $totalUnits = EducationUnit::count();
            $activeUnits = EducationUnit::where('is_active', true)->count();
            $totalEmployees = Employee::count();
            $totalTeachers = Employee::where(function ($q) {
                $q->where('status_pegawai', 'like', '%Guru%')
                  ->orWhereHas('position', function ($p) {
                      $p->where('name', 'like', '%Guru%');
                  });
            })->count();
            $totalStudents = Student::count();
            $totalParents = ParentModel::count();
            $totalClasses = Kelas::count();

            $totalRombel = Schema::hasTable('rombels')
                ? DB::table('rombels')->count()
                : $totalClasses;

            $totalAlumni = Student::where(fn ($q) => $q
                ->where('is_active', false)
                ->orWhere('metadata->is_alumni', true)
                ->orWhere('metadata->status_siswa', 'alumni'))->count();
        }

        $activeUsers = User::where('is_active', true)->count();
        $activeRoles = Role::count();
        $usersWithoutRole = User::doesntHave('roles')->count();

        $kpis = [
            'total_units' => ['total' => $totalUnits, 'growth' => 0],
            'active_units' => ['total' => $activeUnits, 'growth' => 0],
            'total_employees' => ['total' => $totalEmployees, 'growth' => 0],
            'total_teachers' => ['total' => $totalTeachers, 'growth' => 0],
            'total_students' => ['total' => $totalStudents, 'growth' => 0],
            'total_parents' => ['total' => $totalParents, 'growth' => 0],
            'total_classes' => ['total' => $totalClasses, 'growth' => 0],
            'total_rombel' => ['total' => $totalRombel, 'growth' => 0],
            'total_alumni' => ['total' => $totalAlumni, 'growth' => 0],
            'active_users' => ['total' => $activeUsers, 'growth' => 0],
            'active_roles' => ['total' => $activeRoles, 'growth' => 0],
            'users_without_role' => ['total' => $usersWithoutRole, 'growth' => 0],
        ];

        // 3. Charts Data
        // Student distribution per unit
        $studentDistQuery = EducationUnit::withCount('students');
        if ($unitFilter) {
            $studentDistQuery->where('id', $unitFilter);
        }
        $studentDistribution = $studentDistQuery
            ->orderBy('name')
            ->get()
            ->map(function ($unit) {
                return [
                    'name' => $unit->name ?? $unit->nama,
                    'total' => $unit->students_count,
                ];
            });

        // Teacher and Employee distribution per unit
        $staffDistQuery = EducationUnit::withCount(['employees', 'teachers']);
        if ($unitFilter) {
            $staffDistQuery->where('id', $unitFilter);
        }
        $staffDistribution = $staffDistQuery
            ->orderBy('name')
            ->get()
            ->map(function ($unit) {
                return [
                    'name' => $unit->name ?? $unit->nama,
                    'pegawai' => $unit->employees_count,
                    'guru' => $unit->teachers_count,
                ];
            });

        // Overall student attendance summary based on Period
        $today = now()->toDateString();
        $startDate = match ($periodFilter) {
            'hari' => $today,
            'minggu' => now()->subDays(6)->toDateString(),
            'bulan' => now()->startOfMonth()->toDateString(),
            'semester' => now()->subMonths(6)->toDateString(),
            'tahun' => now()->startOfYear()->toDateString(),
            default => now()->subDays(6)->toDateString(),
        };

        $attendanceTrend = [];
        if (Schema::hasTable('attendances')) {
            $attQuery = DB::table('attendances')
                ->selectRaw('attendance_date as date, count(*) as total, sum(case when status = \'present\' or status = \'hadir\' then 1 else 0 end) as hadir')
                ->whereBetween('attendance_date', [$startDate, $today]);

            if ($unitFilter && Schema::hasColumn('attendances', 'unit_id')) {
                $attQuery->where('unit_id', $unitFilter);
            }

            $attendanceTrend = $attQuery
                ->groupBy('attendance_date')
                ->orderBy('attendance_date')
                ->get();
        }

        $charts = [
            'student_distribution' => $studentDistribution,
            'staff_distribution' => $staffDistribution,
            'attendance_trend' => $attendanceTrend,
        ];

        // 4. Tables Data - Units summary (dynamic, no hardcoded limit 10)
        $unitQuery = EducationUnit::withCount(['students', 'employees', 'teachers', 'classes']);
        if ($unitFilter) {
            $unitQuery->where('id', $unitFilter);
        }
        if ($statusFilter === 'aktif') {
            $unitQuery->where('is_active', true);
        } elseif ($statusFilter === 'nonaktif') {
            $unitQuery->where('is_active', false);
        }

        $unitSummaries = $unitQuery
            ->orderBy('name')
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name ?? $unit->nama,
                    'code' => $unit->code ?? $unit->kode ?? '-',
                    'siswa_count' => $unit->students_count,
                    'pegawai_count' => $unit->employees_count,
                    'guru_count' => $unit->teachers_count,
                    'kelas_count' => $unit->classes_count,
                    'status' => $unit->is_active ? 'Aktif' : 'Nonaktif',
                ];
            });

        // 5. Recent user logins (eager load real user roles from DB)
        $recentLogins = [];
        if (Schema::hasTable('login_events')) {
            $events = DB::table('login_events')
                ->join('users', 'users.id', '=', 'login_events.user_id')
                ->select('users.id', 'users.name', 'users.email', 'login_events.created_at', 'login_events.ip_address')
                ->orderByDesc('login_events.created_at')
                ->limit(8)
                ->get();

            if ($events->isNotEmpty()) {
                $userIds = $events->pluck('id')->unique();
                $usersWithRoles = User::with('roles:id,name')->whereIn('id', $userIds)->get()->keyBy('id');

                $recentLogins = $events->map(function ($ev) use ($usersWithRoles) {
                    $u = $usersWithRoles->get($ev->id);
                    $primaryRole = $u?->roles->first()?->name ?? 'Pengguna';
                    return [
                        'id' => $ev->id,
                        'name' => $ev->name,
                        'email' => $ev->email,
                        'role' => $primaryRole,
                        'ip_address' => $ev->ip_address,
                        'created_at' => Carbon::parse($ev->created_at)->diffForHumans(),
                    ];
                })->all();
            }
        }

        if (empty($recentLogins)) {
            $recentUsers = User::with('roles:id,name')->latest()->limit(8)->get();
            $recentLogins = $recentUsers->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->roles->first()?->name ?? 'Pengguna',
                    'created_at' => $u->created_at ? $u->created_at->diffForHumans() : 'Terbaru',
                ];
            })->all();
        }

        // 6. System activity / Audit log
        $recentActivities = [];
        if (Schema::hasTable('audit_logs')) {
            $recentActivities = DB::table('audit_logs')
                ->orderByDesc('created_at')
                ->limit(6)
                ->get();
        }

        return [
            'context' => [
                'role' => 'Admin Sistem',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
                'available_units' => $availableUnits,
                'available_academic_years' => $availableAcademicYears,
                'available_semesters' => $availableSemesters,
            ],
            'kpis' => $kpis,
            'charts' => $charts,
            'unit_summaries' => $unitSummaries,
            'recent_logins' => $recentLogins,
            'recent_activities' => $recentActivities,
        ];
    }
}
