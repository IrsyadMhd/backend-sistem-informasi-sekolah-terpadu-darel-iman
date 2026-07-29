<?php

namespace App\Repositories\Contracts;

use App\Models\TujuanPembelajaran;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TujuanPembelajaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?TujuanPembelajaran;

    public function getByCpId(string $cpId): Collection;

    public function create(array $data): TujuanPembelajaran;

    public function update(string $id, array $data): ?TujuanPembelajaran;

    public function delete(string $id): bool;

    public function restore(string $id): bool;
}
