<?php

namespace App\Repositories\Contracts;

use App\Models\LmsModulAjar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LmsModulAjarRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsModulAjar;

    public function create(array $data): LmsModulAjar;

    public function update(string $id, array $data): ?LmsModulAjar;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function createRevision(LmsModulAjar $modul, string $catatanRevisi, ?string $userId = null): void;

    public function getStats(): array;
}
