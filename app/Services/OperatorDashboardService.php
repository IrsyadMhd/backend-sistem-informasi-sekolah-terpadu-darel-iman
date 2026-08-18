<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

class OperatorDashboardService
{
    public function getDashboardOverview($user, array $filters = []): array
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // Operator melekat pada unit via data Employee; bila ada unit, scope data ke unit tsb.
        $employee = Employee::where('user_id', $user->id)->first();
        $unitId = $employee ? $employee->unit_id : null;

        $studentQuery = Student::query();
        $employeeQuery = Employee::query();
        $userQuery = User::query()->where('is_active', true);
        $classQuery = Kelas::query()->where('status', 'Aktif');
        if ($unitId) {
            $studentQuery->where('unit_id', $unitId);
            $employeeQuery->where('unit_id', $unitId);
            $userQuery->whereHas('employee', fn ($query) => $query->where('unit_id', $unitId));
            $classQuery->where('unit_pendidikan_id', $unitId);
        }

        $totalSiswa = (clone $studentQuery)->where('is_active', true)->count();
        $totalPegawai = $employeeQuery->count();
        $totalUsers = $userQuery->count();
        $totalKelasAktif = $classQuery->count();

        $kpis = [
            'total_users' => ['total' => $totalUsers, 'growth' => 0],
            'total_students' => ['total' => $totalSiswa, 'growth' => 0],
            'total_employees' => ['total' => $totalPegawai, 'growth' => 0],
            'active_classes' => ['total' => $totalKelasAktif, 'growth' => 0],
        ];

        return [
            'context' => [
                'role' => 'Operator Sekolah',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [
                'data_density' => [
                    ['name' => 'Siswa', 'total' => $totalSiswa],
                    ['name' => 'Pegawai', 'total' => $totalPegawai],
                    ['name' => 'Kelas Aktif', 'total' => $totalKelasAktif],
                ],
            ],
            'tables' => [
                'recent_activities' => [],
            ],
        ];
    }
}
