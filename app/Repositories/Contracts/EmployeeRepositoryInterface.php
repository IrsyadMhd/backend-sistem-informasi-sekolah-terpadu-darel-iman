<?php

namespace App\Repositories\Contracts;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(string $id): ?Employee;

    public function create(array $data): Employee;

    public function update(string $id, array $data): Employee;

    public function delete(string $id): bool;
}
