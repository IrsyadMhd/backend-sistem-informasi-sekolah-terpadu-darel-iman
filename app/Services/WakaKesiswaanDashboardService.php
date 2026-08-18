<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WakaKesiswaanDashboardService
{
    public function getDashboardOverview($user, array $filters = []): array
    {
        $employee = Employee::where('user_id', $user->id)->first();
        $unitId = $employee ? $employee->unit_id : null;

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        $studentQuery = Student::query();
        if ($unitId) {
            $studentQuery->where('unit_id', $unitId);
        }

        $totalSiswa = (clone $studentQuery)->count();
        $siswaAktif = (clone $studentQuery)->where('is_active', true)->count();

        // Attendance & Tardiness today
        $today = now()->toDateString();
        $terlambat = 0;
        $tidakHadir = 0;
        if (Schema::hasTable('attendances')) {
            $attQuery = DB::table('attendances')->whereDate('attendance_date', $today);
            if ($unitId) {
                $attQuery->whereIn('student_id', (clone $studentQuery)->pluck('id'));
            }
            $terlambat = (clone $attQuery)->where('status', 'late')->count();
            $tidakHadir = (clone $attQuery)->whereIn('status', ['absent', 'alpha', 'sick', 'permission'])->count();
        }

        // Student notes / BK cases (scope ke siswa di unit)
        $studentIds = (clone $studentQuery)->pluck('id');
        $totalCatatan = StudentNote::when($unitId, fn ($q) => $q->whereIn('student_id', $studentIds))->count();
        $totalPrestasi = 0;
        if (Schema::hasTable('rekap_prestasi_siswas')) {
            $prestasiQuery = DB::table('rekap_prestasi_siswas');
            if ($unitId) {
                $prestasiQuery->whereIn('id_siswa', $studentIds);
            }
            $totalPrestasi = $prestasiQuery->count();
        }

        $kpis = [
            'total_siswa' => ['total' => $totalSiswa, 'growth' => 0],
            'siswa_aktif' => ['total' => $siswaAktif, 'growth' => 0],
            'siswa_terlambat' => ['total' => $terlambat, 'growth' => 0],
            'siswa_tidak_hadir' => ['total' => $tidakHadir, 'growth' => 0],
            'catatan_siswa' => ['total' => $totalCatatan, 'growth' => 0],
            'prestasi_siswa' => ['total' => $totalPrestasi, 'growth' => 0],
        ];

        return [
            'context' => [
                'role' => 'Waka Kesiswaan',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [],
            'tables' => [],
        ];
    }
}
