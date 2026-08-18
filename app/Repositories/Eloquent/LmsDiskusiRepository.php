<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Repositories\Contracts\LmsDiskusiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsDiskusiRepository implements LmsDiskusiRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsDiskusi::with(['modulAjar', 'creator', 'komentar.user', 'komentar.replies.user']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['modul_ajar_id'])) {
            $query->where('modul_ajar_id', $filters['modul_ajar_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->whereHas('modulAjar', fn ($modulQuery) => $modulQuery->where('guru_id', $filters['guru_id']));
        }

        if (! empty($filters['kelas_ids'])) {
            $query->whereHas('modulAjar', fn ($modulQuery) => $modulQuery->whereIn('kelas_id', $filters['kelas_ids']));
        }

        if (! empty($filters['published_only'])) {
            $query->where('status', 'aktif')->where('is_closed', false);
        }

        if (! empty($filters['kategori'])) {
            $query->where('kategori', $filters['kategori']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Pinned discussions come first
        $query->orderBy('is_pinned', 'desc');

        $allowedColumns = ['created_at', 'updated_at', 'judul', 'kategori', 'status'];
        if (in_array($orderBy, $allowedColumns)) {
            $query->orderBy($orderBy, strtolower($orderDir) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(string $id): ?LmsDiskusi
    {
        return LmsDiskusi::with(['modulAjar', 'creator', 'komentar.user', 'komentar.replies.user'])
            ->find($id);
    }

    public function getByModulAjarId(string $modulAjarId): Collection
    {
        return LmsDiskusi::with(['creator', 'komentar.user', 'komentar.replies.user'])
            ->where('modul_ajar_id', $modulAjarId)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): LmsDiskusi
    {
        return LmsDiskusi::create($data);
    }

    public function update(string $id, array $data): ?LmsDiskusi
    {
        $diskusi = LmsDiskusi::find($id);
        if (! $diskusi) {
            return null;
        }

        $diskusi->update($data);

        return $diskusi->fresh(['modulAjar', 'creator', 'komentar.user', 'komentar.replies.user']);
    }

    public function delete(string $id): bool
    {
        $diskusi = LmsDiskusi::find($id);
        if (! $diskusi) {
            return false;
        }

        return (bool) $diskusi->delete();
    }

    public function restore(string $id): bool
    {
        $diskusi = LmsDiskusi::withTrashed()->find($id);
        if (! $diskusi) {
            return false;
        }

        return (bool) $diskusi->restore();
    }

    public function togglePin(string $id): ?LmsDiskusi
    {
        $diskusi = LmsDiskusi::find($id);
        if (! $diskusi) {
            return null;
        }

        $diskusi->update(['is_pinned' => ! $diskusi->is_pinned]);

        return $diskusi->fresh(['modulAjar', 'creator']);
    }

    public function toggleClose(string $id): ?LmsDiskusi
    {
        $diskusi = LmsDiskusi::find($id);
        if (! $diskusi) {
            return null;
        }

        $diskusi->update(['is_closed' => ! $diskusi->is_closed]);

        return $diskusi->fresh(['modulAjar', 'creator']);
    }

    public function addComment(string $diskusiId, array $data): LmsDiskusiKomentar
    {
        $data['diskusi_id'] = $diskusiId;

        return LmsDiskusiKomentar::create($data);
    }

    public function deleteComment(string $komentarId): bool
    {
        $komentar = LmsDiskusiKomentar::find($komentarId);
        if (! $komentar) {
            return false;
        }

        return (bool) $komentar->delete();
    }

    public function getStats(): array
    {
        $total = LmsDiskusi::count();
        $aktif = LmsDiskusi::where('status', 'aktif')->where('is_closed', false)->count();
        $ditutup = LmsDiskusi::where('is_closed', true)->orWhere('status', 'nonaktif')->count();
        $pinned = LmsDiskusi::where('is_pinned', true)->count();
        $totalKomentar = LmsDiskusiKomentar::count();
        $komentarGuru = LmsDiskusiKomentar::where('peran_pengirim', 'Guru')->count();
        $komentarSiswa = LmsDiskusiKomentar::where('peran_pengirim', 'Siswa')->count();

        return [
            'total_diskusi' => $total,
            'diskusi_aktif' => $aktif,
            'diskusi_ditutup' => $ditutup,
            'diskusi_pinned' => $pinned,
            'total_komentar' => $totalKomentar,
            'komentar_guru' => $komentarGuru,
            'komentar_siswa' => $komentarSiswa,
        ];
    }
}
