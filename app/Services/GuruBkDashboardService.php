<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\StudentNote;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class GuruBkDashboardService
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function getDashboardOverview($user, array $filters = []): array
    {
        $activeAcademicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::latest()->first();
        $activeSemester = Semester::where('is_active', true)->first() ?? Semester::latest()->first();

        $isGlobal = $this->accessScope->hasGlobalScope($user);

        // Enforce server-side authorization scope via AccessScopeService
        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            if (! $isGlobal) {
                $this->accessScope->assertEducationUnitAccess($user, (string) $filters['unit_id']);
            }
            $targetUnitIds = collect([(string) $filters['unit_id']]);
        } else {
            if ($isGlobal) {
                $targetUnitIds = EducationUnit::pluck('id');
            } else {
                $targetUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id');
            }
        }

        if (! empty($filters['student_id'])) {
            if (! $isGlobal) {
                abort_unless(
                    $this->accessScope->accessibleStudents($user)->whereKey($filters['student_id'])->exists(),
                    403,
                    'Siswa berada di luar cakupan unit akun.'
                );
            }
        }

        // CRITICAL: If non-global user has no accessible units, force restrictive empty query (prevent unbounded foundation query)
        if (! $isGlobal && $targetUnitIds->isEmpty()) {
            $notesQuery = StudentNote::query()->whereRaw('1 = 0');
        } else {
            $notesQuery = StudentNote::query()
                ->where(function (Builder $q) use ($targetUnitIds) {
                    $q->whereIn('education_unit_id', $targetUnitIds)
                      ->orWhere(function (Builder $sq) use ($targetUnitIds) {
                          $sq->whereNull('education_unit_id')
                             ->whereHas('student', fn ($st) => $st->whereIn('unit_id', $targetUnitIds));
                      });
                })
                ->whereHas('student', function (Builder $sq) use ($targetUnitIds) {
                    $sq->whereIn('unit_id', $targetUnitIds);
                });

            if (! empty($filters['student_id'])) {
                $notesQuery->where('student_id', $filters['student_id']);
            }
        }

        $totalCatatan = (clone $notesQuery)->count();
        $siswaDalamPendampingan = (clone $notesQuery)->distinct('student_id')->count('student_id');
        $kasusMenungguTindakLanjut = (clone $notesQuery)->whereNotNull('follow_up')->where('follow_up', '!=', '')->count();
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
