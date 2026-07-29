<?php

namespace App\Services;

use App\Models\LmsPengumpulanTugas;
use App\Repositories\Contracts\LmsPengumpulanTugasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsPengumpulanTugasService
{
    public function __construct(
        protected LmsPengumpulanTugasRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsPengumpulanTugas
    {
        return $this->repository->findById($id);
    }

    public function dapatkanBerdasarkanPenugasan(string $penugasanId): Collection
    {
        return $this->repository->getByPenugasanId($penugasanId);
    }

    public function dapatkanBerdasarkanSiswa(string $siswaId): Collection
    {
        return $this->repository->getBySiswaId($siswaId);
    }

    public function simpan(array $data): LmsPengumpulanTugas
    {
        return $this->repository->create($data);
    }

    public function ubah(string $id, array $data): ?LmsPengumpulanTugas
    {
        return $this->repository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        return $this->repository->restore($id);
    }

    public function dapatkanStatistik(): array
    {
        return $this->repository->getStats();
    }

    public function dapatkanOpsi(): array
    {
        return $this->repository->getOptions();
    }
}
