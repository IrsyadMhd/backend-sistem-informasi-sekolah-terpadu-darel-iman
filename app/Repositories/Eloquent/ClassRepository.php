<?php

namespace App\Repositories\Eloquent;

use App\Models\SchoolClass;
use App\Repositories\Contracts\ClassRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClassRepository implements ClassRepositoryInterface
{
    public function paginate(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        return SchoolClass::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('level', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('level')
            ->orderBy('name')
            ->paginate($perPage);
    }
}
