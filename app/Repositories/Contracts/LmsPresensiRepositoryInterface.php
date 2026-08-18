<?php

namespace App\Repositories\Contracts;

use App\Models\LmsPresensi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsPresensiRepositoryInterface
{
    public function getPaginated(array $filters = [], int $perPage = 15, string $orderBy = 'tanggal', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsPresensi;

    public function create(array $data): LmsPresensi;

    public function update(string $id, array $data): ?LmsPresensi;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function bulkUpsert(string $jadwalPelajaranId, string $tanggal, int $pertemuanKe, array $presensiItems): Collection;

    public function getStats(array $filters = []): array;

    public function getOptions(?string $employeeId = null): array;
}
