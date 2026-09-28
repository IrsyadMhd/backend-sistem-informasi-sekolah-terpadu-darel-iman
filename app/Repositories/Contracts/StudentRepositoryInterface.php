<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginate(
        string $search = '',
        int $perPage = 15,
        ?string $unitId = null,
        bool $canAccessAllUnits = false,
        ?string $kelasId = null,
        ?string $status = null
    ): LengthAwarePaginator;
}
