<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\Semester;
use App\Repositories\Contracts\LmsUjianRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class LmsUjianService
{
    public function __construct(
        protected LmsUjianRepositoryInterface $ujianRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->ujianRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsUjian
    {
        return $this->ujianRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data): LmsUjian
    {
        if (! isset($data['status'])) {
            $data['status'] = 'draft';
        }

        if (! isset($data['durasi_menit'])) {
            $data['durasi_menit'] = 60;
        }

        if (! isset($data['nilai_kkm'])) {
            $data['nilai_kkm'] = 70.0;
        }

        if (! isset($data['max_attempt'])) {
            $data['max_attempt'] = 1;
        }

        // Auto-fill teacher ID if logged in as employee/teacher
        if (empty($data['guru_id']) && auth()->check()) {
            $employee = Employee::where('user_id', auth()->id())->first();
            if ($employee) {
                $data['guru_id'] = $employee->id;
            } else {
                $data['guru_id'] = Employee::first()?->id;
            }
        }

        $ujian = $this->ujianRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Sesi CBT / Ujian Online Baru', [
            'ujian_id' => $ujian->id,
            'judul_ujian' => $ujian->judul_ujian,
            'kisi_kisi_id' => $ujian->kisi_kisi_id,
            'kelas_id' => $ujian->kelas_id,
            'user_id' => auth()->id(),
        ]);

        return $ujian;
    }

    public function ubah(string $id, array $data): ?LmsUjian
    {
        $existing = $this->ujianRepository->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->ujianRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Sesi CBT Ujian', [
            'ujian_id' => $id,
            'judul_sebelum' => $existing->judul_ujian,
            'judul_sesudah' => $updated->judul_ujian ?? $existing->judul_ujian,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $ujian = $this->ujianRepository->findById($id);
        if (! $ujian) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus Sesi CBT Ujian (Soft Delete)', [
            'ujian_id' => $id,
            'judul_ujian' => $ujian->judul_ujian,
            'user_id' => auth()->id(),
        ]);

        return $this->ujianRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Sesi CBT Ujian', [
            'ujian_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->ujianRepository->restore($id);
    }

    public function duplikasi(string $id): ?LmsUjian
    {
        $duplicated = $this->ujianRepository->duplicate($id);

        if ($duplicated) {
            Log::info('[AUDIT LOG] Menduplikasi Sesi CBT Ujian', [
                'ujian_id_asal' => $id,
                'ujian_id_baru' => $duplicated->id,
                'user_id' => auth()->id(),
            ]);
        }

        return $duplicated;
    }

    public function ubahStatusPublish(string $id, string $status): ?LmsUjian
    {
        $allowed = ['draft', 'published', 'berlangsung', 'selesai', 'dibatalkan'];
        if (! in_array($status, $allowed)) {
            return null;
        }

        $ujian = $this->ujianRepository->update($id, ['status' => $status]);

        if ($ujian) {
            Log::info('[AUDIT LOG] Mengubah Status Publish CBT Ujian', [
                'ujian_id' => $id,
                'status_baru' => $status,
                'user_id' => auth()->id(),
            ]);
        }

        return $ujian;
    }

    // CBT Engine Methods
    public function mulaiSesi(string $ujianId, string $siswaId): array
    {
        $ujian = $this->ujianRepository->findById($ujianId);
        if (! $ujian) {
            throw new \InvalidArgumentException('Ujian tidak ditemukan.');
        }

        $sesi = $this->ujianRepository->startSesiUjian($ujianId, $siswaId);

        // Fetch questions from kisi-kisi
        $soalList = LmsBankSoal::where('kisi_kisi_id', $ujian->kisi_kisi_id)
            ->where('status', true)
            ->get();

        if ($ujian->acak_soal) {
            $soalList = $soalList->shuffle();
        }

        $soalFormatted = $soalList->map(function ($soal) use ($ujian) {
            $options = [];
            if ($soal->tipe_soal === 'pg') {
                $options = [
                    ['key' => 'A', 'text' => $soal->opsi_a],
                    ['key' => 'B', 'text' => $soal->opsi_b],
                    ['key' => 'C', 'text' => $soal->opsi_c],
                    ['key' => 'D', 'text' => $soal->opsi_d],
                    ['key' => 'E', 'text' => $soal->opsi_e],
                ];
                if ($ujian->acak_jawaban) {
                    shuffle($options);
                }
            }

            $matchingItems = null;
            if ($soal->tipe_soal === 'menjodohkan' && $soal->kunci_jawaban) {
                $pairs = json_decode($soal->kunci_jawaban, true);
                if (is_array($pairs)) {
                    $matchingItems = [
                        'kiri' => collect($pairs)->pluck('kiri')->filter()->values()->all(),
                        'kanan' => collect($pairs)->pluck('kanan')->filter()->shuffle()->values()->all(),
                    ];
                }
            }

            return [
                'id' => $soal->id,
                'kode_soal' => $soal->kode_soal,
                'pertanyaan' => $soal->pertanyaan,
                'tipe_soal' => $soal->tipe_soal,
                'poin' => (float) $soal->poin,
                'opsi' => $options,
                // Jangan pernah mengirim pasangan kunci yang benar ke browser siswa.
                'pasangan_menjodohkan' => $matchingItems,
            ];
        });

        // Compute time remaining in seconds
        $durasiDetik = $ujian->durasi_menit * 60;
        $sisaWaktuDetik = $durasiDetik;
        if ($sesi->waktu_mulai) {
            $elapsed = now()->diffInSeconds($sesi->waktu_mulai);
            $sisaWaktuDetik = max(0, $durasiDetik - $elapsed);
        }

        return [
            'sesi_id' => $sesi->id,
            'ujian' => [
                'id' => $ujian->id,
                'judul_ujian' => $ujian->judul_ujian,
                'instruksi' => $ujian->instruksi,
                'durasi_menit' => $ujian->durasi_menit,
                'sisa_waktu_detik' => $sisaWaktuDetik,
                'nilai_kkm' => (float) $ujian->nilai_kkm,
                'tampilkan_nilai_langsung' => (bool) $ujian->tampilkan_nilai_langsung,
            ],
            'soal' => $soalFormatted,
            // Jangan pernah membocorkan is_correct/poin_didapat ke siswa —
            // hanya kembalikan jawaban mentah (soal_id, pilihan/esai).
            'jawaban_tersimpan' => $sesi->jawaban->map(fn ($jawab) => [
                'soal_id' => $jawab->soal_id,
                'jawaban_dipilih' => $jawab->jawaban_dipilih,
                'jawaban_esai' => $jawab->jawaban_esai,
            ])->values(),
        ];
    }

    public function simpanJawaban(string $sesiId, array $jawaban): bool
    {
        return $this->ujianRepository->saveJawabanSesi($sesiId, $jawaban);
    }

    public function selesaikanSesi(string $sesiId): ?LmsUjianSesi
    {
        $sesi = $this->ujianRepository->finalizeSesiUjian($sesiId);

        if ($sesi) {
            Log::info('[AUDIT LOG] Siswa Menyelesaikan Sesi CBT Ujian', [
                'sesi_id' => $sesiId,
                'siswa_id' => $sesi->siswa_id,
                'nilai_final' => $sesi->nilai_final,
                'user_id' => auth()->id(),
            ]);
        }

        return $sesi;
    }

    public function statistik(array $filters = []): array
    {
        return $this->ujianRepository->getStats($filters);
    }

    public function hasilUjian(string $ujianId): array
    {
        return $this->ujianRepository->getHasilUjian($ujianId);
    }

    public function nilaiEssay(string $jawabanId, float $poin, ?string $catatan = null): bool
    {
        return $this->ujianRepository->gradeEssayAnswer($jawabanId, $poin, $catatan);
    }

    public function opsi(): array
    {
        $kisiKisi = LmsKisiKisi::with(['subject:id,name', 'kelas:id,nama_kelas'])
            ->where('status', true)
            ->orderBy('judul_kisi', 'asc')
            ->get(['id', 'judul_kisi', 'jenis_ujian', 'mata_pelajaran_id', 'kelas_id'])
            ->map(function ($k) {
                return [
                    'id' => $k->id,
                    'judul_kisi' => $k->judul_kisi,
                    'jenis_ujian' => $k->jenis_ujian,
                    'subject_name' => $k->subject->name ?? '',
                    'kelas_id' => $k->kelas_id,
                    'kelas_name' => $k->kelas->nama_kelas ?? '',
                ];
            });

        $kelas = Kelas::orderBy('nama_kelas', 'asc')->get(['id', 'nama_kelas']);
        $semesters = Semester::get(['id', 'name'])
            ->map(fn (Semester $semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'nama_semester' => $semester->nama_semester,
            ])
            ->values();
        $gurus = Employee::orderBy('nama_lengkap', 'asc')->get(['id', 'nama_lengkap']);

        return [
            'kisi_kisi' => $kisiKisi,
            'kelas' => $kelas,
            'semesters' => $semesters,
            'guru' => $gurus,
            'status_options' => [
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'published', 'label' => 'Dipublikasikan'],
                ['value' => 'berlangsung', 'label' => 'Sedang Berlangsung'],
                ['value' => 'selesai', 'label' => 'Selesai'],
                ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
            ],
        ];
    }
}
