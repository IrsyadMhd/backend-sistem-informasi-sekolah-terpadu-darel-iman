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
            ->with(['class'])
            ->when($search !== '', function ($query) use ($search) {
                $term = "%{$search}%";
                $query->where(function ($q) use ($term) {
                    $q->where('nis', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhere('metadata->nisn', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage);
    }
}
