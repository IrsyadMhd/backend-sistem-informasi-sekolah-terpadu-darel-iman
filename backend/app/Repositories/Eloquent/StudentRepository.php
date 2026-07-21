<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository implements StudentRepositoryInterface
{
    public function paginate(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        return Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->whereRaw("to_tsvector('simple', coalesce(nis,'') || ' ' || coalesce(full_name,'')) @@ plainto_tsquery('simple', ?)", [$search]);
            })
            ->orderBy('full_name')
            ->paginate($perPage);
    }
}
