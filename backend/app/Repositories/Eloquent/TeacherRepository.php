<?php

namespace App\Repositories\Eloquent;

use App\Models\Teacher;
use App\Repositories\Contracts\TeacherRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeacherRepository implements TeacherRepositoryInterface
{
    public function paginate(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        return Teacher::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('employee_number', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage);
    }
}
