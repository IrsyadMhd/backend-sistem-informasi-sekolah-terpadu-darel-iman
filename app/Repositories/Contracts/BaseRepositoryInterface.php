<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function paginate(string $search = '', int $perPage = 15): LengthAwarePaginator;
}
