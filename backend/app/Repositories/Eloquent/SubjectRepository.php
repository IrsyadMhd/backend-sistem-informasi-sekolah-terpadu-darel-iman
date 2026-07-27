<?php

namespace App\Repositories\Eloquent;

use App\Models\Subject;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class SubjectRepository
 * Implementasi repositori Eloquent untuk Master Mata Pelajaran (Subject).
 */
class SubjectRepository implements SubjectRepositoryInterface
{
    /**
     * Dapatkan daftar mata pelajaran terpaginasi dengan filter pencarian dan pengurutan.
     */
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = Subject::query();

        if (!empty($filters['dengan_sampah']) && $filters['dengan_sampah'] === 'true') {
            $query->withTrashed();
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', "%{$search}%")
                  ->orWhere('name', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['code', 'name', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedSorts)) {
            $orderBy = 'created_at';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage);
    }

    /**
     * Cari data mata pelajaran berdasarkan ID UUID (termasuk yang terhapus lunak).
     */
    public function findById(string $id): ?Subject
    {
        return Subject::withTrashed()
            ->where('id', $id)
            ->first();
    }

    /**
     * Buat data mata pelajaran baru.
     */
    public function create(array $data): Subject
    {
        return Subject::create($data);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function update(string $id, array $data): ?Subject
    {
        $subject = $this->findById($id);
        if (!$subject) {
            return null;
        }

        $subject->update($data);

        return $subject->fresh();
    }

    /**
     * Hapus data mata pelajaran (Soft Delete).
     */
    public function delete(string $id): bool
    {
        $subject = $this->findById($id);
        if (!$subject) {
            return false;
        }

        return (bool) $subject->delete();
    }

    /**
     * Pulihkan data mata pelajaran yang telah terhapus.
     */
    public function restore(string $id): bool
    {
        $subject = Subject::onlyTrashed()->where('id', $id)->first();
        if (!$subject) {
            return false;
        }

        return (bool) $subject->restore();
    }

    /**
     * Dapatkan statistik ringkas data mata pelajaran.
     */
    public function getStats(): array
    {
        $total = Subject::count();
        $terhapus = Subject::onlyTrashed()->count();

        return [
            'total' => $total,
            'terhapus' => $terhapus,
        ];
    }

    /**
     * Dapatkan daftar opsi dropdown untuk pilihan mata pelajaran.
     */
    public function getDropdownOptions(): Collection
    {
        return Subject::orderBy('name', 'asc')
            ->get(['id', 'code', 'name', 'description']);
    }
}
