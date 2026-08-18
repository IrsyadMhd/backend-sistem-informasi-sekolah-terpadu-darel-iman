<?php

namespace App\Repositories\Eloquent;

use App\Models\Kelas;
use App\Repositories\Contracts\KelasRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class KelasRepository
 * Implementasi Repository Pattern Eloquent untuk Model Kelas.
 */
class KelasRepository implements KelasRepositoryInterface
{
    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = Kelas::with(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas', 'pembuat'])
            ->withCount('siswa');

        // Search Query (Pencarian universal)
        if (! empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama_kelas) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(kode_kelas) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(ruangan) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('waliKelas', function ($wq) use ($search) {
                        $wq->whereRaw('LOWER(nama_lengkap) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        // Filter Unit Pendidikan
        if (! empty($filters['unit_pendidikan_id'])) {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }

        if (array_key_exists('allowed_unit_ids', $filters)) {
            $query->whereIn('unit_pendidikan_id', $filters['allowed_unit_ids']);
        }

        if (array_key_exists('allowed_kelas_ids', $filters)) {
            $query->whereIn('id', $filters['allowed_kelas_ids']);
        }

        // Filter Tahun Ajaran
        if (! empty($filters['tahun_ajaran_id'])) {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }

        // Filter Semester
        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        // Filter Jenjang
        if (! empty($filters['jenjang'])) {
            $query->where('jenjang', $filters['jenjang']);
        }

        // Filter Tingkat
        if (! empty($filters['tingkat'])) {
            $query->where('tingkat', $filters['tingkat']);
        }

        // Filter Status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Include Trashed (Soft Delete) jika diminta
        if (! empty($filters['dengan_sampah']) && $filters['dengan_sampah'] === 'true') {
            $query->withTrashed();
        }

        // Sorting
        $allowedSorts = ['nama_kelas', 'kode_kelas', 'tingkat', 'jenjang', 'status', 'created_at', 'kapasitas'];
        $sortField = in_array($orderBy, $allowedSorts) ? $orderBy : 'created_at';
        $sortDirection = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortDirection)->paginate($perPage);
    }

    public function dapatkanSemua(array $filters = []): Collection
    {
        $query = Kelas::with(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas'])
            ->withCount('siswa');

        if (! empty($filters['unit_pendidikan_id'])) {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('nama_kelas', 'asc')->get();
    }

    public function cariBerdasarkanId(string $id): ?Kelas
    {
        return Kelas::with(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas', 'siswa'])
            ->withCount('siswa')
            ->find($id);
    }

    public function buat(array $data): Kelas
    {
        return Kelas::create($data);
    }

    public function perbarui(string $id, array $data): Kelas
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->update($data);

        return $kelas->fresh(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas']);
    }

    public function hapus(string $id): bool
    {
        $kelas = Kelas::findOrFail($id);

        return (bool) $kelas->delete();
    }

    public function pulihkan(string $id): bool
    {
        $kelas = Kelas::withTrashed()->findOrFail($id);

        return (bool) $kelas->restore();
    }

    public function dapatkanStatistik(?array $allowedKelasIds = null): array
    {
        $query = Kelas::query();
        if ($allowedKelasIds !== null) {
            $query->whereIn('id', $allowedKelasIds);
        }

        $totalKelas = (clone $query)->count();
        $totalAktif = (clone $query)->where('status', 'Aktif')->count();
        $waliTerisi = (clone $query)->whereNotNull('wali_kelas_id')->count();
        $totalKapasitas = (clone $query)->sum('kapasitas');

        return [
            'total_kelas' => $totalKelas,
            'total_aktif' => $totalAktif,
            'wali_terisi' => $waliTerisi,
            'total_kapasitas' => (int) $totalKapasitas,
        ];
    }
}
