<?php

namespace App\Repositories\Contracts;

use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LmsDiskusiRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?LmsDiskusi;

    public function getByModulAjarId(string $modulAjarId): Collection;

    public function create(array $data): LmsDiskusi;

    public function update(string $id, array $data): ?LmsDiskusi;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function togglePin(string $id): ?LmsDiskusi;

    public function toggleClose(string $id): ?LmsDiskusi;

    public function addComment(string $diskusiId, array $data): LmsDiskusiKomentar;

    public function deleteComment(string $komentarId): bool;

    public function getStats(): array;
}
