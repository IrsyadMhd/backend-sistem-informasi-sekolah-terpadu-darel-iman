<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsModulAjar;
use App\Models\LmsUjian;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use Illuminate\Support\Facades\Schema;

class WakaKurikulumDashboardService
{
    public function getDashboardOverview($user, array $filters = []): array
    {
        $employee = Employee::where('user_id', $user->id)->first();
        $unitId = $employee ? $employee->unit_id : null;

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // Curriculum KPIs
        $totalSubjects = Subject::count();
        $totalSchedules = ClassSchedule::count();
        $totalCP = CapaianPembelajaran::count();
        $totalTP = TujuanPembelajaran::count();

        $totalModulAjar = LmsModulAjar::count();
        $totalKisiKisi = LmsKisiKisi::count();
        $totalBankSoal = LmsBankSoal::count();
        $totalUjianCbt = LmsUjian::count();

        $kpis = [
            'total_subjects' => ['total' => $totalSubjects, 'growth' => 0],
            'total_schedules' => ['total' => $totalSchedules, 'growth' => 0],
            'total_cp' => ['total' => $totalCP, 'growth' => 0],
            'total_tp' => ['total' => $totalTP, 'growth' => 0],
            'total_modul_ajar' => ['total' => $totalModulAjar, 'growth' => 0],
            'total_kisi_kisi' => ['total' => $totalKisiKisi, 'growth' => 0],
            'total_bank_soal' => ['total' => $totalBankSoal, 'growth' => 0],
            'total_ujian_cbt' => ['total' => $totalUjianCbt, 'growth' => 0],
        ];

        return [
            'context' => [
                'role' => 'Waka Kurikulum',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [],
            'tables' => [],
        ];
    }
}
