<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\MutabaahDailyHeader;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Models\StudentNote;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WaliKelasDashboardService
{
    public function getDashboardData($user, array $filters = []): array
    {
        // 1. Identify Homeroom Teacher & Assigned Rombels
        $employee = Employee::where('user_id', $user->id)->first();
        $teacher = Teacher::where('user_id', $user->id)->first();
        $teacherId = $teacher?->id;
        $employeeId = $employee?->id;

        $homeroomClasses = Kelas::query()
            ->when($teacherId || $employeeId, function ($q) use ($teacherId, $employeeId) {
                $q->where(function ($sq) use ($teacherId, $employeeId) {
                    if ($teacherId) {
                        $sq->where('wali_kelas_id', $teacherId)->orWhere('created_by', $teacherId);
                    }
                    if ($employeeId) {
                        $sq->orWhere('wali_kelas_id', $employeeId)->orWhere('created_by', $employeeId);
                    }
                });
            })
            ->get();

        // class_id filter wajib milik wali kelas (cegah IDOR antar-rombel)
        $allowedClassIds = $homeroomClasses->pluck('id')->toArray();
        $requestedClassId = $filters['class_id'] ?? null;
        if ($requestedClassId && in_array($requestedClassId, $allowedClassIds, true)) {
            $selectedClassId = $requestedClassId;
        } else {
            $selectedClassId = $homeroomClasses->first()?->id;
        }
        $selectedClass = $selectedClassId ? $homeroomClasses->firstWhere('id', $selectedClassId) : $homeroomClasses->first();
        $targetClassId = $selectedClass?->id;

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // 2. Query Students in Homeroom Class
        $studentQuery = Student::query();
        if ($targetClassId) {
            $studentQuery->where(function ($q) use ($targetClassId) {
                $q->where('kelas_id', $targetClassId)->orWhere('class_id', $targetClassId);
            });
        } elseif ($homeroomClasses->isNotEmpty()) {
            $classIds = $homeroomClasses->pluck('id')->toArray();
            $studentQuery->where(function ($q) use ($classIds) {
                $q->whereIn('kelas_id', $classIds)->orWhereIn('class_id', $classIds);
            });
        }

        $totalSiswaRombel = (clone $studentQuery)->where('is_active', true)->count();
        $studentIds = (clone $studentQuery)->pluck('id')->toArray();

        // Attendance Stats Today
        $today = now()->toDateString();
        $hadir = 0;
        $terlambat = 0;
        $izin = 0;
        $sakit = 0;
        $alpha = 0;

        if (Schema::hasTable('attendances') && ! empty($studentIds)) {
            $attQuery = DB::table('attendances')->whereDate('attendance_date', $today)->whereIn('student_id', $studentIds);
            $hadir = (clone $attQuery)->whereIn('status', ['present', 'hadir'])->count();
            $terlambat = (clone $attQuery)->where('status', 'late')->count();
            $izin = (clone $attQuery)->whereIn('status', ['permission', 'izin'])->count();
            $sakit = (clone $attQuery)->whereIn('status', ['sick', 'sakit'])->count();
            $alpha = (clone $attQuery)->whereIn('status', ['absent', 'alpha'])->count();
        }

        // Student Permissions Pending Verification
        $pendingPermissionsCount = 0;
        if (Schema::hasTable('student_attendance_permissions') && ! empty($studentIds)) {
            $pendingPermissionsCount = StudentAttendancePermission::whereIn('student_id', $studentIds)
                ->where('status', 'pending')
                ->count();
        }

        // Student Notes
        $activeNotesCount = 0;
        $followupNotesCount = 0;
        if (Schema::hasTable('student_notes') && ! empty($studentIds)) {
            $activeNotesCount = StudentNote::whereIn('student_id', $studentIds)->count();
            $followupNotesCount = StudentNote::whereIn('student_id', $studentIds)->whereNotNull('follow_up')->count();
        }

        // Unsigned Parent Mutabaah Notes
        $unsignedParentNotesCount = 0;
        if (Schema::hasTable('mutabaah_daily_headers') && ! empty($studentIds)) {
            $unsignedParentNotesCount = MutabaahDailyHeader::whereIn('student_id', $studentIds)
                ->whereIn('status', ['finalized', 'parent_reviewed'])
                ->whereDoesntHave('parentSignatures')
                ->count();
        }

        $kpis = [
            'total_siswa_rombel' => ['total' => $totalSiswaRombel, 'growth' => 0],
            'siswa_hadir_hari_ini' => ['total' => $hadir, 'growth' => 0],
            'siswa_terlambat' => ['total' => $terlambat, 'growth' => 0],
            'siswa_izin' => ['total' => $izin, 'growth' => 0],
            'siswa_sakit' => ['total' => $sakit, 'growth' => 0],
            'siswa_alpha' => ['total' => $alpha, 'growth' => 0],
            'pending_permissions' => ['total' => $pendingPermissionsCount, 'growth' => 0],
            'active_student_notes' => ['total' => $activeNotesCount, 'growth' => 0],
            'followup_notes' => ['total' => $followupNotesCount, 'growth' => 0],
            'unsigned_parent_notes' => ['total' => $unsignedParentNotesCount, 'growth' => 0],
        ];

        // Rombel List Options for dropdown
        $rombelOptions = $homeroomClasses->map(fn ($c) => [
            'id' => $c->id,
            'nama_kelas' => $c->nama_kelas ?? $c->name ?? $c->kode_kelas,
        ]);

        return [
            'context' => [
                'role' => 'Wali Kelas',
                'rombel' => $selectedClass ? ['id' => $selectedClass->id, 'nama' => $selectedClass->nama_kelas ?? $selectedClass->name] : null,
                'rombel_options' => $rombelOptions,
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [
                'attendance_distribution' => [
                    ['status' => 'Hadir', 'total' => $hadir],
                    ['status' => 'Terlambat', 'total' => $terlambat],
                    ['status' => 'Izin', 'total' => $izin],
                    ['status' => 'Sakit', 'total' => $sakit],
                    ['status' => 'Alpha', 'total' => $alpha],
                ],
            ],
            'tables' => [
                'students' => (clone $studentQuery)->limit(10)->get(['id', 'full_name', 'nisn', 'gender', 'is_active']),
            ],
        ];
    }
}
