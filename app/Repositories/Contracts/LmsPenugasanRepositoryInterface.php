<?php

namespace App\Repositories\Contracts;

use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsPenugasanRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsPenugasan;

    public function getByModulAjarId(string $modulAjarId): Collection;

    public function create(array $data): LmsPenugasan;

    public function update(string $id, array $data): ?LmsPenugasan;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function togglePublish(string $id): ?LmsPenugasan;

    public function submitOrGrade(string $penugasanId, array $data): LmsPengumpulanTugas;

    public function getStats(): array;
}
