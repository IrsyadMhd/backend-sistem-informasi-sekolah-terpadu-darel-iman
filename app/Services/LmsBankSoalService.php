<?php

namespace App\Services;

use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Repositories\Contracts\LmsBankSoalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LmsBankSoalService
{
    public function __construct(
        protected LmsBankSoalRepositoryInterface $bankSoalRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->bankSoalRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsBankSoal
    {
        return $this->bankSoalRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsBankSoal
    {
        if (empty($data['kode_soal'])) {
            $data['kode_soal'] = 'SOAL-'.strtoupper(Str::random(6));
        }

        // Auto-assign mata_pelajaran_id from Kisi-kisi if not set
        if (empty($data['mata_pelajaran_id']) && ! empty($data['kisi_kisi_id'])) {
            $kisi = LmsKisiKisi::find($data['kisi_kisi_id']);
            if ($kisi) {
                $data['mata_pelajaran_id'] = $kisi->mata_pelajaran_id;
            }
        }

        if (! isset($data['status'])) {
            $data['status'] = true;
        }

        if (! isset($data['poin'])) {
            $data['poin'] = 1.0;
        }

        if (! isset($data['tingkat_kesulitan'])) {
            $data['tingkat_kesulitan'] = 'sedang';
        }

        $soal = $this->bankSoalRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Butir Soal Baru di Bank Soal', [
            'soal_id' => $soal->id,
            'kode_soal' => $soal->kode_soal,
            'kisi_kisi_id' => $soal->kisi_kisi_id,
            'tipe_soal' => $soal->tipe_soal,
            'user_id' => auth()->id(),
        ]);

        return $soal;
    }

    public function ubah(string $id, array $data): ?LmsBankSoal
    {
        $existing = $this->bankSoalRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if (empty($data['mata_pelajaran_id']) && ! empty($data['kisi_kisi_id'])) {
            $kisi = LmsKisiKisi::find($data['kisi_kisi_id']);
            if ($kisi) {
                $data['mata_pelajaran_id'] = $kisi->mata_pelajaran_id;
            }
        }

        $updated = $this->bankSoalRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Butir Soal Bank Soal', [
            'soal_id' => $id,
            'kode_soal' => $existing->kode_soal,
            'tipe_soal' => $updated->tipe_soal ?? $existing->tipe_soal,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $soal = $this->bankSoalRepository->findById($id);
        if (! $soal) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Butir Soal Bank Soal (Soft Delete)', [
            'soal_id' => $id,
            'kode_soal' => $soal->kode_soal,
            'user_id' => auth()->id(),
        ]);

        return $this->bankSoalRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Butir Soal Bank Soal', [
            'soal_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->bankSoalRepository->restore($id);
    }

    public function duplikasi(string $id): ?LmsBankSoal
    {
        $duplicated = $this->bankSoalRepository->duplicate($id);

        if ($duplicated) {
            Log::info('[AUDIT LOG] Menduplikasi Butir Soal Bank Soal', [
                'soal_id_asal' => $id,
                'soal_id_baru' => $duplicated->id,
                'user_id' => auth()->id(),
            ]);
        }

        return $duplicated;
    }

    public function statistik(array $filters = []): array
    {
        return $this->bankSoalRepository->getStats($filters);
    }

    public function opsi(): array
    {
        $kisiKisiList = LmsKisiKisi::with(['subject:id,name', 'kelas:id,nama_kelas'])
            ->where('status', true)
            ->orderBy('judul_kisi', 'asc')
            ->get(['id', 'judul_kisi', 'jenis_ujian', 'mata_pelajaran_id', 'kelas_id'])
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'judul_kisi' => $k->judul_kisi,
                    'jenis_ujian' => $k->jenis_ujian,
                    'mata_pelajaran_id' => $k->mata_pelajaran_id,
                    'subject_name' => $k->subject->name ?? '',
                    'kelas_name' => $k->kelas->nama_kelas ?? '',
                ];
            });

        $tipeSoalList = [
            ['value' => 'pg', 'label' => 'Pilihan Ganda'],
            ['value' => 'esai', 'label' => 'Essay / Esai'],
            ['value' => 'benar_salah', 'label' => 'Benar / Salah'],
            ['value' => 'menjodohkan', 'label' => 'Menjodohkan'],
        ];

        $tingkatKesulitanList = [
            ['value' => 'mudah', 'label' => 'Mudah'],
            ['value' => 'sedang', 'label' => 'Sedang'],
            ['value' => 'sulit', 'label' => 'Sulit'],
        ];

        return [
            'kisi_kisi' => $kisiKisiList,
            'tipe_soal' => $tipeSoalList,
            'tingkat_kesulitan' => $tingkatKesulitanList,
        ];
    }
}
