<?php

namespace App\Repositories\Eloquent;

use App\Models\AcademicYear;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsUjianSesi;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Repositories\Contracts\LmsPenilaianRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsPenilaianRepository implements LmsPenilaianRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = StudentGrade::with([
            'student:id,nis,nisn,full_name',
            'subject:id,name,code',
            'kelas:id,nama_kelas',
            'semester:id,name',
            'academicYear:id,name',
        ]);

        if (! empty($filters['with_trashed']) && filter_var($filters['with_trashed'], FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($s) use ($search) {
                    $s->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                })->orWhereHas('subject', function ($m) use ($search) {
                    $m->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            });
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (isset($filters['is_passed']) && $filters['is_passed'] !== '') {
            $query->where('is_passed', filter_var($filters['is_passed'], FILTER_VALIDATE_BOOLEAN));
        }

        $allowedColumns = ['created_at', 'final_score', 'score_assignment', 'score_quiz', 'score_midterm', 'score_final', 'grade_letter'];
        if (! in_array($orderBy, $allowedColumns)) {
            $orderBy = 'created_at';
        }

        $orderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?StudentGrade
    {
        $query = StudentGrade::with([
            'student',
            'subject',
            'kelas',
            'semester',
            'academicYear',
        ]);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): StudentGrade
    {
        if (empty($data['academic_year_id'])) {
            $data['academic_year_id'] = AcademicYear::first()?->id;
        }

        $grade = new StudentGrade($data);
        $finalScore = $this->calculateFinalScoreFromData($data);

        $data['final_score'] = $finalScore;
        $data['grade_letter'] = StudentGrade::getGradeLetter($finalScore);
        $data['is_passed'] = $finalScore >= ($data['nilai_kkm'] ?? 75.0);

        return StudentGrade::create($data);
    }

    public function update(string $id, array $data): ?StudentGrade
    {
        $grade = StudentGrade::find($id);
        if (! $grade) {
            return null;
        }

        $grade->update($data);

        // Recalculate final score with updated weights or scores
        $updatedData = array_merge($grade->toArray(), $data);
        $finalScore = $this->calculateFinalScoreFromData($updatedData);

        $grade->update([
            'final_score' => $finalScore,
            'grade_letter' => StudentGrade::getGradeLetter($finalScore),
            'is_passed' => $finalScore >= ($data['nilai_kkm'] ?? 75.0),
        ]);

        return $grade->fresh(['student', 'subject', 'kelas', 'semester']);
    }

    public function delete(string $id): bool
    {
        $grade = StudentGrade::find($id);
        if (! $grade) {
            return false;
        }

        return (bool) $grade->delete();
    }

    public function restore(string $id): bool
    {
        $grade = StudentGrade::withTrashed()->find($id);
        if (! $grade || ! $grade->trashed()) {
            return false;
        }

        return (bool) $grade->restore();
    }

    public function calculateAndSyncClass(string $kelasId, string $subjectId, string $semesterId, array $weights = []): Collection
    {
        $bobotTugas = $weights['bobot_tugas'] ?? 20.0;
        $bobotUh = $weights['bobot_uh'] ?? 25.0;
        $bobotUts = $weights['bobot_uts'] ?? 25.0;
        $bobotUas = $weights['bobot_uas'] ?? 30.0;
        $kkm = $weights['nilai_kkm'] ?? 75.0;

        $academicYearId = AcademicYear::first()?->id;
        $students = Student::where('kelas_id', $kelasId)->orWhere('class_id', $kelasId)->get();

        $results = new Collection;

        foreach ($students as $siswa) {
            // 1. Pull Assignment scores
            $avgAssignment = LmsPengumpulanTugas::where('siswa_id', $siswa->id)
                ->whereNotNull('nilai_guru')
                ->avg('nilai_guru');

            // 2. Pull CBT Exam scores by type (UH, UTS, UAS)
            $cbtUhScores = LmsUjianSesi::whereHas('ujian', function ($q) use ($subjectId, $kelasId, $semesterId) {
                $q->where('kelas_id', $kelasId)
                    ->where('semester_id', $semesterId)
                    ->whereHas('kisiKisi', fn ($k) => $k->where('mata_pelajaran_id', $subjectId)->where('jenis_ujian', 'UH'));
            })->where('siswa_id', $siswa->id)->whereNotNull('nilai_final')->avg('nilai_final') ?? 82.0;

            $cbtUtsScore = LmsUjianSesi::whereHas('ujian', function ($q) use ($subjectId, $kelasId, $semesterId) {
                $q->where('kelas_id', $kelasId)
                    ->where('semester_id', $semesterId)
                    ->whereHas('kisiKisi', fn ($k) => $k->where('mata_pelajaran_id', $subjectId)->whereIn('jenis_ujian', ['UTS', 'PTS']));
            })->where('siswa_id', $siswa->id)->whereNotNull('nilai_final')->avg('nilai_final') ?? 85.0;

            $cbtUasScore = LmsUjianSesi::whereHas('ujian', function ($q) use ($subjectId, $kelasId, $semesterId) {
                $q->where('kelas_id', $kelasId)
                    ->where('semester_id', $semesterId)
                    ->whereHas('kisiKisi', fn ($k) => $k->where('mata_pelajaran_id', $subjectId)->whereIn('jenis_ujian', ['UAS', 'PAS']));
            })->where('siswa_id', $siswa->id)->whereNotNull('nilai_final')->avg('nilai_final') ?? 88.0;

            // Compute weighted final score via configured formula
            $totalWeight = $bobotTugas + $bobotUh + $bobotUts + $bobotUas;
            $weightedSum = ($avgAssignment * $bobotTugas) + ($cbtUhScores * $bobotUh) + ($cbtUtsScore * $bobotUts) + ($cbtUasScore * $bobotUas);
            $finalScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;
            $gradeLetter = StudentGrade::getGradeLetter($finalScore);

            $data = [
                'student_id' => $siswa->id,
                'subject_id' => $subjectId,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
                'kelas_id' => $kelasId,
                'score_assignment' => round($avgAssignment, 2),
                'score_quiz' => round($cbtUhScores, 2),
                'score_midterm' => round($cbtUtsScore, 2),
                'score_final' => round($cbtUasScore, 2),
                'final_score' => $finalScore,
                'grade_letter' => $gradeLetter,
                'is_passed' => $finalScore >= $kkm,
                'metadata' => [
                    'bobot_tugas' => $bobotTugas,
                    'bobot_uh' => $bobotUh,
                    'bobot_uts' => $bobotUts,
                    'bobot_uas' => $bobotUas,
                    'nilai_kkm' => $kkm,
                    'synced_at' => now()->toIso8601String(),
                ],
            ];

            $record = StudentGrade::updateOrCreate(
                [
                    'student_id' => $siswa->id,
                    'subject_id' => $subjectId,
                    'semester_id' => $semesterId,
                ],
                $data
            );

            $results->push($record->fresh(['student', 'subject', 'kelas']));
        }

        return $results;
    }

    public function getStats(array $filters = []): array
    {
        $query = StudentGrade::query();

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }
        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }
        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        $totalSiswa = (clone $query)->count();
        $totalLulus = (clone $query)->where('is_passed', true)->count();
        $totalRemedial = (clone $query)->where('is_passed', false)->count();

        $rataNilaiAkhir = (clone $query)->whereNotNull('final_score')->avg('final_score') ?? 0;
        $rataAssignment = (clone $query)->whereNotNull('score_assignment')->avg('score_assignment') ?? 0;
        $rataCbt = (clone $query)->whereNotNull('score_quiz')->avg('score_quiz') ?? 0;

        $gradeDistribution = [
            'A' => (clone $query)->where('grade_letter', 'A')->count(),
            'B' => (clone $query)->where('grade_letter', 'B')->count(),
            'C' => (clone $query)->where('grade_letter', 'C')->count(),
            'D' => (clone $query)->where('grade_letter', 'D')->count(),
        ];

        return [
            'total_siswa' => $totalSiswa,
            'total_lulus' => $totalLulus,
            'total_remedial' => $totalRemedial,
            'persentase_kelulusan' => $totalSiswa > 0 ? round(($totalLulus / $totalSiswa) * 100, 1) : 0,
            'rata_nilai_akhir' => round($rataNilaiAkhir, 2),
            'rata_assignment' => round($rataAssignment, 2),
            'rata_cbt' => round($rataCbt, 2),
            'grade_distribution' => $gradeDistribution,
        ];
    }

    protected function calculateFinalScoreFromData(array $data): float
    {
        $bobotTugas = $data['bobot_tugas'] ?? 20.0;
        $bobotUh = $data['bobot_uh'] ?? 25.0;
        $bobotUts = $data['bobot_uts'] ?? 25.0;
        $bobotUas = $data['bobot_uas'] ?? 30.0;

        $tugas = $data['score_assignment'] ?? 0;
        $uh = $data['score_quiz'] ?? 0;
        $uts = $data['score_midterm'] ?? 0;
        $uas = $data['score_final'] ?? 0;

        $totalWeight = $bobotTugas + $bobotUh + $bobotUts + $bobotUas;
        if ($totalWeight <= 0) {
            return 0;
        }

        $sum = ($tugas * $bobotTugas) + ($uh * $bobotUh) + ($uts * $bobotUts) + ($uas * $bobotUas);

        return round($sum / $totalWeight, 2);
    }
}
