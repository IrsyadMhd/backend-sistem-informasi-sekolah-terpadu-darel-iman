<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\StudentNote;
use App\Models\Teacher;
use Illuminate\Support\Facades\Schema;

class GuruBkDashboardService
{
    public function getDashboardOverview($user, array $filters = []): array
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        $teacher = Teacher::where('user_id', $user->id)->first();
        $employee = Employee::where('user_id', $user->id)->first();
        $teacherId = $teacher?->id;
        $employeeId = $employee?->id;

        // Scoped Notes for Counselors (Confidentiality Protected)
        $notesQuery = StudentNote::query()
            ->when($teacherId || $employeeId, function ($q) use ($teacherId, $employeeId) {
                $q->where(function ($sq) use ($teacherId, $employeeId) {
                    if ($teacherId) {
                        $sq->where('teacher_id', $teacherId);
                    }
                    if ($employeeId) {
                        $sq->orWhere('teacher_id', $employeeId);
                    }
                });
            });

        $totalCatatan = (clone $notesQuery)->count();
        $siswaDalamPendampingan = (clone $notesQuery)->distinct('student_id')->count('student_id');
        $kasusMenungguTindakLanjut = (clone $notesQuery)->whereNotNull('follow_up')->count();
        $kasusPrioritasTinggi = (clone $notesQuery)->whereIn('priority', ['tinggi', 'high', 'urgent'])->count();

        $kpis = [
            'total_konseling' => ['total' => $totalCatatan, 'growth' => 0],
            'siswa_dalam_pendampingan' => ['total' => $siswaDalamPendampingan, 'growth' => 0],
            'kasus_menunggu_tindak_lanjut' => ['total' => $kasusMenungguTindakLanjut, 'growth' => 0],
            'kasus_prioritas_tinggi' => ['total' => $kasusPrioritasTinggi, 'growth' => 0],
        ];

        // Active cases table (Non-sensitive general category labels)
        $recentNotes = (clone $notesQuery)
            ->with('student:id,full_name,nisn')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'student_id', 'date', 'category', 'title', 'priority', 'follow_up']);

        return [
            'context' => [
                'role' => 'Guru BK',
                'tahun_ajaran' => $activeAcademicYear ? ['id' => $activeAcademicYear->id, 'nama' => $activeAcademicYear->name ?? $activeAcademicYear->year_name ?? $activeAcademicYear->nama] : null,
                'semester' => $activeSemester ? ['id' => $activeSemester->id, 'nama' => $activeSemester->name ?? $activeSemester->nama] : null,
            ],
            'kpis' => $kpis,
            'charts' => [],
            'tables' => [
                'cases' => $recentNotes,
            ],
        ];
    }
}
