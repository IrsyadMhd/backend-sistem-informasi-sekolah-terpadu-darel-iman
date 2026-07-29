<?php

namespace App\Repositories\Contracts;

use App\Models\LmsAktivitasBelajar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsAktivitasBelajarRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsAktivitasBelajar;

    public function getByModulAjarId(string $modulAjarId): Collection;

    public function create(array $data): LmsAktivitasBelajar;

    public function update(string $id, array $data): ?LmsAktivitasBelajar;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function getStats(): array;
}
