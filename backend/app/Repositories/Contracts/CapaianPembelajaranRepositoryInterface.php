<?php

namespace App\Repositories\Contracts;

use App\Models\CapaianPembelajaran;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CapaianPembelajaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function getDropdownOptions(array $filters = []): Collection;

    public function findById(string $id, bool $withTrashed = false): ?CapaianPembelajaran;

    public function create(array $data): CapaianPembelajaran;

    public function update(string $id, array $data): ?CapaianPembelajaran;

    public function delete(string $id): bool;

    public function restore(string $id): bool;
}
