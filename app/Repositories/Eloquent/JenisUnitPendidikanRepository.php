<?php

namespace App\Repositories\Eloquent;

use App\Models\JenisUnitPendidikan;
use App\Repositories\Contracts\JenisUnitPendidikanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class JenisUnitPendidikanRepository implements JenisUnitPendidikanRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = JenisUnitPendidikan::with(['creator', 'updater'])->filter($filters);

        $allowedSorts = ['kode_jenis', 'nama_jenis', 'singkatan', 'jenjang', 'urutan', 'status', 'created_at'];
        if (! in_array($orderBy, $allowedSorts)) {
            $orderBy = 'urutan';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'desc' ? 'desc' : 'asc')
            ->paginate($perPage);
    }

    public function findById(string|int $id): ?JenisUnitPendidikan
    {
        return JenisUnitPendidikan::withTrashed()
            ->with(['creator', 'updater', 'deleter'])
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', $id);
                } else {
                    $q->where('uuid', $id)->orWhere('kode_jenis', $id);
                }
            })
            ->first();
    }

    public function create(array $data): JenisUnitPendidikan
    {
        return JenisUnitPendidikan::create($data);
    }

    public function update(string|int $id, array $data): ?JenisUnitPendidikan
    {
        $item = $this->findById($id);
        if (! $item) {
            return null;
        }

        $item->update($data);

        return $item->fresh();
    }

    public function delete(string|int $id, int|string|null $deletedBy = null): bool
    {
        $item = $this->findById($id);
        if (! $item) {
            return false;
        }

        if ($deletedBy) {
            $item->deleted_by = $deletedBy;
            $item->save();
        }

        return (bool) $item->delete();
    }

    public function restore(string|int $id): bool
    {
        $item = JenisUnitPendidikan::onlyTrashed()
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', $id);
                } else {
                    $q->where('uuid', $id);
                }
            })
            ->first();

        if (! $item) {
            return false;
        }

        $item->deleted_by = null;
        $item->save();

        return (bool) $item->restore();
    }

    public function getStats(): array
    {
        $total = JenisUnitPendidikan::count();
        $aktif = JenisUnitPendidikan::where('status', true)->count();
        $tidakAktif = JenisUnitPendidikan::where('status', false)->count();
        $terhapus = JenisUnitPendidikan::onlyTrashed()->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'tidak_aktif' => $tidakAktif,
            'terhapus' => $terhapus,
        ];
    }

    public function getDropdownOptions(): Collection
    {
        return JenisUnitPendidikan::where('status', true)
            ->orderBy('urutan', 'asc')
            ->orderBy('nama_jenis', 'asc')
            ->get(['id', 'uuid', 'kode_jenis', 'nama_jenis', 'singkatan', 'jenjang', 'warna_badge', 'icon']);
    }
}
