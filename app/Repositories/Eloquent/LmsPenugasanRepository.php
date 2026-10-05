<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsPenugasanRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LmsPenugasanRepository implements LmsPenugasanRepositoryInterface
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
                if (! empty($teacherIds)) {
                    $q->whereIn('guru_id', $teacherIds)
                      ->orWhere('created_by', $user->id);
                } else {
                    $q->where('created_by', $user->id);
                }
                if (! empty($accessibleRombelIds)) {
                    $q->orWhereIn('kelas_id', $accessibleRombelIds);
                }
                if (! empty($unitIds)) {
                    $q->orWhereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $unitIds));
                }
            });
        }

        // Staff / TU / Kepsek: Strictly scoped to their unit's classes/rombels
        return $query->where(function ($q) use ($unitIds, $accessibleRombelIds) {
            if (! empty($accessibleRombelIds)) {
                $q->whereIn('kelas_id', $accessibleRombelIds);
            }
            if (! empty($unitIds)) {
                $q->orWhereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $unitIds));
            }
        });
    }

    protected function applyUnitAndCustomFilters($query, array $filters = [])
    {
        $unitIds = [];
        if (! empty($filters['unit_pendidikan_id'])) {
            $unitIds = is_array($filters['unit_pendidikan_id']) ? $filters['unit_pendidikan_id'] : [$filters['unit_pendidikan_id']];
        } elseif (! empty($filters['unit_ids']) && is_array($filters['unit_ids'])) {
            $unitIds = $filters['unit_ids'];
        }

        if (! empty($unitIds)) {
            $query->where(function ($q) use ($unitIds, $filters) {
                $q->whereHas('kelas', fn ($qk) => $qk->whereIn('unit_pendidikan_id', $unitIds))
                  ->orWhereHas('modulAjar', fn ($qm) => $qm->whereIn('unit_pendidikan_id', $unitIds))
                  ->orWhereHas('subject', fn ($qs) => $qs->whereIn('unit_pendidikan_id', $unitIds));

                if (! empty($filters['kelas_ids']) && is_array($filters['kelas_ids'])) {
                    $q->orWhereIn('kelas_id', $filters['kelas_ids']);
                }
            });
        } elseif (! empty($filters['kelas_ids']) && is_array($filters['kelas_ids'])) {
            $query->whereIn('kelas_id', $filters['kelas_ids']);
        }

        if (! empty($filters['modul_ajar_id'])) {
            $query->where('modul_ajar_id', $filters['modul_ajar_id']);
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->where('guru_id', $filters['guru_id']);
        }

        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        if (! empty($filters['tipe'])) {
            $query->where('tipe_tugas', $filters['tipe']);
        }

        return $query;
    }

    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsPenugasan::query()
            ->with([
                'materi',
                'modulAjar',
                'guru',
                'kelas',
                'subject',
                'semester',
                'tahunAjaran',
                'creator',
                'pengumpulan.siswa',
            ]);

        $this->applyTeacherScope($query);
        $this->applyUnitAndCustomFilters($query, $filters);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul_tugas', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%")
                    ->orWhere('instruksi', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'dipublikasikan' || $filters['status'] === 'published') {
                $query->where('is_published', true);
            } elseif ($filters['status'] === 'draft') {
                $query->where('is_published', false);
            }
        }

        if (isset($filters['is_published']) && $filters['is_published'] !== '') {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        $allowedSorts = ['created_at', 'judul_tugas', 'deadline', 'tanggal_mulai', 'nilai_maksimal'];
        $sortField = in_array($orderBy, $allowedSorts, true) ? $orderBy : 'created_at';
        $direction = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function findById(string $id): ?LmsPenugasan
    {
        return LmsPenugasan::with([
            'materi',
            'modulAjar',
            'guru',
            'kelas',
            'subject',
            'semester',
            'tahunAjaran',
            'creator',
            'pengumpulan.siswa',
            'pengumpulan.penilai',
        ])->find($id);
    }

    public function getByModulAjarId(string $modulAjarId): Collection
    {
        return LmsPenugasan::with(['materi', 'guru', 'kelas', 'subject', 'pengumpulan'])
            ->where('modul_ajar_id', $modulAjarId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): LmsPenugasan
    {
        return LmsPenugasan::create($data);
    }

    public function update(string $id, array $data): ?LmsPenugasan
    {
        $penugasan = LmsPenugasan::find($id);
        if (! $penugasan) {
            return null;
        }

        $penugasan->update($data);

        return $penugasan->fresh([
            'modulAjar',
            'guru',
            'kelas',
            'subject',
            'semester',
            'tahunAjaran',
            'creator',
            'pengumpulan.siswa',
        ]);
    }

    public function delete(string $id): bool
    {
        $penugasan = LmsPenugasan::find($id);
        if (! $penugasan) {
            return false;
        }

        return (bool) $penugasan->delete();
    }

    public function restore(string $id): bool
    {
        $penugasan = LmsPenugasan::withTrashed()->find($id);
        if (! $penugasan) {
            return false;
        }

        return (bool) $penugasan->restore();
    }

    public function togglePublish(string $id): ?LmsPenugasan
    {
        $penugasan = LmsPenugasan::find($id);
        if (! $penugasan) {
            return null;
        }

        $penugasan->is_published = ! $penugasan->is_published;
        $penugasan->save();

        return $penugasan->fresh(['modulAjar', 'guru', 'kelas', 'subject']);
    }

    public function submitOrGrade(string $penugasanId, array $data): LmsPengumpulanTugas
    {
        $penugasan = LmsPenugasan::findOrFail($penugasanId);

        $siswaId = $data['siswa_id'] ?? null;
        if (! $siswaId) {
            throw new \InvalidArgumentException('Siswa ID required.');
        }

        $pengumpulan = LmsPengumpulanTugas::firstOrNew([
            'penugasan_id' => $penugasanId,
            'siswa_id' => $siswaId,
        ]);

        if (isset($data['jawaban_teks'])) {
            $pengumpulan->jawaban_teks = $data['jawaban_teks'];
        }
        if (isset($data['file_path'])) {
            $pengumpulan->file_path = $data['file_path'];
        }
        if (isset($data['url_link'])) {
            $pengumpulan->url_link = $data['url_link'];
        }
        if (isset($data['waktu_kumpul'])) {
            $pengumpulan->waktu_kumpul = $data['waktu_kumpul'];
        } elseif (! $pengumpulan->waktu_kumpul && isset($data['jawaban_teks'])) {
            $pengumpulan->waktu_kumpul = now();
        }

        if (isset($data['nilai_guru'])) {
            $pengumpulan->nilai_guru = $data['nilai_guru'];
            $pengumpulan->waktu_dinilai = now();
            $employee = \App\Models\Employee::where('user_id', Auth::id())->first();
            $pengumpulan->dinilai_oleh = $employee?->id;
            $pengumpulan->status = 'dinilai';
        }

        if (isset($data['catatan_guru'])) {
            $pengumpulan->catatan_guru = $data['catatan_guru'];
        }

        if (isset($data['status'])) {
            $pengumpulan->status = $data['status'];
        } elseif (! $pengumpulan->status) {
            $pengumpulan->status = 'dikumpulkan';
        }

        $pengumpulan->save();

        return $pengumpulan->fresh(['siswa', 'penilai']);
    }

    public function getStats(array $filters = []): array
    {
        $baseQuery = LmsPenugasan::query();
        $this->applyTeacherScope($baseQuery);
        $this->applyUnitAndCustomFilters($baseQuery, $filters);

        $total = (clone $baseQuery)->count();
        $published = (clone $baseQuery)->where('is_published', true)->count();
        $draft = (clone $baseQuery)->where('is_published', false)->count();

        $subQuery = LmsPengumpulanTugas::whereHas('penugasan', function ($q) use ($filters) {
            $this->applyTeacherScope($q);
            $this->applyUnitAndCustomFilters($q, $filters);
        });

        $totalSubmissions = (clone $subQuery)->count();
        $totalGraded = (clone $subQuery)->whereNotNull('nilai_guru')->count();

        return [
            'total' => $total,
            'published' => $published,
            'draft' => $draft,
            'total_pengumpulan' => $totalSubmissions,
            'total_dinilai' => $totalGraded,
        ];
    }
}
