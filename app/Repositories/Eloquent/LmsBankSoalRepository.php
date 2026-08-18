<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Models\LmsBankSoal;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsBankSoalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LmsBankSoalRepository implements LmsBankSoalRepositoryInterface
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

        return $query->where(function ($q) use ($teacherIds, $user) {
            $q->where('created_by', $user->id);
            if (! empty($teacherIds)) {
                $q->orWhereHas('kisiKisi', fn ($qk) => $qk->whereIn('guru_id', $teacherIds)->orWhere('created_by', $user->id));
            }
        });
    }

    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsBankSoal::with([
            'kisiKisi:id,judul_kisi,jenis_ujian,mata_pelajaran_id,kelas_id',
            'kisiKisi.subject:id,name,code',
            'kisiKisi.kelas:id,nama_kelas',
            'subject:id,name,code',
        ]);

        $this->applyTeacherScope($query);

        if (! empty($filters['with_trashed']) && filter_var($filters['with_trashed'], FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('pertanyaan', 'like', "%{$search}%")
                    ->orWhere('kode_soal', 'like', "%{$search}%")
                    ->orWhere('indikator', 'like', "%{$search}%")
                    ->orWhere('pembahasan', 'like', "%{$search}%")
                    ->orWhereHas('kisiKisi', function ($k) use ($search) {
                        $k->where('judul_kisi', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['kisi_kisi_id'])) {
            $query->where('kisi_kisi_id', $filters['kisi_kisi_id']);
        }

        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        if (! empty($filters['tipe_soal'])) {
            $query->where('tipe_soal', $filters['tipe_soal']);
        }

        if (! empty($filters['tingkat_kesulitan'])) {
            $query->where('tingkat_kesulitan', $filters['tingkat_kesulitan']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        $allowedColumns = ['created_at', 'kode_soal', 'tipe_soal', 'poin', 'tingkat_kesulitan', 'status'];
        if (! in_array($orderBy, $allowedColumns)) {
            $orderBy = 'created_at';
        }

        $orderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?LmsBankSoal
    {
        $query = LmsBankSoal::with([
            'kisiKisi:id,judul_kisi,jenis_ujian,mata_pelajaran_id,kelas_id,guru_id',
            'kisiKisi.subject:id,name,code',
            'kisiKisi.kelas:id,nama_kelas',
            'kisiKisi.guru:id,nama_lengkap',
            'subject:id,name,code',
        ]);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): LmsBankSoal
    {
        return LmsBankSoal::create($data);
    }

    public function update(string $id, array $data): ?LmsBankSoal
    {
        $bankSoal = LmsBankSoal::find($id);
        if (! $bankSoal) {
            return null;
        }

        $bankSoal->update($data);

        return $bankSoal->fresh(['kisiKisi', 'subject']);
    }

    public function delete(string $id): bool
    {
        $bankSoal = LmsBankSoal::find($id);
        if (! $bankSoal) {
            return false;
        }

        return (bool) $bankSoal->delete();
    }

    public function restore(string $id): bool
    {
        $bankSoal = LmsBankSoal::withTrashed()->find($id);
        if (! $bankSoal || ! $bankSoal->trashed()) {
            return false;
        }

        return (bool) $bankSoal->restore();
    }

    public function duplicate(string $id): ?LmsBankSoal
    {
        $existing = LmsBankSoal::find($id);
        if (! $existing) {
            return null;
        }

        $replicated = $existing->replicate();
        $replicated->id = (string) Str::uuid();
        $replicated->kode_soal = $existing->kode_soal ? $existing->kode_soal.'-COPY' : 'SOAL-'.strtoupper(Str::random(6));
        $replicated->pertanyaan = '[Salinan] '.$existing->pertanyaan;
        $replicated->created_at = now();
        $replicated->updated_at = now();
        $replicated->save();

        return $replicated->fresh(['kisiKisi', 'subject']);
    }

    public function getStats(array $filters = []): array
    {
        $query = LmsBankSoal::query();
        $this->applyTeacherScope($query);

        if (! empty($filters['kisi_kisi_id'])) {
            $query->where('kisi_kisi_id', $filters['kisi_kisi_id']);
        }
        if (! empty($filters['mata_pelajaran_id'])) {
            $query->where('mata_pelajaran_id', $filters['mata_pelajaran_id']);
        }

        $totalSoal = (clone $query)->count();
        $totalPg = (clone $query)->where('tipe_soal', 'pg')->count();
        $totalEsai = (clone $query)->where('tipe_soal', 'esai')->count();
        $totalBenarSalah = (clone $query)->where('tipe_soal', 'benar_salah')->count();
        $totalMenjodohkan = (clone $query)->where('tipe_soal', 'menjodohkan')->count();
        $totalAktif = (clone $query)->where('status', true)->count();
        $totalNonAktif = (clone $query)->where('status', false)->count();

        return [
            'total_soal' => $totalSoal,
            'total_pg' => $totalPg,
            'total_esai' => $totalEsai,
            'total_benar_salah' => $totalBenarSalah,
            'total_menjodohkan' => $totalMenjodohkan,
            'total_aktif' => $totalAktif,
            'total_non_aktif' => $totalNonAktif,
        ];
    }

    public function getByKisiKisiId(string $kisiKisiId): Collection
    {
        return LmsBankSoal::with(['subject'])
            ->where('kisi_kisi_id', $kisiKisiId)
            ->where('status', true)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
