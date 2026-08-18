<?php

namespace App\Repositories\Eloquent;

use App\Models\Subject;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class SubjectRepository
 * Implementasi repositori Eloquent untuk Master Mata Pelajaran (Subject).
 */
class SubjectRepository implements SubjectRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = Subject::with(['unitPendidikan', 'kurikulum', 'guruPengampu', 'creator', 'updater'])
            ->filter($filters);

        $allowedSorts = ['kode_mapel', 'nama_mapel', 'code', 'name', 'kelompok_mapel', 'kategori', 'jenjang', 'kkm', 'status', 'created_at'];
        if (! in_array($orderBy, $allowedSorts)) {
            $orderBy = 'created_at';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage);
    }

    public function findById(string $id): ?Subject
    {
        return Subject::withTrashed()
            ->with(['unitPendidikan', 'kurikulum', 'guruPengampu', 'creator', 'updater', 'deleter'])
            ->where('id', $id)
            ->first();
    }

    public function create(array $data): Subject
    {
        $teacherIds = $data['teacher_ids'] ?? null;
        $kelasIds = $data['kelas_ids'] ?? null;
        $rombelIds = $data['rombel_ids'] ?? null;
        unset($data['teacher_ids'], $data['kelas_ids'], $data['rombel_ids']);

        $subject = null;
        $code = $data['code'] ?? $data['kode_mapel'] ?? null;
        if ($code) {
            $existing = Subject::withTrashed()->where('code', $code)->first();
            if ($existing?->trashed()) {
                $existing->restore();
                $existing->update($data);
                $subject = $existing;
            }
        }

        $subject ??= Subject::create($data);

        if ($teacherIds && is_array($teacherIds)) {
            $subject->teachers()->sync($teacherIds);
        }
        if ($kelasIds && is_array($kelasIds)) {
            $subject->classes()->sync($kelasIds);
        }
        if ($rombelIds && is_array($rombelIds)) {
            $subject->rombel()->sync($rombelIds);
        }

        return $subject->fresh(['unitPendidikan', 'kurikulum', 'guruPengampu', 'teachers', 'classes', 'rombel']);
    }

    public function update(string $id, array $data): ?Subject
    {
        $subject = $this->findById($id);
        if (! $subject) {
            return null;
        }

        $teacherIds = $data['teacher_ids'] ?? null;
        $kelasIds = $data['kelas_ids'] ?? null;
        $rombelIds = $data['rombel_ids'] ?? null;
        unset($data['teacher_ids'], $data['kelas_ids'], $data['rombel_ids']);

        $subject->update($data);

        if ($teacherIds !== null && is_array($teacherIds)) {
            $subject->teachers()->sync($teacherIds);
        }
        if ($kelasIds !== null && is_array($kelasIds)) {
            $subject->classes()->sync($kelasIds);
        }
        if ($rombelIds !== null && is_array($rombelIds)) {
            $subject->rombel()->sync($rombelIds);
        }

        return $subject->fresh(['unitPendidikan', 'kurikulum', 'guruPengampu', 'teachers', 'classes', 'rombel', 'creator', 'updater']);
    }

    public function delete(string $id): bool
    {
        $subject = $this->findById($id);
        if (! $subject) {
            return false;
        }

        return (bool) $subject->delete();
    }

    public function restore(string $id): bool
    {
        $subject = Subject::onlyTrashed()->where('id', $id)->first();
        if (! $subject) {
            return false;
        }

        return (bool) $subject->restore();
    }

    public function getStats(): array
    {
        $total = Subject::count();
        $aktif = Subject::where('status', true)->count();
        $tidakAktif = Subject::where('status', false)->count();
        $terhapus = Subject::onlyTrashed()->count();

        $perKelompok = Subject::selectRaw('kelompok_mapel, count(*) as count')
            ->groupBy('kelompok_mapel')
            ->pluck('count', 'kelompok_mapel')
            ->toArray();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'tidak_aktif' => $tidakAktif,
            'terhapus' => $terhapus,
            'per_kelompok' => $perKelompok,
        ];
    }

    public function getDropdownOptions(array $filters = []): Collection
    {
        return Subject::where('status', true)
            ->when($filters['unit_pendidikan_id'] ?? null, fn ($query, $unitId) => $query->where('unit_pendidikan_id', $unitId))
            ->when($filters['kurikulum_id'] ?? null, fn ($query, $kurikulumId) => $query->where('kurikulum_id', $kurikulumId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $query->where(function ($subQuery) use ($search, $like) {
                    $subQuery->where('kode_mapel', $like, "%{$search}%")
                        ->orWhere('nama_mapel', $like, "%{$search}%")
                        ->orWhere('code', $like, "%{$search}%")
                        ->orWhere('name', $like, "%{$search}%");
                });
            })
            ->with(['kurikulum:id,kode_kurikulum,nama_kurikulum,jenis_kurikulum'])
            ->orderBy('nama_mapel', 'asc')
            ->get(['id', 'unit_pendidikan_id', 'kurikulum_id', 'kode_mapel', 'nama_mapel', 'code', 'name', 'kelompok_mapel', 'kategori', 'guru_pengampu_id']);
    }

    public function bulkStatusUpdate(array $ids, bool $status): int
    {
        return Subject::whereIn('id', $ids)->update(['status' => $status, 'updated_by' => auth()->id()]);
    }

    public function bulkDelete(array $ids): int
    {
        $subjects = Subject::whereIn('id', $ids)->get();
        $count = 0;
        foreach ($subjects as $s) {
            if ($s->delete()) {
                $count++;
            }
        }

        return $count;
    }

    public function getAllFilteredForExport(array $filters = []): Collection
    {
        return Subject::with(['unitPendidikan', 'kurikulum', 'guruPengampu'])
            ->filter($filters)
            ->orderBy('kode_mapel', 'asc')
            ->get();
    }
}
