<?php

namespace App\Repositories\Contracts;

use App\Models\LmsMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsMediaRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsMedia;

    public function getByMateriId(string $materiId): Collection;

    public function create(array $data): LmsMedia;

    public function update(string $id, array $data): ?LmsMedia;

    public function delete(string $id): bool;

    public function reorder(array $orders): bool;

    public function getStats(): array;
}
