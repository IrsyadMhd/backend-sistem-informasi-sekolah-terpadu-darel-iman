<?php

namespace App\Repositories\Contracts;

use App\Models\JenisUnitPendidikan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface JenisUnitPendidikanRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function findById(string|int $id): ?JenisUnitPendidikan;

    public function create(array $data): JenisUnitPendidikan;

    public function update(string|int $id, array $data): ?JenisUnitPendidikan;

    public function delete(string|int $id, int|string|null $deletedBy = null): bool;

    public function restore(string|int $id): bool;

    public function getStats(): array;

    public function getDropdownOptions(): Collection;
}
