<?php

namespace App\Repositories\Eloquent;

use App\Models\MasterKurikulum;
use App\Repositories\Contracts\MasterKurikulumRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MasterKurikulumRepository implements MasterKurikulumRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = MasterKurikulum::with(['unitPendidikan', 'tahunAjaran', 'semester', 'creator', 'updater'])
            ->filter($filters);

        $allowedSorts = ['kode_kurikulum', 'nama_kurikulum', 'jenis_kurikulum', 'jenjang', 'status', 'tanggal_mulai', 'tanggal_selesai', 'created_at'];
        if (! in_array($orderBy, $allowedSorts)) {
            $orderBy = 'created_at';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage);
    }

    public function findById(string $id): ?MasterKurikulum
    {
        return MasterKurikulum::withTrashed()
            ->with(['unitPendidikan', 'tahunAjaran', 'semester', 'creator', 'updater', 'deleter'])
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('kode_kurikulum', $id);
            })
            ->first();
    }

    public function create(array $data): MasterKurikulum
    {
        return MasterKurikulum::create($data);
    }

    public function update(string $id, array $data): ?MasterKurikulum
    {
        $item = $this->findById($id);
        if (! $item) {
            return null;
        }

        $item->update($data);

        return $item->fresh(['unitPendidikan', 'tahunAjaran', 'semester', 'creator', 'updater']);
    }

    public function delete(string $id, ?string $deletedBy = null): bool
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

    public function restore(string $id): bool
    {
        $item = MasterKurikulum::onlyTrashed()
            ->where('id', $id)
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
        $total = MasterKurikulum::count();
        $aktif = MasterKurikulum::where('status', true)->count();
        $tidakAktif = MasterKurikulum::where('status', false)->count();
        $terhapus = MasterKurikulum::onlyTrashed()->count();

        // Breakdown per Jenis Kurikulum
        $perJenis = MasterKurikulum::selectRaw('jenis_kurikulum, count(*) as count')
            ->groupBy('jenis_kurikulum')
            ->pluck('count', 'jenis_kurikulum')
            ->toArray();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'tidak_aktif' => $tidakAktif,
            'terhapus' => $terhapus,
            'per_jenis' => $perJenis,
        ];
    }

    public function getDropdownOptions(?string $unitPendidikanId = null): Collection
    {
        $query = MasterKurikulum::where('status', true);

        if ($unitPendidikanId) {
            $query->where('unit_pendidikan_id', $unitPendidikanId);
        }

        return $query->orderBy('nama_kurikulum', 'asc')
            ->get(['id', 'kode_kurikulum', 'nama_kurikulum', 'jenis_kurikulum', 'jenjang', 'unit_pendidikan_id', 'tahun_ajaran_id']);
    }
}
