<?php

namespace App\Services;

use App\Models\LmsPresensi;
use App\Repositories\Contracts\LmsPresensiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsPresensiService
{
    public function __construct(
        protected LmsPresensiRepositoryInterface $repository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'tanggal', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id): ?LmsPresensi
    {
        return $this->repository->findById($id);
    }

    public function simpan(array $data): LmsPresensi
    {
        return $this->repository->create($data);
    }

    public function ubah(string $id, array $data): ?LmsPresensi
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

    public function simpanBulk(string $jadwalPelajaranId, string $tanggal, int $pertemuanKe, array $presensiItems): Collection
    {
        return $this->repository->bulkUpsert($jadwalPelajaranId, $tanggal, $pertemuanKe, $presensiItems);
    }

    public function dapatkanStatistik(array $filters = []): array
    {
        return $this->repository->getStats($filters);
    }

    public function dapatkanOpsi(?string $employeeId = null): array
    {
        return $this->repository->getOptions($employeeId);
    }
}
