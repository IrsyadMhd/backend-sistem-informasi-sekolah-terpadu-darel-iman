<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Models\LmsKisiKisi;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsKisiKisiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LmsKisiKisiRepository implements LmsKisiKisiRepositoryInterface
{
    protected function applyTeacherScope($query)
    {
        $user = Auth::user();
        if (! $user) {
            return $query;
        }

        $accessScope = app(\App\Services\AccessScopeService::class);
        if ($accessScope->hasGlobalScope($user)) {
            return $query;
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $teacher = Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null);

        $teacherIds = array_values(array_filter(array_unique([
            $employee?->id,
            $teacher?->id,
        ])));
        $unitIds = $accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $accessScope->accessibleRombels($user)->pluck('id')->all();

        $isTeacherOnly = ! $user->hasAnyRole([
            'Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'Ketua Yayasan',
            'Kepala Sekolah', 'kepala_sekolah', 'Waka Kurikulum', 'waka_kurikulum',
            'Waka Kesiswaan', 'waka_kesiswaan', 'Tata Usaha', 'tata_usaha', 'tu',
        ]) && $user->hasAnyRole(['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran']);

        if ($isTeacherOnly) {
            return $query->where(function ($q) use ($teacherIds, $user, $unitIds, $accessibleRombelIds) {
                $q->where('created_by', $user->id)
                  ->orWhereNull('created_by');

                if (! empty($teacherIds)) {
                    $q->orWhereIn('guru_id', $teacherIds);
                }
                if (! empty($accessibleRombelIds)) {
                    $q->orWhereIn('kelas_id', $accessibleRombelIds);
                }
                if (! empty($unitIds)) {
                    $q->orWhereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $unitIds))
                      ->orWhereHas('subject', fn ($qs) => $qs->whereIn('unit_pendidikan_id', $unitIds));
                }
            });
        }

        return $query->where(function ($q) use ($unitIds, $accessibleRombelIds) {
            if (! empty($accessibleRombelIds)) {
                $q->whereIn('kelas_id', $accessibleRombelIds);
            }
            if (! empty($unitIds)) {
                $q->orWhereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $unitIds))
                  ->orWhereHas('subject', fn ($qs) => $qs->whereIn('unit_pendidikan_id', $unitIds));
            }
        });
    }

    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsKisiKisi::with([
            'subject',
            'cp',
            'tp',
            'kurikulum',
            'kelas',
            'semester',
            'tahunAjaran',
            'guru',
        ])->withCount(['bankSoal', 'ujian']);

        $this->applyTeacherScope($query);

        if (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $filters['unit_ids']))
                  ->orWhereHas('subject', fn ($qs) => $qs->whereIn('unit_pendidikan_id', $filters['unit_ids']));
                if (! empty($filters['kelas_ids'])) {
                    $q->orWhereIn('kelas_id', $filters['kelas_ids']);
                }
            });
        } elseif (! empty($filters['kelas_ids'])) {
            $query->whereIn('kelas_id', $filters['kelas_ids']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul_kisi', 'like', "%{$search}%")
                    ->orWhere('kompetensi_dasar', 'like', "%{$search}%")
                    ->orWhere('level_kognitif', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        if (! empty($filters['jenis_ujian'])) {
            $query->where('jenis_ujian', $filters['jenis_ujian']);
        }

        if (! empty($filters['kurikulum_id'])) {
            $query->where('kurikulum_id', $filters['kurikulum_id']);
        }

        if (! empty($filters['cp_id'])) {
            $query->where('cp_id', $filters['cp_id']);
        }

        if (! empty($filters['tp_id'])) {
            $query->where('tp_id', $filters['tp_id']);
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        if (! empty($filters['with_trashed']) && $filters['with_trashed'] === 'true') {
            $query->withTrashed();
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?LmsKisiKisi
    {
        $query = LmsKisiKisi::with([
            'subject',
            'cp',
            'tp',
            'kurikulum',
            'kelas',
            'semester',
            'tahunAjaran',
            'guru',
            'bankSoal',
            'ujian',
        ]);

        $this->applyTeacherScope($query);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): LmsKisiKisi
    {
        return LmsKisiKisi::create($data);
    }

    public function update(string $id, array $data): ?LmsKisiKisi
    {
        $kisi = $this->findById($id);
        if (! $kisi) {
            return null;
        }

        $kisi->update($data);

        return $kisi->fresh([
            'subject',
            'cp',
            'tp',
            'kurikulum',
            'kelas',
            'semester',
            'tahunAjaran',
            'guru',
        ]);
    }

    public function delete(string $id): bool
    {
        $kisi = $this->findById($id);
        if (! $kisi) {
            return false;
        }

        return (bool) $kisi->delete();
    }

    public function restore(string $id): bool
    {
        $query = LmsKisiKisi::withTrashed();
        $this->applyTeacherScope($query);
        $kisi = $query->find($id);
        if (! $kisi || ! $kisi->trashed()) {
            return false;
        }

        return (bool) $kisi->restore();
    }

    public function duplicate(string $id): ?LmsKisiKisi
    {
        $original = $this->findById($id);
        if (! $original) {
            return null;
        }

        $user = Auth::user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $new = $original->replicate();
        $new->id = (string) Str::uuid();
        $new->judul_kisi = $original->judul_kisi.' (Salinan)';
        if ($employee) {
            $new->guru_id = $employee->id;
        }
        if ($user) {
            $new->created_by = $user->id;
        }
        $new->created_at = now();
        $new->updated_at = now();
        $new->save();

        return $new->fresh([
            'subject',
            'cp',
            'tp',
            'kurikulum',
            'kelas',
            'semester',
            'tahunAjaran',
            'guru',
        ]);
    }

    public function getStats(): array
    {
        $query = LmsKisiKisi::query();
        $this->applyTeacherScope($query);

        $total = (clone $query)->count();
        $aktif = (clone $query)->where('status', true)->count();
        $nonaktif = (clone $query)->where('status', false)->count();
        $totalSoalTarget = (clone $query)->sum('jumlah_soal');
        $uhCount = (clone $query)->where('jenis_ujian', 'UH')->count();
        $ptsCount = (clone $query)->whereIn('jenis_ujian', ['PTS', 'UTS'])->count();
        $pasCount = (clone $query)->whereIn('jenis_ujian', ['PAS', 'UAS'])->count();

        return [
            'total' => $total,
            'aktif' => $aktif,
            'nonaktif' => $nonaktif,
            'total_soal_target' => $totalSoalTarget,
            'uh' => $uhCount,
            'pts' => $ptsCount,
            'pas' => $pasCount,
        ];
    }
}
