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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminDashboardService
{
    public function getDashboardOverview(array $filters = []): array
    {
        // 1. Context Information
        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // 2. KPI Metrics (Strict DB aggregates)
        $totalUnits = EducationUnit::count();
        $activeUnits = EducationUnit::where('is_active', true)->count();
        $totalEmployees = Employee::count();
        $totalTeachers = Teacher::count();
        $totalStudents = Student::count();
        $totalParents = ParentModel::count();
        $totalClasses = Kelas::count();
        
        $totalRombel = Schema::hasTable('rombels') 
            ? DB::table('rombels')->count() 
            : $totalClasses;

        // Alumni = siswa non-aktif / ditandai alumni (tidak ada kolom `status`).
        $totalAlumni = Student::where(fn ($q) => $q
            ->where('is_active', false)
            ->orWhere('metadata->is_alumni', true)
            ->orWhere('metadata->status_siswa', 'alumni'))->count();
        $activeUsers = User::where('is_active', true)->count();
        $activeRoles = Role::count();

        // Users without roles
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
        $studentDistribution = EducationUnit::withCount('students')
            ->get()
            ->map(function ($unit) {
                return [
                    'name' => $unit->name ?? $unit->nama,
                    'total' => $unit->students_count,
                ];
            });

        // Teacher and Employee distribution per unit
        $staffDistribution = EducationUnit::withCount(['employees', 'teachers'])
            ->get()
            ->map(function ($unit) {
                return [
                    'name' => $unit->name ?? $unit->nama,
                    'pegawai' => $unit->employees_count,
                    'guru' => $unit->teachers_count,
                ];
            });

        // Overall student attendance summary (7 days)
        $today = now()->toDateString();
        $sub7Days = now()->subDays(6)->toDateString();
        $attendanceTrend = [];

        if (Schema::hasTable('attendances')) {
            $attendanceTrend = DB::table('attendances')
                ->selectRaw('attendance_date as date, count(*) as total, sum(case when status = \'present\' or status = \'hadir\' then 1 else 0 end) as hadir')
                ->whereBetween('attendance_date', [$sub7Days, $today])
                ->groupBy('attendance_date')
                ->orderBy('attendance_date')
                ->get();
        }

        $charts = [
            'student_distribution' => $studentDistribution,
            'staff_distribution' => $staffDistribution,
            'attendance_trend' => $attendanceTrend,
        ];

        // 4. Tables Data
        // Units summary
        $unitSummaries = EducationUnit::withCount(['students', 'employees', 'teachers', 'classes'])
            ->limit(10)
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

        // Recent user logins
        $recentLogins = [];
        if (Schema::hasTable('login_events')) {
            $recentLogins = DB::table('login_events')
                ->join('users', 'users.id', '=', 'login_events.user_id')
                ->select('users.name', 'users.email', 'login_events.created_at', 'login_events.ip_address')
                ->orderByDesc('login_events.created_at')
                ->limit(5)
                ->get();
        } else {
            $recentLogins = User::latest()->limit(5)->get(['id', 'name', 'email', 'created_at']);
        }

        // System activity / Audit log
        $recentActivities = [];
        if (Schema::hasTable('audit_logs')) {
            $recentActivities = DB::table('audit_logs')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        return [
            'context' => [
                'role' => 'Super Admin',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => $charts,
            'unit_summaries' => $unitSummaries,
            'recent_logins' => $recentLogins,
            'recent_activities' => $recentActivities,
        ];
    }
}
