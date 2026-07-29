<?php

namespace App\Repositories\Contracts;

use App\Models\LmsBankSoal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsBankSoalRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id, bool $withTrashed = false): ?LmsBankSoal;

    public function create(array $data): LmsBankSoal;

    public function update(string $id, array $data): ?LmsBankSoal;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function duplicate(string $id): ?LmsBankSoal;

    public function getStats(array $filters = []): array;

    public function getByKisiKisiId(string $kisiKisiId): Collection;
}
