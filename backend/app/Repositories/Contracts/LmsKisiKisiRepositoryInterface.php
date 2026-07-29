<?php

namespace App\Repositories\Contracts;

use App\Models\LmsKisiKisi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LmsKisiKisiRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?LmsKisiKisi;

    public function create(array $data): LmsKisiKisi;

    public function update(string $id, array $data): ?LmsKisiKisi;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function duplicate(string $id): ?LmsKisiKisi;

    public function getStats(): array;
}
