<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\Student;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsPengumpulanTugasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class LmsPengumpulanTugasRepository implements LmsPengumpulanTugasRepositoryInterface
{
    protected function applyTeacherScope($query)
    {
        $user = Auth::user();
        if (! $user) {
            return $query;
        }

        $adminRoles = ['superadmin', 'yayasan', 'ketuayayasan', 'pengurusyayasan', 'sekretarisyayasan', 'bendaharayayasan', 'kepalasekolah', 'tatausaha', 'tu', 'divisipendidikan'];
        $userRoles = $user->getRoleNames()->map(fn ($r) => strtolower((string) preg_replace('/[\s_-]+/', '', $r)));

        if ($userRoles->intersect($adminRoles)->isNotEmpty()) {
            return $query;
        }

        $employee = Employee::where('user_id', $user->id)->first();
        $teacher = Teacher::where('user_id', $user->id)->first() ?? ($employee ? Teacher::where('employee_id', $employee->id)->first() : null);

        $teacherIds = array_values(array_filter(array_unique([
            $employee?->id,
            $teacher?->id,
        ])));

        return $query->whereHas('penugasan', function ($qp) use ($teacherIds, $user) {
            if (! empty($teacherIds)) {
                $qp->whereIn('guru_id', $teacherIds)
                   ->orWhere('created_by', $user->id);
            } else {
                $qp->where('created_by', $user->id);
            }
        });
    }

    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsPengumpulanTugas::query()
            ->with([
                'penugasan.subject',
                'penugasan.kelas',
                'siswa',
                'penilai',
            ]);

        $this->applyTeacherScope($query);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('jawaban_teks', 'like', "%{$search}%")
                    ->orWhere('file_path', 'like', "%{$search}%")
                    ->orWhere('url_link', 'like', "%{$search}%")
                    ->orWhere('catatan_guru', 'like', "%{$search}%")
                    ->orWhereHas('siswa', function ($sq) use ($search) {
                        $sq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('nisn', 'like', "%{$search}%")
                            ->orWhere('nis', 'like', "%{$search}%");
                    })
                    ->orWhereHas('penugasan', function ($pq) use ($search) {
                        $pq->where('judul_tugas', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['penugasan_id'])) {
            $query->where('penugasan_id', $filters['penugasan_id']);
        }

        if (! empty($filters['siswa_id'])) {
            $query->where('siswa_id', $filters['siswa_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_graded'])) {
            if ($filters['is_graded'] === 'true' || $filters['is_graded'] === true || $filters['is_graded'] === '1') {
                $query->whereNotNull('nilai_guru');
            } elseif ($filters['is_graded'] === 'false' || $filters['is_graded'] === false || $filters['is_graded'] === '0') {
                $query->whereNull('nilai_guru');
            }
        }

        $allowedSorts = ['created_at', 'waktu_kumpul', 'waktu_dinilai', 'nilai_guru', 'status'];
        $sortField = in_array($orderBy, $allowedSorts, true) ? $orderBy : 'created_at';
        $direction = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $direction)->paginate($perPage);
    }

    public function findById(string $id): ?LmsPengumpulanTugas
    {
        return LmsPengumpulanTugas::with([
            'penugasan.subject',
            'penugasan.kelas',
            'penugasan.guru',
            'siswa',
            'penilai',
        ])->find($id);
    }

    public function getByPenugasanId(string $penugasanId): Collection
    {
        return LmsPengumpulanTugas::with(['siswa', 'penilai'])
            ->where('penugasan_id', $penugasanId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getBySiswaId(string $siswaId): Collection
    {
        return LmsPengumpulanTugas::with(['penugasan.subject', 'penilai'])
            ->where('siswa_id', $siswaId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): LmsPengumpulanTugas
    {
        // Map alias fields if provided
        $mapped = $this->mapAliasFields($data);

        if (empty($mapped['waktu_kumpul']) && (! empty($mapped['jawaban_teks']) || ! empty($mapped['file_path']) || ! empty($mapped['url_link']))) {
            $mapped['waktu_kumpul'] = now();
        }

        if (empty($mapped['status'])) {
            $mapped['status'] = ! empty($mapped['nilai_guru']) ? 'dinilai' : 'dikumpulkan';
        }

        return LmsPengumpulanTugas::create($mapped);
    }

    public function update(string $id, array $data): ?LmsPengumpulanTugas
    {
        $pengumpulan = LmsPengumpulanTugas::find($id);
        if (! $pengumpulan) {
            return null;
        }

        $mapped = $this->mapAliasFields($data);

        // If grading fields provided, record penilai and waktu_dinilai
        if (isset($mapped['nilai_guru'])) {
            $mapped['waktu_dinilai'] = now();
            if (Auth::check()) {
                $employee = \App\Models\Employee::where('user_id', Auth::id())->first();
                $mapped['dinilai_oleh'] = $employee?->id;
            }
            if (! isset($mapped['status'])) {
                $mapped['status'] = 'dinilai';
            }
        }

        $pengumpulan->update($mapped);

        return $pengumpulan->fresh(['penugasan.subject', 'penugasan.kelas', 'siswa', 'penilai']);
    }

    public function delete(string $id): bool
    {
        $pengumpulan = LmsPengumpulanTugas::find($id);
        if (! $pengumpulan) {
            return false;
        }

        return (bool) $pengumpulan->delete();
    }

    public function restore(string $id): bool
    {
        $pengumpulan = LmsPengumpulanTugas::withTrashed()->find($id);
        if (! $pengumpulan) {
            return false;
        }

        return (bool) $pengumpulan->restore();
    }

    public function getStats(): array
    {
        $query = LmsPengumpulanTugas::query();
        $this->applyTeacherScope($query);

        $total = (clone $query)->count();
        $dikumpulkan = (clone $query)->where('status', 'dikumpulkan')->count();
        $terlambat = (clone $query)->where('status', 'terlambat')->count();
        $dinilai = (clone $query)->whereNotNull('nilai_guru')->count();
        $belumDinilai = (clone $query)->whereNull('nilai_guru')->count();
        $revisi = (clone $query)->where('status', 'revisi')->count();

        return [
            'total' => $total,
            'dikumpulkan' => $dikumpulkan,
            'terlambat' => $terlambat,
            'dinilai' => $dinilai,
            'belum_dinilai' => $belumDinilai,
            'revisi' => $revisi,
        ];
    }

    public function getOptions(): array
    {
        $penugasanList = LmsPenugasan::query()
            ->with(['subject', 'kelas'])
            ->orderBy('judul_tugas', 'asc')
            ->get()
            ->map(function ($item) {
                $judul = $item->judul_tugas ?? $item->judul ?? 'Tugas';

                return [
                    'id' => $item->id,
                    'label' => $judul,
                    'judul' => $judul,
                    'judul_tugas' => $judul,
                    'subject' => $item->subject ? ($item->subject->nama_mapel ?? $item->subject->name ?? null) : null,
                    'kelas' => $item->kelas ? ($item->kelas->nama_kelas ?? $item->kelas->kode_kelas ?? null) : null,
                ];
            });

        $siswaList = Student::query()
            ->orderBy('full_name', 'asc')
            ->get()
            ->map(function ($siswa) {
                $name = $siswa->full_name ?? $siswa->name ?? $siswa->nama_lengkap ?? 'Siswa';

                return [
                    'id' => $siswa->id,
                    'label' => $name,
                    'name' => $name,
                    'full_name' => $name,
                    'nama_lengkap' => $name,
                    'nama' => $name,
                    'nisn' => $siswa->nisn ?? null,
                    'nis' => $siswa->nis ?? null,
                ];
            });

        return [
            'penugasan' => $penugasanList,
            'siswa' => $siswaList,
            'status' => [
                ['id' => 'belum', 'label' => 'Belum Kumpul'],
                ['id' => 'dikumpulkan', 'label' => 'Dikumpulkan'],
                ['id' => 'terlambat', 'label' => 'Terlambat'],
                ['id' => 'dinilai', 'label' => 'Sudah Dinilai'],
                ['id' => 'revisi', 'label' => 'Perlu Revisi'],
            ],
        ];
    }

    protected function mapAliasFields(array $data): array
    {
        $mapped = $data;

        // Field aliases requested: file, link, catatan, nilai, status
        if (isset($data['file']) && ! isset($data['file_path'])) {
            $mapped['file_path'] = $data['file'];
        }
        if (isset($data['link']) && ! isset($data['url_link'])) {
            $mapped['url_link'] = $data['link'];
        }
        if (isset($data['catatan'])) {
            if (! isset($data['catatan_guru'])) {
                $mapped['catatan_guru'] = $data['catatan'];
            }
            if (! isset($data['jawaban_teks'])) {
                $mapped['jawaban_teks'] = $data['catatan'];
            }
        }
        if (isset($data['nilai']) && ! isset($data['nilai_guru'])) {
            $mapped['nilai_guru'] = $data['nilai'];
        }

        return $mapped;
    }
}
