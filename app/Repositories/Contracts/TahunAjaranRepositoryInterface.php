<?php

namespace App\Repositories\Contracts;

use App\Models\AcademicYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TahunAjaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'start_date', string $orderDir = 'desc'): LengthAwarePaginator;

    public function findById(string|int $id): ?AcademicYear;

    public function create(array $data): AcademicYear;

    public function update(string|int $id, array $data): ?AcademicYear;

    public function delete(string|int $id): bool;

    public function restore(string|int $id): bool;

    public function setAktif(string|int $id): ?AcademicYear;

    public function getStats(): array;

    public function getDropdownOptions(): Collection;
}
