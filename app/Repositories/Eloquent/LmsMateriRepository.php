<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsMateriRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsMateriRepository implements LmsMateriRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = LmsMateri::with(['modulAjar', 'subject', 'guru', 'media', 'creator']);

        if (! empty($filters['dengan_sampah'])) {
            $query->withTrashed();
        }

        if (! empty($filters['modul_ajar_id'])) {
            $query->where('modul_ajar_id', $filters['modul_ajar_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->where('guru_id', $filters['guru_id']);
        }

        if (! empty($filters['tipe'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('tipe', $filters['tipe'])
                    ->orWhere('tipe_materi', $filters['tipe']);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', $search)
                    ->orWhere('isi', 'like', $search)
                    ->orWhere('konten', 'like', $search);
            });
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?LmsMateri
    {
        $query = LmsMateri::with(['modulAjar', 'subject', 'guru', 'media', 'creator', 'updater', 'deleter']);
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function getByModulAjarId(string $modulAjarId): Collection
    {
        return LmsMateri::with(['media'])
            ->where('modul_ajar_id', $modulAjarId)
            ->orderBy('urutan', 'asc')
            ->get();
    }

    public function create(array $data): LmsMateri
    {
        return LmsMateri::create($data);
    }

    public function update(string $id, array $data): ?LmsMateri
    {
        $materi = $this->findById($id);
        if (! $materi) {
            return null;
        }
        $materi->update($data);

        return $materi->fresh(['modulAjar', 'subject', 'guru', 'media', 'creator']);
    }

    public function delete(string $id): bool
    {
        $materi = $this->findById($id);
        if (! $materi) {
            return false;
        }

        return (bool) $materi->delete();
    }

    public function restore(string $id): bool
    {
        $materi = LmsMateri::withTrashed()->find($id);
        if (! $materi) {
            return false;
        }

        return (bool) $materi->restore();
    }

    public function getStats(): array
    {
        return [
            'total_materi' => LmsMateri::count(),
            'materi_aktif' => LmsMateri::where('status', 'aktif')->orWhere('is_published', true)->count(),
            'materi_dokumen' => LmsMateri::whereIn('tipe', ['dokumen', 'pdf', 'file'])->orWhereIn('tipe_materi', ['dokumen', 'pdf'])->count(),
            'materi_video' => LmsMateri::whereIn('tipe', ['video'])->orWhereIn('tipe_materi', ['video'])->count(),
            'materi_link' => LmsMateri::whereIn('tipe', ['link', 'url'])->orWhereIn('tipe_materi', ['link'])->count(),
            'total_modul_ajar' => LmsModulAjar::count(),
        ];
    }
}
