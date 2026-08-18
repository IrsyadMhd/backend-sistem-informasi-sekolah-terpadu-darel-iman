<?php

namespace App\Repositories\Contracts;

use App\Models\LmsPengumpulanTugas;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsPengumpulanTugasRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsPengumpulanTugas;

    public function getByPenugasanId(string $penugasanId): Collection;

    public function getBySiswaId(string $siswaId): Collection;

    public function create(array $data): LmsPengumpulanTugas;

    public function update(string $id, array $data): ?LmsPengumpulanTugas;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function getStats(): array;

    public function getOptions(): array;
}
