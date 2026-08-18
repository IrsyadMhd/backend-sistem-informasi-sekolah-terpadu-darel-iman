<?php

namespace App\Repositories\Eloquent;

use App\Models\AcademicYear;
use App\Repositories\Contracts\TahunAjaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TahunAjaranRepository implements TahunAjaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'start_date', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = AcademicYear::query();

        if (! empty($filters['dengan_sampah']) && filter_var($filters['dengan_sampah'], FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                    ->orWhereRaw("metadata->>'keterangan' ILIKE ?", [$search]);
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $status);
        }

        $allowedColumns = ['name', 'start_date', 'end_date', 'is_active', 'created_at'];
        if (! in_array($orderBy, $allowedColumns)) {
            $orderBy = 'start_date';
        }
        $orderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string|int $id): ?AcademicYear
    {
        return AcademicYear::withTrashed()->find($id);
    }

    public function create(array $data): AcademicYear
    {
        if (! empty($data['is_active'])) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
        }

        return AcademicYear::create($data);
    }

    public function update(string|int $id, array $data): ?AcademicYear
    {
        $item = $this->findById($id);
        if (! $item) {
            return null;
        }

        if (! empty($data['is_active'])) {
            AcademicYear::where('id', '!=', $id)->where('is_active', true)->update(['is_active' => false]);
        }

        $item->update($data);

        return $item->fresh();
    }

    public function delete(string|int $id): bool
    {
        $item = $this->findById($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->delete();
    }

    public function restore(string|int $id): bool
    {
        $item = AcademicYear::onlyTrashed()->find($id);
        if (! $item) {
            return false;
        }

        return (bool) $item->restore();
    }

    public function setAktif(string|int $id): ?AcademicYear
    {
        $item = $this->findById($id);
        if (! $item) {
            return null;
        }

        // Nonaktifkan semua tahun ajaran lain
        AcademicYear::where('id', '!=', $id)->update(['is_active' => false]);

        // Aktifkan item terpilih
        $item->update(['is_active' => true]);

        return $item->fresh();
    }

    public function getStats(): array
    {
        $total = AcademicYear::count();
        $aktif = AcademicYear::where('is_active', true)->count();
        $tidakAktif = AcademicYear::where('is_active', false)->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'tidak_aktif' => $tidakAktif,
        ];
    }

    public function getDropdownOptions(): Collection
    {
        return AcademicYear::where('is_active', true)
            ->orWhere(function ($q) {
                $q->whereNull('deleted_at');
            })
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'is_active', 'start_date', 'end_date']);
    }
}
