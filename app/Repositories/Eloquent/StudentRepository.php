<?php

namespace App\Repositories\Eloquent;

use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository implements StudentRepositoryInterface
{
    public function paginate(
        string $search = '',
        int $perPage = 15,
        ?string $unitId = null,
        bool $canAccessAllUnits = false
    ): LengthAwarePaginator
    {
        return Student::query()
            ->with(['kelas:id,nama_kelas,tingkat,unit_pendidikan_id', 'educationUnit:id,name'])
            ->when(! $canAccessAllUnits, function ($query) use ($unitId) {
                $query->when(
                    $unitId,
                    fn ($studentQuery) => $studentQuery->where('unit_id', $unitId),
                    fn ($studentQuery) => $studentQuery->whereRaw('1 = 0')
                );
            })
            ->when($search !== '', function ($query) use ($search) {
                $term = "%{$search}%";
                $query->where(function ($q) use ($term) {
                    $q->where('nis', 'like', $term)
                        ->orWhere('full_name', 'like', $term)
                        ->orWhere('nisn', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage);
    }
}
