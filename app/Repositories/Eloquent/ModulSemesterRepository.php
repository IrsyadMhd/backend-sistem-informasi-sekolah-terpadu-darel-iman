<?php

namespace App\Repositories\Eloquent;

use App\Models\ModulSemester;
use App\Models\ModulSemesterDetail;
use App\Repositories\Contracts\ModulSemesterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModulSemesterRepository implements ModulSemesterRepositoryInterface
{
    public function dapatkanDaftar(array $filters, int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = ModulSemester::with([
            'tahunAjaran',
            'semester',
            'unitPendidikan',
            'kelas',
            'subject',
            'guru',
            'details',
        ]);

        if (! empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama_modul) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(kode_modul) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(kurikulum) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('guru', function ($gq) use ($search) {
                        $gq->whereRaw('LOWER(nama_lengkap) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('subject', function ($sq) use ($search) {
                        $sq->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('kelas', function ($kq) use ($search) {
                        $kq->whereRaw('LOWER(nama_kelas) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if (! empty($filters['tahun_ajaran_id'])) {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['unit_pendidikan_id'])) {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->where('guru_id', $filters['guru_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['dengan_sampah']) && $filters['dengan_sampah'] === 'true') {
            $query->withTrashed();
        }

        $allowedSorts = ['kode_modul', 'nama_modul', 'created_at', 'status', 'alokasi_jam', 'jumlah_pertemuan'];
        $sortField = in_array($orderBy, $allowedSorts) ? $orderBy : 'created_at';
        $sortDirection = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortDirection)->paginate($perPage);
    }

    public function dapatkanSemua(array $filters = []): Collection
    {
        $query = ModulSemester::with(['tahunAjaran', 'semester', 'unitPendidikan', 'kelas', 'subject', 'guru']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('nama_modul', 'asc')->get();
    }

    public function cariBerdasarkanId(string $id): ?ModulSemester
    {
        return ModulSemester::with([
            'tahunAjaran',
            'semester',
            'unitPendidikan',
            'kelas',
            'subject',
            'guru',
            'details',
            'pembuat',
        ])->find($id);
    }

    public function buat(array $data, array $details = []): ModulSemester
    {
        return DB::transaction(function () use ($data, $details) {
            $modul = ModulSemester::create($data);

            if (! empty($details) && is_array($details)) {
                foreach ($details as $idx => $detail) {
                    ModulSemesterDetail::create([
                        'modul_semester_id' => $modul->id,
                        'minggu' => $detail['minggu'] ?? ($idx + 1),
                        'materi' => $detail['materi'] ?? 'Materi '.($idx + 1),
                        'atp' => $detail['atp'] ?? null,
                        'cp' => $detail['cp'] ?? null,
                        'jp' => $detail['jp'] ?? 2,
                        'keterangan' => $detail['keterangan'] ?? null,
                    ]);
                }
            }

            return $modul->fresh([
                'tahunAjaran',
                'semester',
                'unitPendidikan',
                'kelas',
                'subject',
                'guru',
                'details',
            ]);
        });
    }

    public function perbarui(string $id, array $data, array $details = []): ModulSemester
    {
        return DB::transaction(function () use ($id, $data, $details) {
            $modul = ModulSemester::findOrFail($id);
            $modul->update($data);

            if (isset($details) && is_array($details)) {
                // Sync details table
                ModulSemesterDetail::where('modul_semester_id', $modul->id)->delete();
                foreach ($details as $idx => $detail) {
                    ModulSemesterDetail::create([
                        'modul_semester_id' => $modul->id,
                        'minggu' => $detail['minggu'] ?? ($idx + 1),
                        'materi' => $detail['materi'] ?? 'Materi '.($idx + 1),
                        'atp' => $detail['atp'] ?? null,
                        'cp' => $detail['cp'] ?? null,
                        'jp' => $detail['jp'] ?? 2,
                        'keterangan' => $detail['keterangan'] ?? null,
                    ]);
                }
            }

            return $modul->fresh([
                'tahunAjaran',
                'semester',
                'unitPendidikan',
                'kelas',
                'subject',
                'guru',
                'details',
            ]);
        });
    }

    public function hapus(string $id): bool
    {
        $modul = ModulSemester::findOrFail($id);

        return (bool) $modul->delete();
    }

    public function pulihkan(string $id): bool
    {
        $modul = ModulSemester::withTrashed()->findOrFail($id);

        return (bool) $modul->restore();
    }

    public function gantiStatus(string $id, string $status): ModulSemester
    {
        $modul = ModulSemester::findOrFail($id);
        $modul->update(['status' => $status]);

        return $modul->fresh([
            'tahunAjaran',
            'semester',
            'unitPendidikan',
            'kelas',
            'subject',
            'guru',
            'details',
        ]);
    }

    public function duplikasi(string $id): ModulSemester
    {
        return DB::transaction(function () use ($id) {
            $original = ModulSemester::with('details')->findOrFail($id);

            $newData = $original->toArray();
            unset($newData['id'], $newData['created_at'], $newData['updated_at'], $newData['deleted_at'], $newData['details']);

            // Auto-generate new unique code
            $newCode = 'MDS-'.strtoupper(Str::random(6));
            $newData['kode_modul'] = $newCode;
            $newData['nama_modul'] = $original->nama_modul.' (Salinan)';
            $newData['status'] = 'Aktif';

            $newModul = ModulSemester::create($newData);

            foreach ($original->details as $detail) {
                ModulSemesterDetail::create([
                    'modul_semester_id' => $newModul->id,
                    'minggu' => $detail->minggu,
                    'materi' => $detail->materi,
                    'atp' => $detail->atp,
                    'cp' => $detail->cp,
                    'jp' => $detail->jp,
                    'keterangan' => $detail->keterangan,
                ]);
            }

            return $newModul->fresh([
                'tahunAjaran',
                'semester',
                'unitPendidikan',
                'kelas',
                'subject',
                'guru',
                'details',
            ]);
        });
    }

    public function dapatkanStatistik(): array
    {
        $totalModul = ModulSemester::count();
        $totalAktif = ModulSemester::where('status', 'Aktif')->count();
        $totalNonaktif = ModulSemester::where('status', 'Nonaktif')->count();
        $totalArsip = ModulSemester::where('status', 'Arsip')->count();
        $totalJam = (int) ModulSemester::sum('alokasi_jam');
        $totalMateri = ModulSemesterDetail::count();

        return [
            'total_modul' => $totalModul,
            'total_aktif' => $totalAktif,
            'total_nonaktif' => $totalNonaktif,
            'total_arsip' => $totalArsip,
            'total_jam' => $totalJam,
            'total_materi' => $totalMateri,
        ];
    }
}
