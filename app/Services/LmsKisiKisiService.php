<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsKisiKisi;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Repositories\Contracts\LmsKisiKisiRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class LmsKisiKisiService
{
    public function __construct(
        protected LmsKisiKisiRepositoryInterface $kisiKisiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->kisiKisiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsKisiKisi
    {
        return $this->kisiKisiRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsKisiKisi
    {
        if (! isset($data['status'])) {
            $data['status'] = true;
        }

        if (empty($data['distribusi_bobot'])) {
            $data['distribusi_bobot'] = [
                'pg' => 60,
                'isian' => 20,
                'esai' => 20,
            ];
        }

        $kisi = $this->kisiKisiRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Kisi-kisi Ujian Baru', [
            'kisi_kisi_id' => $kisi->id,
            'judul_kisi' => $kisi->judul_kisi,
            'mata_pelajaran_id' => $kisi->mata_pelajaran_id,
            'cp_id' => $kisi->cp_id,
            'tp_id' => $kisi->tp_id,
            'jenis_ujian' => $kisi->jenis_ujian,
            'user_id' => auth()->id(),
        ]);

        return $kisi;
    }

    public function ubah(string $id, array $data): ?LmsKisiKisi
    {
        $existing = $this->kisiKisiRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->kisiKisiRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Kisi-kisi Ujian', [
            'kisi_kisi_id' => $id,
            'judul_sebelum' => $existing->judul_kisi,
            'judul_sesudah' => $updated->judul_kisi ?? $existing->judul_kisi,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $kisi = $this->kisiKisiRepository->findById($id);
        if (! $kisi) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Kisi-kisi Ujian (Soft Delete)', [
            'kisi_kisi_id' => $id,
            'judul_kisi' => $kisi->judul_kisi,
            'user_id' => auth()->id(),
        ]);

        return $this->kisiKisiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Kisi-kisi Ujian', [
            'kisi_kisi_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->kisiKisiRepository->restore($id);
    }

    public function duplikasi(string $id): ?LmsKisiKisi
    {
        $duplicated = $this->kisiKisiRepository->duplicate($id);

        if ($duplicated) {
            Log::info('[AUDIT LOG] Menduplikasi Kisi-kisi Ujian', [
                'original_id' => $id,
                'new_id' => $duplicated->id,
                'user_id' => auth()->id(),
            ]);
        }

        return $duplicated;
    }

    public function statistik(): array
    {
        return $this->kisiKisiRepository->getStats();
    }

    public function opsi(?string $mataPelajaranId = null, ?string $cpId = null): array
    {
        $subjects = Subject::where('status', true)
            ->orWhereNull('status')
            ->orderBy('nama_mapel')
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $name = $item->nama_mapel ?? $item->name ?? 'Mata Pelajaran';
                $code = $item->kode_mapel ?? $item->code ?? '';

                return [
                    'id' => $item->id,
                    'name' => $name,
                    'nama' => $name,
                    'nama_mapel' => $name,
                    'code' => $code,
                    'kode' => $code,
                    'kode_mapel' => $code,
                    'label' => $code ? "{$code} - {$name}" : $name,
                ];
            })
            ->values();

        $kurikulumList = MasterKurikulum::select('id', 'nama_kurikulum', 'kode_kurikulum')->get();
        $kelasList = Kelas::select('id', 'nama_kelas', 'tingkat')->get();
        $semesters = Semester::select('id', 'name', 'sequence')
            ->get()
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'nama' => $semester->name,
                'nama_semester' => $semester->nama_semester,
                'tipe_semester' => $semester->tipe_semester,
            ])
            ->values();
        $tahunAjaranList = AcademicYear::select('id', 'name', 'is_active')
            ->get()
            ->map(fn (AcademicYear $tahunAjaran) => [
                'id' => $tahunAjaran->id,
                'name' => $tahunAjaran->name,
                'status' => $tahunAjaran->is_active,
                'is_active' => $tahunAjaran->is_active,
            ])
            ->values();
        $gurus = Employee::select('id', 'nama_lengkap', 'niy', 'nik')->limit(100)->get();

        $cpQuery = CapaianPembelajaran::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhereNull('status');
            });

        if (! empty($mataPelajaranId) && $mataPelajaranId !== '' && $mataPelajaranId !== 'null') {
            $cpQuery->where('mata_pelajaran_id', $mataPelajaranId);
        }

        $cps = $cpQuery->get()
            ->map(function ($item) {
                $nama = $item->nama_cp ?? $item->deskripsi ?? 'Capaian Pembelajaran';
                $kode = $item->kode_cp ?? '';

                return [
                    'id' => $item->id,
                    'mata_pelajaran_id' => $item->mata_pelajaran_id,
                    'kode_cp' => $kode,
                    'nama_cp' => $nama,
                    'deskripsi' => $item->deskripsi ?? $nama,
                    'fase' => $item->fase ?? null,
                    'label' => $kode ? "{$kode} - {$nama}" : $nama,
                ];
            })
            ->values();

        $tpQuery = TujuanPembelajaran::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhereNull('status');
            });

        if (! empty($cpId) && $cpId !== '' && $cpId !== 'null') {
            $tpQuery->where('cp_id', $cpId);
        } elseif (! empty($mataPelajaranId) && $mataPelajaranId !== '' && $mataPelajaranId !== 'null') {
            $validCpIds = CapaianPembelajaran::where('mata_pelajaran_id', $mataPelajaranId)->pluck('id');
            $tpQuery->whereIn('cp_id', $validCpIds);
        }

        $tps = $tpQuery->get()
            ->map(function ($item) {
                $nama = $item->nama_tp ?? $item->deskripsi ?? 'Tujuan Pembelajaran';
                $kode = $item->kode_tp ?? '';

                return [
                    'id' => $item->id,
                    'cp_id' => $item->cp_id,
                    'kode_tp' => $kode,
                    'nama_tp' => $nama,
                    'deskripsi' => $item->deskripsi ?? $nama,
                    'label' => $kode ? "{$kode} - {$nama}" : $nama,
                ];
            })
            ->values();

        return [
            'subjects' => $subjects,
            'kurikulum' => $kurikulumList,
            'kelas' => $kelasList,
            'semesters' => $semesters,
            'tahun_ajaran' => $tahunAjaranList,
            'guru' => $gurus,
            'capaian_pembelajaran' => $cps,
            'tujuan_pembelajaran' => $tps,
            'jenis_ujian_options' => [
                ['id' => 'UH', 'nama' => 'Ulangan Harian (UH)'],
                ['id' => 'PTS', 'nama' => 'Penilaian Tengah Semester (PTS)'],
                ['id' => 'UTS', 'nama' => 'Ujian Tengah Semester (UTS)'],
                ['id' => 'PAS', 'nama' => 'Penilaian Akhir Semester (PAS)'],
                ['id' => 'UAS', 'nama' => 'Ujian Akhir Semester (UAS)'],
                ['id' => 'CBT', 'nama' => 'Computer Based Test (CBT)'],
                ['id' => 'Remedial', 'nama' => 'Ujian Remedial'],
            ],
            'level_kognitif_options' => [
                ['id' => 'C1 - Mengingat', 'nama' => 'C1 - Mengingat (Remembering)'],
                ['id' => 'C2 - Memahami', 'nama' => 'C2 - Memahami (Understanding)'],
                ['id' => 'C3 - Mengaplikasikan', 'nama' => 'C3 - Mengaplikasikan (Applying)'],
                ['id' => 'C4 - Menganalisis', 'nama' => 'C4 - Menganalisis (Analyzing)'],
                ['id' => 'C5 - Mengevaluasi', 'nama' => 'C5 - Mengevaluasi (Evaluating)'],
                ['id' => 'C6 - Mencipta', 'nama' => 'C6 - Mencipta (Creating)'],
            ],
        ];
    }
}
