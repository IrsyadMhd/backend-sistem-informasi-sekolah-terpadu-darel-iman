<?php

namespace App\Repositories\Contracts;

use App\Models\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface SubjectRepositoryInterface
 * Kontrak repositori data Master Mata Pelajaran (Subject).
 */
interface SubjectRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string $id): ?Subject;

    public function create(array $data): Subject;

    public function update(string $id, array $data): ?Subject;

    public function delete(string $id): bool;

    public function restore(string $id): bool;

    public function getStats(): array;

    public function getDropdownOptions(): Collection;
}
