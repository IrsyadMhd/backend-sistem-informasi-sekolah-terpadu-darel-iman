<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TataUsahaDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardOverview($user, array $filters = []): array
    {
        $unitQuery = $this->accessScope->accessibleEducationUnits($user);
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $this->accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
            $unitQuery->whereKey($filters['unit_id']);
        }
        $unitId = $unitQuery->value('id');

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        $studentQuery = Student::query()->whereIn('unit_id', array_filter([$unitId]));
        $employeeQuery = Employee::query()->whereIn('unit_id', array_filter([$unitId]));

        $totalSiswa = (clone $studentQuery)->where('is_active', true)->count();
        $totalPegawai = (clone $employeeQuery)->count();

        // Incomplete student records
        $siswaIncomplete = (clone $studentQuery)->where(function ($q) {
            $q->whereNull('nisn')->orWhereNull('birth_date')->orWhereNull('parent_id');
        })->count();

        // Incomplete employee records
        $pegawaiIncomplete = (clone $employeeQuery)->where(function ($q) {
            $q->whereNull('niy')->orWhereNull('nik');
        })->count();

        $today = now()->toDateString();
        $absensiHariIni = 0;
        if (Schema::hasTable('attendances')) {
            $attQuery = DB::table('attendances')
                ->whereDate('attendance_date', $today)
                ->whereIn('student_id', (clone $studentQuery)->pluck('id'));
            $absensiHariIni = $attQuery->count();
        }

        $kpis = [
            'total_siswa' => ['total' => $totalSiswa, 'growth' => 0],
            'total_pegawai' => ['total' => $totalPegawai, 'growth' => 0],
            'siswa_incomplete' => ['total' => $siswaIncomplete, 'growth' => 0],
            'pegawai_incomplete' => ['total' => $pegawaiIncomplete, 'growth' => 0],
            'absensi_hari_ini' => ['total' => $absensiHariIni, 'growth' => 0],
        ];

        return [
            'context' => [
                'role' => 'Tata Usaha',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [],
            'tables' => [],
        ];
    }
}
