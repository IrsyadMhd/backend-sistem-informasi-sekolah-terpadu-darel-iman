<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsModulAjar;
use App\Models\LmsReferensi;
use App\Repositories\Contracts\LmsReferensiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsReferensiRepository implements LmsReferensiRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsReferensi::with(['modulAjar', 'modulAjar.subject', 'creator']);

        if (! empty($filters['modul_ajar_id'])) {
            if (in_array(strtolower($filters['modul_ajar_id']), ['umum', 'tanpa_modul', 'null'])) {
                $query->whereNull('modul_ajar_id');
            } else {
                $query->where('modul_ajar_id', $filters['modul_ajar_id']);
            }
        }

        if (! empty($filters['guru_id'])) {
            $query->whereHas('modulAjar', fn ($modulQuery) => $modulQuery->where('guru_id', $filters['guru_id']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtolower($filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', $search)
                    ->orWhere('penulis', 'like', $search)
                    ->orWhere('penerbit', 'like', $search)
                    ->orWhere('tahun', 'like', $search)
                    ->orWhereHas('modulAjar', function ($mq) use ($search) {
                        $mq->where('judul_modul', 'like', $search)
                            ->orWhere('kode_modul', 'like', $search);
                    });
            });
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id): ?LmsReferensi
    {
        return LmsReferensi::with(['modulAjar', 'modulAjar.subject', 'creator'])->find($id);
    }

    public function getByModulAjarId(string $modulAjarId): Collection
    {
        return LmsReferensi::with(['modulAjar'])
            ->where('modul_ajar_id', $modulAjarId)
            ->latest()
            ->get();
    }

    public function create(array $data): LmsReferensi
    {
        return LmsReferensi::create($data);
    }

    public function update(string $id, array $data): ?LmsReferensi
    {
        $referensi = $this->findById($id);
        if (! $referensi) {
            return null;
        }
        $referensi->update($data);

        return $referensi->fresh(['modulAjar', 'creator']);
    }

    public function delete(string $id): bool
    {
        $referensi = $this->findById($id);
        if (! $referensi) {
            return false;
        }

        return (bool) $referensi->delete();
    }

    public function restore(string $id): bool
    {
        $referensi = LmsReferensi::withTrashed()->find($id);
        if (! $referensi) {
            return false;
        }

        return (bool) $referensi->restore();
    }

    public function getStats(): array
    {
        return [
            'total_referensi' => LmsReferensi::count(),
            'total_aktif' => LmsReferensi::where('status', 'aktif')->count(),
            'total_non_aktif' => LmsReferensi::where('status', 'non-aktif')->count(),
            'dengan_file' => LmsReferensi::whereNotNull('file')->where('file', '!=', '')->count(),
            'dengan_url' => LmsReferensi::whereNotNull('url')->where('url', '!=', '')->count(),
            'total_modul_ajar' => LmsModulAjar::count(),
        ];
    }
}
