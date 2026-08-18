<?php

namespace App\Repositories\Eloquent;

use App\Models\TujuanPembelajaran;
use App\Repositories\Contracts\TujuanPembelajaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TujuanPembelajaranRepository implements TujuanPembelajaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = TujuanPembelajaran::with(['capaianPembelajaran.subject', 'capaianPembelajaran.kurikulum', 'creator']);

        if (! empty($filters['dengan_sampah'])) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $s = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(kode_tp) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(nama_tp) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', [$s]);
            });
        }

        if (! empty($filters['cp_id'])) {
            $query->where('cp_id', $filters['cp_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $statusBool = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($statusBool !== null) {
                $query->where('status', $statusBool);
            } elseif ($filters['status'] === 'active' || $filters['status'] === 'aktif') {
                $query->where('status', true);
            } elseif ($filters['status'] === 'inactive' || $filters['status'] === 'nonaktif') {
                $query->where('status', false);
            }
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?TujuanPembelajaran
    {
        $query = TujuanPembelajaran::with(['capaianPembelajaran.subject', 'capaianPembelajaran.kurikulum', 'creator']);
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function getByCpId(string $cpId): Collection
    {
        return TujuanPembelajaran::with('capaianPembelajaran')
            ->where('cp_id', $cpId)
            ->where('status', true)
            ->orderBy('urutan', 'asc')
            ->get();
    }

    public function create(array $data): TujuanPembelajaran
    {
        return TujuanPembelajaran::create($data);
    }

    public function update(string $id, array $data): ?TujuanPembelajaran
    {
        $tp = $this->findById($id);
        if (! $tp) {
            return null;
        }

        $tp->update($data);

        return $tp->fresh(['capaianPembelajaran', 'creator']);
    }

    public function delete(string $id): bool
    {
        $tp = $this->findById($id);
        if (! $tp) {
            return false;
        }

        return (bool) $tp->delete();
    }

    public function restore(string $id): bool
    {
        $tp = $this->findById($id, true);
        if (! $tp) {
            return false;
        }

        return (bool) $tp->restore();
    }
}
