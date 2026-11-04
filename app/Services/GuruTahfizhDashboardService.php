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

        $academicPeriodService = app(\App\Services\AcademicPeriodService::class);
        $activeAcademicYear = $academicPeriodService->getActiveYear();
        $activeSemester = $academicPeriodService->getActiveSemester($activeAcademicYear?->id);

        $isPrincipal = $user->hasAnyRole(['Kepala Sekolah', 'kepala_sekolah', 'KepalaSekolah', 'kepsek']);

        // 1. Scoped Students assigned to Tahfizh Mentor or Unit for Principal
        $assignedStudentIds = [];
        if ($isPrincipal) {
            $accessScope = app(\App\Services\AccessScopeService::class);
            $unitIds = $accessScope->accessibleEducationUnits($user)->pluck('id');
            $assignedStudentIds = Student::query()
                ->where(function ($q) {
                    $q->whereNull('metadata->mutasi_type')
                      ->orWhere('metadata->mutasi_type', '!=', 'keluar');
                })
                ->when($unitIds->isNotEmpty(), fn ($q) => $q->whereIn('unit_id', $unitIds))
                ->pluck('id')
                ->unique()
                ->filter()
                ->toArray();
        } elseif (Schema::hasTable('tahfizh_daily_logs')) {
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

        // Jangan fallback ke seluruh siswa sekolah untuk guru murni: siswa binaan = siswa yang pernah
        // menerima setoran dari guru ini. Guru tanpa data setoran menampilkan 0.
        $totalSiswaBinaan = count($assignedStudentIds);

        // Today's deposits
        $today = now()->toDateString();
        $todayLogs = TahfizhDailyLog::with([
                'student.kelas',
                'student.educationUnit',
                'student.parent',
            ])
            ->whereDate('record_date', $today)
            ->whereIn('student_id', $assignedStudentIds)
            ->orderByDesc('created_at')
            ->get();

        $setoranHariIni = $todayLogs->count();

        $siswaSudahSetorIds = $todayLogs->pluck('student_id')->unique()->filter()->values()->toArray();
        $siswaSudahSetor = count($siswaSudahSetorIds);
        $siswaBelumSetor = max(0, $totalSiswaBinaan - $siswaSudahSetor);

        // Map today's deposited students details
        $sudahSetorList = $todayLogs->map(function ($log) {
            $student = $log->student;
            return [
                'id' => $log->id,
                'student_id' => $log->student_id,
                'student_name' => $student?->full_name ?? $student?->name ?? 'Santri',
                'nis' => $student?->nis ?? '-',
                'gender' => $student?->gender ?? null,
                'photo_url' => $student?->photo_url ?? null,
                'unit_name' => $student?->educationUnit?->name ?? $student?->unit_name ?? '-',
                'class_name' => $student?->kelas?->name ?? $student?->kelas?->nama_kelas ?? $student?->schoolClass?->name ?? '-',
                'surah_name' => $log->hafalan_surah_name ?? 'Surah',
                'surah_number' => $log->hafalan_surah_number,
                'ayah_start' => $log->hafalan_ayah_start ?? 1,
                'ayah_end' => $log->hafalan_ayah_end ?? 1,
                'baris' => $log->hafalan_baris ?? 0,
                'murajaah' => $log->murajaah_text ?: ($log->murajaah_lembar ? "{$log->murajaah_lembar} Lembar" : null),
                'kelancaran' => $log->kelancaran_label ?? 'Lancar',
                'tajwid' => $log->tajwid_label ?? 'Baik',
                'notes_teacher' => $log->notes_teacher,
                'created_at' => $log->created_at?->toIso8601String(),
                'time' => $log->created_at ? $log->created_at->format('H:i') : null,
                'parent_phone' => $student?->parent?->phone,
            ];
        })->values()->all();

        // Map today's not-yet-deposited students
        $belumSetorStudentIds = array_values(array_diff($assignedStudentIds, $siswaSudahSetorIds));
        $belumSetorList = [];
        if (!empty($belumSetorStudentIds)) {
            $belumSetorList = Student::with(['kelas', 'educationUnit', 'parent'])
                ->whereIn('id', $belumSetorStudentIds)
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'student_id' => $student->id,
                        'student_name' => $student->full_name ?? $student->name ?? 'Santri',
                        'nis' => $student->nis ?? '-',
                        'gender' => $student->gender ?? null,
                        'photo_url' => $student->photo_url ?? null,
                        'unit_name' => $student->educationUnit?->name ?? $student->unit_name ?? '-',
                        'class_name' => $student->kelas?->name ?? $student->kelas?->nama_kelas ?? $student->schoolClass?->name ?? '-',
                        'parent_name' => $student->parent?->full_name ?? '-',
                        'parent_phone' => $student->parent?->phone,
                    ];
                })->values()->all();
        }

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
                'role' => $isPrincipal ? 'Kepala Sekolah (Supervisi Tahfizh)' : 'Guru Tahfizh',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [
                'setoran_summary' => [
                    ['status' => 'Sudah Setor', 'total' => $siswaSudahSetor],
                    ['status' => 'Belum Setor', 'total' => $siswaBelumSetor],
                ],
                'setoran_details' => [
                    'sudah_setor' => $sudahSetorList,
                    'belum_setor' => $belumSetorList,
                    'total_binaan' => $totalSiswaBinaan,
                    'sudah_count' => $siswaSudahSetor,
                    'belum_count' => $siswaBelumSetor,
                    'persentase_selesai' => $totalSiswaBinaan > 0 ? round(($siswaSudahSetor / $totalSiswaBinaan) * 100, 1) : 0,
                ],
            ],
            'tables' => [
                'recent_logs' => $recentLogs,
            ],
        ];
    }
}
