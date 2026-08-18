<?php

namespace App\Repositories\Contracts;

use App\Models\LmsReferensi;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsReferensiRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsReferensi;

    public function getByModulAjarId(string $modulAjarId): Collection;

    public function create(array $data): LmsReferensi;

    public function update(string $id, array $data): ?LmsReferensi;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function getStats(): array;
}
