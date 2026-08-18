<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Semester;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Repositories\Contracts\LmsPenilaianRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class LmsPenilaianService
{
    public function __construct(
        protected LmsPenilaianRepositoryInterface $penilaianRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->penilaianRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?StudentGrade
    {
        return $this->penilaianRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): StudentGrade
    {
        $grade = $this->penilaianRepository->create($data);

        Log::info('[AUDIT LOG] Input Manual / Membuat Rekap Penilaian Siswa', [
            'grade_id' => $grade->id,
            'student_id' => $grade->student_id,
            'subject_id' => $grade->subject_id,
            'final_score' => $grade->final_score,
            'user_id' => auth()->id(),
        ]);

        return $grade;
    }

    public function ubah(string $id, array $data): ?StudentGrade
    {
        $existing = $this->penilaianRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->penilaianRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Nilai / Manual Override Penilaian Siswa', [
            'grade_id' => $id,
            'student_id' => $existing->student_id,
            'nilai_sebelum' => $existing->final_score,
            'nilai_sesudah' => $updated->final_score ?? $existing->final_score,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $grade = $this->penilaianRepository->findById($id);
        if (! $grade) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Rekap Penilaian Siswa (Soft Delete)', [
            'grade_id' => $id,
            'student_id' => $grade->student_id,
            'user_id' => auth()->id(),
        ]);

        return $this->penilaianRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Rekap Penilaian Siswa', [
            'grade_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->penilaianRepository->restore($id);
    }

    public function kalkulasiKeterkaitanCbt(string $kelasId, string $subjectId, string $semesterId, array $weights = []): Collection
    {
        $records = $this->penilaianRepository->calculateAndSyncClass($kelasId, $subjectId, $semesterId, $weights);

        Log::info('[AUDIT LOG] Melakukan Kalkulasi Otomatis Penilaian Kelas (CBT + Penugasan)', [
            'kelas_id' => $kelasId,
            'subject_id' => $subjectId,
            'semester_id' => $semesterId,
            'total_siswa' => $records->count(),
            'weights' => $weights,
            'user_id' => auth()->id(),
        ]);

        return $records;
    }

    public function statistik(array $filters = []): array
    {
        return $this->penilaianRepository->getStats($filters);
    }

    public function opsi(): array
    {
        $kelas = Kelas::orderBy('nama_kelas', 'asc')->get(['id', 'nama_kelas']);
        $subjects = Subject::orderBy('name', 'asc')->get(['id', 'code', 'name']);
        $semesters = Semester::get(['id', 'name'])
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'nama_semester' => $semester->nama_semester,
            ])
            ->values();

        $defaultFormula = [
            'bobot_tugas' => 20.0,
            'bobot_uh' => 25.0,
            'bobot_uts' => 25.0,
            'bobot_uas' => 30.0,
            'nilai_kkm' => 75.0,
            'rumus_deskripsi' => 'Nilai Akhir = (20% Tugas) + (25% CBT UH) + (25% CBT UTS) + (30% CBT UAS)',
        ];

        return [
            'kelas' => $kelas,
            'subjects' => $subjects,
            'semesters' => $semesters,
            'default_formula' => $defaultFormula,
        ];
    }
}
