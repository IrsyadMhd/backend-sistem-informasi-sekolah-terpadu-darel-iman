<?php

namespace App\Repositories\Contracts;

use App\Models\LmsMateri;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsMateriRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?LmsMateri;

    public function getByModulAjarId(string $modulAjarId): Collection;

    public function create(array $data): LmsMateri;

    public function update(string $id, array $data): ?LmsMateri;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function getStats(): array;
}
