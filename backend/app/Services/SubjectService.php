<?php

namespace App\Services;

use App\Repositories\Contracts\SubjectRepositoryInterface;
use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SubjectService
 * Layer bisnis logic untuk pengelolaan data Master Mata Pelajaran (Subject).
 */
class SubjectService
{
    public function __construct(
        protected SubjectRepositoryInterface $subjectRepository
    ) {}

    /**
     * Dapatkan daftar mata pelajaran terpaginasi.
     */
    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->subjectRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    /**
     * Cari detail mata pelajaran berdasarkan ID UUID.
     */
    public function cariBerdasarkanId(string $id): ?Subject
    {
        return $this->subjectRepository->findById($id);
    }

    /**
     * Simpan data mata pelajaran baru.
     */
    public function simpan(array $data): Subject
    {
        Log::info('Membuat mata pelajaran baru', ['code' => $data['code'] ?? null, 'name' => $data['name'] ?? null]);
        return $this->subjectRepository->create($data);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function ubah(string $id, array $data): ?Subject
    {
        Log::info("Memperbarui mata pelajaran ID: {$id}", $data);
        return $this->subjectRepository->update($id, $data);
    }

    /**
     * Hapus data mata pelajaran (Soft Delete).
     */
    public function hapus(string $id): bool
    {
        Log::info("Menghapus mata pelajaran ID: {$id}");
        return $this->subjectRepository->delete($id);
    }

    /**
     * Pulihkan data mata pelajaran dari sampah.
     */
    public function pulihkan(string $id): bool
    {
        Log::info("Memulihkan mata pelajaran ID: {$id}");
        return $this->subjectRepository->restore($id);
    }

    /**
     * Dapatkan statistik ringkasan mata pelajaran.
     */
    public function dapatkanStatistik(): array
    {
        return $this->subjectRepository->getStats();
    }

    /**
     * Dapatkan opsi dropdown master mata pelajaran.
     */
    public function dapatkanOpsiDropdown(): Collection
    {
        return $this->subjectRepository->getDropdownOptions();
    }
}
