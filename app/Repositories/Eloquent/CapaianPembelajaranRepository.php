<?php

namespace App\Repositories\Eloquent;

use App\Models\CapaianPembelajaran;
use App\Repositories\Contracts\CapaianPembelajaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class CapaianPembelajaranRepository implements CapaianPembelajaranRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return CapaianPembelajaran::with(['unitPendidikan', 'tahunAjaran', 'kurikulum', 'subject'])
            ->filter($filters)
            ->orderBy($orderBy, $orderDir)
            ->paginate($perPage);
    }

    public function getDropdownOptions(array $filters = []): Collection
    {
        $query = CapaianPembelajaran::query();

        if (! empty($filters['unit_pendidikan_id']) && Schema::hasColumn('lms_capaian_pembelajaran', 'unit_pendidikan_id')) {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }

        if (! empty($filters['tahun_ajaran_id']) && Schema::hasColumn('lms_capaian_pembelajaran', 'tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }

        if (! empty($filters['kurikulum_id'])) {
            $query->where('kurikulum_id', $filters['kurikulum_id']);
        }

        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        // Must be active
        $query->where('status', true);

        return $query->orderBy('kode_cp', 'asc')->get();
    }

    public function findById(string $id, bool $withTrashed = false): ?CapaianPembelajaran
    {
        $query = CapaianPembelajaran::with(['unitPendidikan', 'tahunAjaran', 'kurikulum', 'subject', 'tujuanPembelajaran']);
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): CapaianPembelajaran
    {
        $data = $this->sanitizeDataColumns($data);

        return CapaianPembelajaran::create($data);
    }

    public function update(string $id, array $data): ?CapaianPembelajaran
    {
        $cp = $this->findById($id);
        if (! $cp) {
            return null;
        }

        $data = $this->sanitizeDataColumns($data);
        $cp->update($data);

        return $cp->fresh(['unitPendidikan', 'tahunAjaran', 'kurikulum', 'subject']);
    }

    protected function sanitizeDataColumns(array $data): array
    {
        if (array_key_exists('unit_pendidikan_id', $data) && ! Schema::hasColumn('lms_capaian_pembelajaran', 'unit_pendidikan_id')) {
            unset($data['unit_pendidikan_id']);
        }

        if (array_key_exists('tahun_ajaran_id', $data) && ! Schema::hasColumn('lms_capaian_pembelajaran', 'tahun_ajaran_id')) {
            unset($data['tahun_ajaran_id']);
        }

        return $data;
    }

    public function delete(string $id): bool
    {
        $cp = $this->findById($id);
        if (! $cp) {
            return false;
        }

        return (bool) $cp->delete();
    }

    public function restore(string $id): bool
    {
        $cp = CapaianPembelajaran::withTrashed()->find($id);
        if (! $cp) {
            return false;
        }

        return (bool) $cp->restore();
    }
}
