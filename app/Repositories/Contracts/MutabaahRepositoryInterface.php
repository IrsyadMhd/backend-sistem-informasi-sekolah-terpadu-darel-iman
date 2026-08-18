<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface MutabaahRepositoryInterface
{
    public function paginate(string $resource, array $filters): LengthAwarePaginator;

    public function find(string $resource, string $id, bool $withTrashed = false): Model;

    public function create(string $resource, array $data): Model;

    public function update(string $resource, string $id, array $data): Model;

    public function delete(string $resource, string $id): void;

    public function restore(string $resource, string $id): Model;

    public function forceDelete(string $resource, string $id): void;
}
