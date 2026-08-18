<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GuruTahfizhDashboardService
{
    public function getDashboardOverview($user, array $filters = []): array
    {
        $teacher = Teacher::where('user_id', $user->id)->first();
        $employee = Employee::where('user_id', $user->id)->first();
        $teacherId = $teacher?->id;
        $employeeId = $employee?->id;

        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        // 1. Scoped Students assigned to Tahfizh Mentor
        $assignedStudentIds = [];
        if (Schema::hasTable('tahfizh_daily_logs')) {
            $assignedStudentIds = TahfizhDailyLog::query()
                ->when($teacherId || $employeeId, function ($q) use ($teacherId, $employeeId) {
                    $q->where(function ($sq) use ($teacherId, $employeeId) {
                        if ($teacherId) {
                            $sq->where('teacher_id', $teacherId);
                        }
                        if ($employeeId) {
                            $sq->orWhere('teacher_id', $employeeId);
                        }
                    });
                })
                ->pluck('student_id')
                ->unique()
                ->filter()
                ->toArray();
        }

        // Jangan fallback ke seluruh siswa sekolah: siswa binaan = siswa yang pernah
        // menerima setoran dari guru ini. Guru tanpa data setoran menampilkan 0.
        $totalSiswaBinaan = count($assignedStudentIds);

        // Today's deposits
        $today = now()->toDateString();
        $setoranHariIni = TahfizhDailyLog::whereDate('record_date', $today)
            ->whereIn('student_id', $assignedStudentIds)
            ->count();

        $siswaSudahSetor = TahfizhDailyLog::whereDate('record_date', $today)
            ->whereIn('student_id', $assignedStudentIds)
            ->pluck('student_id')
            ->unique()
            ->count();

        $siswaBelumSetor = max(0, $totalSiswaBinaan - $siswaSudahSetor);

        $totalSetoranBaris = TahfizhDailyLog::whereIn('student_id', $assignedStudentIds)->sum('hafalan_baris');
        $totalMurajaahLembar = TahfizhDailyLog::whereIn('student_id', $assignedStudentIds)->sum('murajaah_lembar');

        $kpis = [
            'total_siswa_binaan' => ['total' => $totalSiswaBinaan, 'growth' => 0],
            'setoran_hari_ini' => ['total' => $setoranHariIni, 'growth' => 0],
            'siswa_sudah_setor' => ['total' => $siswaSudahSetor, 'growth' => 0],
            'siswa_belum_setor' => ['total' => $siswaBelumSetor, 'growth' => 0],
            'total_setoran_baris' => ['total' => (int) $totalSetoranBaris, 'growth' => 0],
            'total_murajaah_lembar' => ['total' => (float) $totalMurajaahLembar, 'growth' => 0],
        ];

        // Recent deposits list
        $recentLogs = TahfizhDailyLog::with('student')
            ->whereIn('student_id', $assignedStudentIds)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'context' => [
                'role' => 'Guru Tahfizh',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [
                'setoran_summary' => [
                    ['status' => 'Sudah Setor', 'total' => $siswaSudahSetor],
                    ['status' => 'Belum Setor', 'total' => $siswaBelumSetor],
                ],
            ],
            'tables' => [
                'recent_logs' => $recentLogs,
            ],
        ];
    }
}
