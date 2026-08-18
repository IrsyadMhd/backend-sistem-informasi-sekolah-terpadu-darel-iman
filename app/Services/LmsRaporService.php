<?php

namespace App\Services;

use App\Models\LmsRapor;
use App\Models\StudentGrade;
use App\Repositories\Contracts\LmsRaporRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class LmsRaporService
{
    public function __construct(
        protected LmsRaporRepositoryInterface $raporRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->raporRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsRapor
    {
        return $this->raporRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsRapor
    {
        $rapor = $this->raporRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Rapor Digital Siswa Manual', [
            'rapor_id' => $rapor->id,
            'siswa_id' => $rapor->siswa_id,
            'semester_id' => $rapor->semester_id,
            'tahun_ajaran_id' => $rapor->tahun_ajaran_id,
            'user_id' => auth()->id(),
        ]);

        return $rapor;
    }

    public function ubah(string $id, array $data): ?LmsRapor
    {
        $existing = $this->raporRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->raporRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Data / Catatan Rapor Digital', [
            'rapor_id' => $id,
            'siswa_id' => $existing->siswa_id,
            'status_sebelum' => $existing->status_rapor,
            'status_sesudah' => $updated->status_rapor,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $rapor = $this->raporRepository->findById($id);
        if (! $rapor) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Rapor Digital Siswa (Soft Delete)', [
            'rapor_id' => $id,
            'siswa_id' => $rapor->siswa_id,
            'user_id' => auth()->id(),
        ]);

        return $this->raporRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Rapor Digital Siswa', [
            'rapor_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->raporRepository->restore($id);
    }

    public function generateClass(string $kelasId, string $semesterId, string $tahunAjaranId): Collection
    {
        $records = $this->raporRepository->generateForClass($kelasId, $semesterId, $tahunAjaranId);

        Log::info('[AUDIT LOG] Auto-Generate & Kalkulasi Rapor Kelas', [
            'kelas_id' => $kelasId,
            'semester_id' => $semesterId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'total_rapor' => $records->count(),
            'user_id' => auth()->id(),
        ]);

        return $records;
    }

    public function generateStudent(string $siswaId, string $semesterId, string $tahunAjaranId, ?string $kelasId = null): LmsRapor
    {
        $rapor = $this->raporRepository->generateForStudent($siswaId, $semesterId, $tahunAjaranId, $kelasId);

        Log::info('[AUDIT LOG] Auto-Generate Rapor Individual Siswa', [
            'siswa_id' => $siswaId,
            'rapor_id' => $rapor->id,
            'user_id' => auth()->id(),
        ]);

        return $rapor;
    }

    public function getPdfData(string $id): array
    {
        $rapor = $this->raporRepository->findById($id, true);
        if (! $rapor) {
            throw new \Exception('Rapor tidak ditemukan.');
        }

        // Ambil per-subject StudentGrade (Penilaian) untuk rincian tabel Rapor
        $grades = StudentGrade::with(['subject'])
            ->where('student_id', $rapor->siswa_id)
            ->where('semester_id', $rapor->semester_id)
            ->where('academic_year_id', $rapor->tahun_ajaran_id)
            ->get();

        return [
            'rapor' => $rapor,
            'siswa' => $rapor->siswa,
            'kelas' => $rapor->kelas,
            'semester' => $rapor->semester,
            'tahun_ajaran' => $rapor->tahunAjaran,
            'wali_kelas' => $rapor->waliKelas,
            'grades' => $grades->map(function ($g) {
                return [
                    'id' => $g->id,
                    'subject_name' => $g->subject?->nama_pelajaran ?? $g->subject?->name ?? 'Mata Pelajaran',
                    'subject_code' => $g->subject?->kode_pelajaran ?? 'MP',
                    'kkm' => 75.0,
                    'score_assignment' => $g->score_assignment,
                    'score_quiz' => $g->score_quiz,
                    'score_project' => $g->score_project,
                    'score_midterm' => $g->score_midterm,
                    'score_final' => $g->score_final,
                    'final_score' => $g->final_score,
                    'grade_letter' => $g->grade_letter ?? StudentGrade::getGradeLetter((float) $g->final_score),
                    'is_passed' => $g->is_passed,
                    'notes' => $g->notes,
                ];
            }),
            'school_info' => [
                'nama_sekolah' => 'SMK / SMA ISLAM TERPADU EXCELLENCE',
                'npsn' => '10801234',
                'alamat' => 'Jl. Pendidikan Karakter No. 1, Kota Mandiri',
                'kepala_sekolah' => 'Dr. H. Ahmad Dahlan, M.Pd.',
                'nip_kepsek' => '19750817 200003 1 001',
            ],
        ];
    }

    public function statistik(array $filters = []): array
    {
        return $this->raporRepository->getStats($filters);
    }

    public function opsi(): array
    {
        return $this->raporRepository->getOptions();
    }
}
