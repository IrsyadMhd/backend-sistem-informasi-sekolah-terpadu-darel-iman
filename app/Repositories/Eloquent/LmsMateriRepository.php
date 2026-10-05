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
            $statusVal = strtolower((string) $filters['status']);
            if (in_array($statusVal, ['aktif', 'published', 'publish'], true)) {
                $query->where(function ($q) {
                    $q->where('is_published', true)
                      ->orWhere('status', 'aktif')
                      ->orWhere('status', 'published');
                });
            } elseif ($statusVal === 'draft') {
                $query->where(function ($q) {
                    $q->where('is_published', false)
                      ->orWhere('status', 'draft');
                });
            } elseif ($statusVal === 'nonaktif') {
                $query->where('status', 'nonaktif');
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['unit_pendidikan_id'])) {
            $unitId = $filters['unit_pendidikan_id'];
            $query->where(function ($q) use ($unitId) {
                $q->whereHas('modulAjar', fn ($m) => $m->where('unit_pendidikan_id', $unitId))
                  ->orWhereHas('subject', fn ($s) => $s->where('unit_pendidikan_id', $unitId));
            });
        }

        if (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $unitIds = $filters['unit_ids'];
            $query->where(function ($q) use ($unitIds) {
                $q->whereHas('modulAjar', fn ($m) => $m->whereIn('unit_pendidikan_id', $unitIds))
                  ->orWhereHas('subject', fn ($s) => $s->whereIn('unit_pendidikan_id', $unitIds));
            });
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

    public function getStats(array $filters = []): array
    {
        $baseQuery = LmsMateri::query();

        if (! empty($filters['unit_pendidikan_id'])) {
            $unitId = $filters['unit_pendidikan_id'];
            $baseQuery->where(function ($q) use ($unitId) {
                $q->whereHas('modulAjar', fn ($m) => $m->where('unit_pendidikan_id', $unitId))
                  ->orWhereHas('subject', fn ($s) => $s->where('unit_pendidikan_id', $unitId));
            });
        }

        if (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $unitIds = $filters['unit_ids'];
            $baseQuery->where(function ($q) use ($unitIds) {
                $q->whereHas('modulAjar', fn ($m) => $m->whereIn('unit_pendidikan_id', $unitIds))
                  ->orWhereHas('subject', fn ($s) => $s->whereIn('unit_pendidikan_id', $unitIds));
            });
        }

        if (! empty($filters['guru_id'])) {
            $baseQuery->where('guru_id', $filters['guru_id']);
        }

        $modulQuery = LmsModulAjar::query();
        if (! empty($filters['unit_pendidikan_id'])) {
            $modulQuery->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        } elseif (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $modulQuery->whereIn('unit_pendidikan_id', $filters['unit_ids']);
        }

        return [
            'total_materi' => (clone $baseQuery)->count(),
            'materi_aktif' => (clone $baseQuery)->where(function ($q) {
                $q->where('is_published', true)
                  ->orWhere('status', 'aktif')
                  ->orWhere('status', 'published');
            })->count(),
            'materi_dokumen' => (clone $baseQuery)->where(function ($q) {
                $q->whereIn('tipe', ['dokumen', 'pdf', 'file'])
                  ->orWhereIn('tipe_materi', ['dokumen', 'pdf']);
            })->count(),
            'materi_video' => (clone $baseQuery)->where(function ($q) {
                $q->whereIn('tipe', ['video'])
                  ->orWhereIn('tipe_materi', ['video']);
            })->count(),
            'materi_link' => (clone $baseQuery)->where(function ($q) {
                $q->whereIn('tipe', ['link', 'url'])
                  ->orWhereIn('tipe_materi', ['link']);
            })->count(),
            'total_modul_ajar' => $modulQuery->count(),
        ];
    }
}
