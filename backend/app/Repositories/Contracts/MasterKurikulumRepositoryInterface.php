<?php

namespace App\Repositories\Contracts;

use App\Models\MasterKurikulum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MasterKurikulumRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?MasterKurikulum;

    public function create(array $data): MasterKurikulum;

    public function update(string $id, array $data): ?MasterKurikulum;

    public function delete(string $id, ?string $deletedBy = null): bool;

    public function restore(string $id): bool;

    public function getStats(): array;

    public function getDropdownOptions(?string $unitPendidikanId = null): Collection;
}
