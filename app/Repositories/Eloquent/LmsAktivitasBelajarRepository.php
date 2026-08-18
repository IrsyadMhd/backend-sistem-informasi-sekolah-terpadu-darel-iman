<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsAktivitasBelajar;
use App\Repositories\Contracts\LmsAktivitasBelajarRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsAktivitasBelajarRepository implements LmsAktivitasBelajarRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = LmsAktivitasBelajar::with(['modulAjar:id,judul_modul,kode_modul', 'creator:id,name']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_aktivitas', 'like', "%{$search}%")
                    ->orWhere('instruksi', 'like', "%{$search}%")
                    ->orWhere('jenis_aktivitas', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['modul_ajar_id'])) {
            $query->where('modul_ajar_id', $filters['modul_ajar_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->whereHas('modulAjar', fn ($modulQuery) => $modulQuery->where('guru_id', $filters['guru_id']));
        }

        if (! empty($filters['jenis_aktivitas'])) {
            $query->where('jenis_aktivitas', $filters['jenis_aktivitas']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id): ?LmsAktivitasBelajar
    {
        return LmsAktivitasBelajar::with(['modulAjar', 'creator', 'updater'])->find($id);
    }

    public function getByModulAjarId(string $modulAjarId): Collection
    {
        return LmsAktivitasBelajar::where('modul_ajar_id', $modulAjarId)
            ->orderBy('urutan', 'asc')
            ->get();
    }

    public function create(array $data): LmsAktivitasBelajar
    {
        return LmsAktivitasBelajar::create($data);
    }

    public function update(string $id, array $data): ?LmsAktivitasBelajar
    {
        $aktivitas = LmsAktivitasBelajar::find($id);
        if (! $aktivitas) {
            return null;
        }

        $aktivitas->update($data);

        return $aktivitas->fresh(['modulAjar', 'creator']);
    }

    public function delete(string $id): bool
    {
        $aktivitas = LmsAktivitasBelajar::find($id);
        if (! $aktivitas) {
            return false;
        }

        return (bool) $aktivitas->delete();
    }

    public function restore(string $id): bool
    {
        $aktivitas = LmsAktivitasBelajar::withTrashed()->find($id);
        if (! $aktivitas) {
            return false;
        }

        return (bool) $aktivitas->restore();
    }

    public function getStats(): array
    {
        return [
            'total' => LmsAktivitasBelajar::count(),
            'pendahuluan' => LmsAktivitasBelajar::where('jenis_aktivitas', 'Pendahuluan')->count(),
            'inti' => LmsAktivitasBelajar::where('jenis_aktivitas', 'Inti')->count(),
            'penutup' => LmsAktivitasBelajar::whereIn('jenis_aktivitas', ['Penutup', 'Refleksi'])->count(),
            'aktif' => LmsAktivitasBelajar::where('status', 'aktif')->count(),
            'draft' => LmsAktivitasBelajar::where('status', 'draft')->count(),
        ];
    }
}
