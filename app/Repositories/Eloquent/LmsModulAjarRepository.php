<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsModulAjar;
use App\Models\LmsModulAjarRevision;
use App\Repositories\Contracts\LmsModulAjarRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LmsModulAjarRepository implements LmsModulAjarRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsModulAjar::with([
            'educationUnit',
            'tahunAjaran',
            'semesterModel',
            'kurikulum',
            'subject',
            'guru',
            'kelas',
            'rombel',
            'capaianPembelajaran',
            'tujuanPembelajaran',
            'cps',
            'tps',
            'creator',
        ]);

        if (! empty($filters['dengan_sampah'])) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $s = '%'.strtolower($filters['search']).'%';
            $query->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(judul_modul) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(kode_modul) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(tujuan_pembelajaran) LIKE ?', [$s])
                    ->orWhereRaw('LOWER(fase) LIKE ?', [$s]);
            });
        }

        if (! empty($filters['unit_pendidikan_id'])) {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }

        if (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $query->whereIn('unit_pendidikan_id', $filters['unit_ids']);
        }

        if (! empty($filters['tahun_ajaran_id'])) {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['kurikulum_id'])) {
            $query->where('kurikulum_id', $filters['kurikulum_id']);
        }

        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->where('guru_id', $filters['guru_id']);
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['fase'])) {
            $query->where('fase', $filters['fase']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['kode_modul', 'judul_modul', 'fase', 'status', 'versi', 'created_at'];
        if (! in_array($orderBy, $allowedSorts)) {
            $orderBy = 'created_at';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage);
    }

    public function findById(string $id): ?LmsModulAjar
    {
        return LmsModulAjar::withTrashed()
            ->with([
                'educationUnit',
                'tahunAjaran',
                'semesterModel',
                'kurikulum',
                'subject',
                'guru',
                'kelas',
                'rombel',
                'capaianPembelajaran',
                'tujuanPembelajaran',
                'cps',
                'tps',
                'revisions.creator',
                'materi',
                'penugasan',
                'kisiKisi',
                'creator',
            ])
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): LmsModulAjar
    {
        $cpIds = $data['cp_ids'] ?? [];
        $tpIds = $data['tp_ids'] ?? [];
        unset($data['cp_ids'], $data['tp_ids']);

        $modul = LmsModulAjar::create($data);

        if (! empty($cpIds)) {
            $modul->cps()->sync($cpIds);
        }
        if (! empty($tpIds)) {
            $modul->tps()->sync($tpIds);
        }

        $this->createRevision($modul, 'Inisiasi pembuatan Modul Ajar baru (v'.($modul->versi ?? '1.0').')');

        return $this->findById($modul->id);
    }

    public function update(string $id, array $data): ?LmsModulAjar
    {
        $modul = LmsModulAjar::find($id);
        if (! $modul) {
            return null;
        }

        $cpIds = $data['cp_ids'] ?? null;
        $tpIds = $data['tp_ids'] ?? null;
        $catatanRevisi = $data['catatan_revisi'] ?? 'Pembaruan data Modul Ajar';

        unset($data['cp_ids'], $data['tp_ids'], $data['catatan_revisi']);

        $modul->update($data);

        if ($cpIds !== null) {
            $modul->cps()->sync($cpIds);
        }
        if ($tpIds !== null) {
            $modul->tps()->sync($tpIds);
        }

        $this->createRevision($modul, $catatanRevisi);

        return $this->findById($modul->id);
    }

    public function delete(string $id): bool
    {
        $modul = LmsModulAjar::find($id);
        if ($modul) {
            return (bool) $modul->delete();
        }

        return false;
    }

    public function restore(string $id): bool
    {
        $modul = LmsModulAjar::withTrashed()->find($id);
        if ($modul) {
            return (bool) $modul->restore();
        }

        return false;
    }

    public function createRevision(LmsModulAjar $modul, string $catatanRevisi, ?string $userId = null): void
    {
        LmsModulAjarRevision::create([
            'modul_ajar_id' => $modul->id,
            'versi' => $modul->versi ?? '1.0',
            'judul_modul' => $modul->judul_modul,
            'catatan_revisi' => $catatanRevisi,
            'snapshot_data' => $modul->attributesToArray(),
            'created_by' => $userId ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    public function getStats(array $filters = []): array
    {
        $baseQuery = function () use ($filters) {
            $query = LmsModulAjar::query();

            if (! empty($filters['guru_id'])) {
                $query->where('guru_id', $filters['guru_id']);
            }

            if (! empty($filters['unit_pendidikan_id'])) {
                $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
            }

            if (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
                $query->whereIn('unit_pendidikan_id', $filters['unit_ids']);
            }

            if (! empty($filters['tahun_ajaran_id'])) {
                $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
            }

            if (! empty($filters['semester_id'])) {
                $query->where('semester_id', $filters['semester_id']);
            }

            if (! empty($filters['mata_pelajaran_id'])) {
                $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
            }

            if (! empty($filters['kelas_id'])) {
                $query->where('kelas_id', $filters['kelas_id']);
            }

            if (! empty($filters['fase'])) {
                $query->where('fase', $filters['fase']);
            }

            return $query;
        };

        $totalModul = $baseQuery()->count();
        $totalDraft = $baseQuery()->whereIn('status', ['Draft', 'draft'])->count();
        $totalReview = $baseQuery()->whereIn('status', ['Review', 'review'])->count();
        $totalPublished = $baseQuery()->whereIn('status', ['Publish', 'published'])->count();
        $totalArchived = $baseQuery()->whereIn('status', ['Arsip', 'archived'])->count();
        $totalTpTercover = $baseQuery()->whereNotNull('tp_id')->distinct('tp_id')->count('tp_id');

        return [
            'total_modul' => $totalModul,
            'total_draft' => $totalDraft,
            'total_review' => $totalReview,
            'total_published' => $totalPublished,
            'total_archived' => $totalArchived,
            'total_tp_tercover' => $totalTpTercover,
        ];
    }
}
