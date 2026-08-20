<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Models\LmsBankSoal;
use App\Models\LmsJawabanSiswa;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\Teacher;
use App\Repositories\Contracts\LmsUjianRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LmsUjianRepository implements LmsUjianRepositoryInterface
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
            if (! empty($teacherIds)) {
                $q->whereIn('guru_id', $teacherIds)
                  ->orWhere('created_by', $user->id);
            } else {
                $q->where('created_by', $user->id);
            }
        });
    }

    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        $query = LmsUjian::with([
            'kisiKisi:id,judul_kisi,jenis_ujian,mata_pelajaran_id',
            'kisiKisi.subject:id,name,code',
            'kelas:id,nama_kelas',
            'semester:id,name',
            'guru:id,nama_lengkap',
        ])->withCount('sesi');

        $this->applyTeacherScope($query);

        if (! empty($filters['with_trashed']) && filter_var($filters['with_trashed'], FILTER_VALIDATE_BOOLEAN)) {
            $query->withTrashed();
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('judul_ujian', 'like', "%{$search}%")
                    ->orWhere('instruksi', 'like', "%{$search}%")
                    ->orWhereHas('kisiKisi', function ($k) use ($search) {
                        $k->where('judul_kisi', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['kisi_kisi_id'])) {
            $query->where('kisi_kisi_id', $filters['kisi_kisi_id']);
        }

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedColumns = ['created_at', 'judul_ujian', 'waktu_mulai', 'waktu_selesai', 'durasi_menit', 'nilai_kkm', 'status'];
        if (! in_array($orderBy, $allowedColumns)) {
            $orderBy = 'created_at';
        }

        $orderDir = strtolower($orderDir) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id, bool $withTrashed = false): ?LmsUjian
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $query = LmsUjian::with([
            'kisiKisi',
            'kisiKisi.subject',
            'kisiKisi.bankSoal',
            'kelas',
            'semester',
            'guru',
            'sesi.siswa',
        ]);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function create(array $data): LmsUjian
    {
        return LmsUjian::create($data);
    }

    public function update(string $id, array $data): ?LmsUjian
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $ujian = LmsUjian::find($id);
        if (! $ujian) {
            return null;
        }

        $ujian->update($data);

        return $ujian->fresh(['kisiKisi', 'kelas', 'semester', 'guru']);
    }

    public function delete(string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        $ujian = LmsUjian::find($id);
        if (! $ujian) {
            return false;
        }

        return (bool) $ujian->delete();
    }

    public function restore(string $id): bool
    {
        if (! Str::isUuid($id)) {
            return false;
        }

        $ujian = LmsUjian::withTrashed()->find($id);
        if (! $ujian || ! $ujian->trashed()) {
            return false;
        }

        return (bool) $ujian->restore();
    }

    public function duplicate(string $id): ?LmsUjian
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $existing = LmsUjian::find($id);
        if (! $existing) {
            return null;
        }

        $replicated = $existing->replicate();
        $replicated->id = (string) Str::uuid();
        $replicated->judul_ujian = '[Salinan] '.$existing->judul_ujian;
        $replicated->status = 'draft';
        $replicated->created_at = now();
        $replicated->updated_at = now();
        $replicated->save();

        return $replicated->fresh(['kisiKisi', 'kelas', 'semester', 'guru']);
    }

    public function getStats(array $filters = []): array
    {
        $query = LmsUjian::query();
        $this->applyTeacherScope($query);

        if (! empty($filters['kelas_id'])) {
            $query->where('kelas_id', $filters['kelas_id']);
        }

        $totalUjian = (clone $query)->count();
        $totalDraft = (clone $query)->where('status', 'draft')->count();
        $totalPublished = (clone $query)->where('status', 'published')->count();
        $totalBerlangsung = (clone $query)->where('status', 'berlangsung')->count();
        $totalSelesai = (clone $query)->where('status', 'selesai')->count();

        $totalPeserta = LmsUjianSesi::whereIn('ujian_id', (clone $query)->pluck('id'))->count();
        $rataNilai = LmsUjianSesi::whereIn('ujian_id', (clone $query)->pluck('id'))
            ->whereNotNull('nilai_final')
            ->avg('nilai_final') ?? 0;

        return [
            'total_ujian' => $totalUjian,
            'total_draft' => $totalDraft,
            'total_published' => $totalPublished,
            'total_berlangsung' => $totalBerlangsung,
            'total_selesai' => $totalSelesai,
            'total_peserta' => $totalPeserta,
            'rata_nilai' => round($rataNilai, 2),
        ];
    }

    // CBT Session & Auto Scoring Implementation
    public function findSesiById(string $sesiId): ?LmsUjianSesi
    {
        if (! Str::isUuid($sesiId)) {
            return null;
        }

        return LmsUjianSesi::with(['ujian.kisiKisi.bankSoal', 'siswa', 'jawaban.soal'])->find($sesiId);
    }

    public function getSesiByUjianId(string $ujianId): Collection
    {
        if (! Str::isUuid($ujianId)) {
            return new Collection();
        }

        return LmsUjianSesi::with(['siswa', 'jawaban'])
            ->where('ujian_id', $ujianId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getSesiBySiswa(string $ujianId, string $siswaId): ?LmsUjianSesi
    {
        if (! Str::isUuid($ujianId) || ! Str::isUuid($siswaId)) {
            return null;
        }

        return LmsUjianSesi::with(['jawaban'])
            ->where('ujian_id', $ujianId)
            ->where('siswa_id', $siswaId)
            ->latest()
            ->first();
    }

    public function startSesiUjian(string $ujianId, string $siswaId, ?string $ipAddress = null): LmsUjianSesi
    {
        $existing = $this->getSesiBySiswa($ujianId, $siswaId);
        if ($existing && $existing->status === 'proses') {
            return $existing->load(['ujian.kisiKisi', 'jawaban']);
        }

        try {
            $sesi = LmsUjianSesi::create([
                'ujian_id' => $ujianId,
                'siswa_id' => $siswaId,
                'waktu_mulai' => now(),
                'status' => 'proses',
                'ip_address' => $ipAddress ?? request()->ip(),
            ]);

            return $sesi->fresh(['ujian.kisiKisi', 'siswa']);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique violation pada (ujian_id, siswa_id, status='proses') —
            // sesi paralel sudah dibuat duluan; lanjutkan sesi yang ada.
            $concurrent = $this->getSesiBySiswa($ujianId, $siswaId);
            if ($concurrent && $concurrent->status === 'proses') {
                return $concurrent->load(['ujian.kisiKisi', 'jawaban']);
            }

            throw $e;
        }
    }

    public function saveJawabanSesi(string $sesiId, array $jawabanData): bool
    {
        $sesi = LmsUjianSesi::with('ujian')->find($sesiId);
        if (! $sesi || $sesi->status !== 'proses') {
            return false;
        }

        // Enforcement timer: simpan jawaban ditolak setelah batas waktu habis.
        if ($sesi->ujian && $sesi->waktu_mulai) {
            $durasiDetik = (int) $sesi->ujian->durasi_menit * 60;
            $deadline = $sesi->waktu_mulai->copy()->addSeconds($durasiDetik);
            if (now()->gt($deadline)) {
                return false;
            }
        }

        foreach ($jawabanData as $item) {
            if (empty($item['soal_id'])) {
                continue;
            }

            LmsJawabanSiswa::updateOrCreate(
                [
                    'sesi_id' => $sesiId,
                    'soal_id' => $item['soal_id'],
                ],
                [
                    'jawaban_dipilih' => $item['jawaban_dipilih'] ?? null,
                    'jawaban_esai' => $item['jawaban_esai'] ?? null,
                    'waktu_jawab_detik' => $item['waktu_jawab_detik'] ?? null,
                ]
            );
        }

        return true;
    }

    /**
     * Finalisasi sesi ujian (pengumpulan manual atau auto-timeout).
     *
     * Idempoten: sesi yang sudah berstatus final ('selesai'/'timeout') tidak
     * diproses ulang agar hasil tidak digandakan/ditimpa. Panggilan manual
     * dari siswa/guru tetap memakai status default 'selesai'.
     */
    public function finalizeSesiUjian(string $sesiId, string $status = 'selesai'): ?LmsUjianSesi
    {
        $sesi = LmsUjianSesi::with(['ujian', 'jawaban'])->find($sesiId);
        if (! $sesi) {
            return null;
        }

        if (in_array($sesi->status, ['selesai', 'timeout'], true)) {
            return $sesi;
        }

        if ($status !== 'selesai' && $status !== 'timeout') {
            $status = 'selesai';
        }

        return $this->gradeAndFinalize($sesi, $status);
    }

    /**
     * Auto-timeout seluruh sesi CBT yang masih berstatus 'proses' tetapi batas
     * waktunya sudah lewat (waktu_mulai + durasi_menit*60 < sekarang).
     *
     * Setiap sesi dikunci lewat update atomik (status 'proses' -> 'timeout')
     * agar dua runner scheduler tidak memproses sesi yang sama dua kali.
     * Jawaban objektif dinilai, esai dibiarkan pending review manual, dan
     * kunci jawaban tidak pernah dibocorkan ke output.
     *
     * @return array{expired:int, submitted:int, skipped:int}
     */
    public function autoSubmitExpiredSessions(int $limit = 100): array
    {
        $expiredIds = LmsUjianSesi::query()
            ->with('ujian:id,durasi_menit')
            ->where('status', 'proses')
            ->whereNotNull('waktu_mulai')
            ->get()
            ->filter(function (LmsUjianSesi $sesi) {
                if (! $sesi->ujian || (int) $sesi->ujian->durasi_menit <= 0) {
                    return false;
                }
                $deadline = $sesi->waktu_mulai->copy()->addSeconds((int) $sesi->ujian->durasi_menit * 60);

                return now()->gt($deadline);
            })
            ->take($limit)
            ->pluck('id');

        $submitted = 0;
        $skipped = 0;

        foreach ($expiredIds as $sesiId) {
            // Kunci (claim) atomik: hanya sesi yang masih 'proses' yang diambil.
            $claimed = LmsUjianSesi::query()
                ->whereKey($sesiId)
                ->where('status', 'proses')
                ->update([
                    'status' => 'timeout',
                    'waktu_selesai' => now(),
                ]);

            if ($claimed === 0) {
                $skipped++;
                continue;
            }

            $sesi = LmsUjianSesi::with(['ujian', 'jawaban'])->find($sesiId);
            if (! $sesi || ! $this->gradeAndFinalize($sesi, 'timeout')) {
                $skipped++;
                continue;
            }

            $submitted++;
        }

        return [
            'expired' => $expiredIds->count(),
            'submitted' => $submitted,
            'skipped' => $skipped,
        ];
    }

    private function gradeAndFinalize(LmsUjianSesi $sesi, string $status): ?LmsUjianSesi
    {
        $sesiId = $sesi->id;
        $ujian = $sesi->ujian;
        $soalList = LmsBankSoal::where('kisi_kisi_id', $ujian->kisi_kisi_id)
            ->where('status', true)
            ->get();

        $jumlahBenar = 0;
        $jumlahSalah = 0;
        $jumlahKosong = 0;
        $totalPoinDidapat = 0;
        $maxPossiblePoints = 0;

        foreach ($soalList as $soal) {
            $maxPossiblePoints += $soal->poin;
            $jawabanRecord = $sesi->jawaban->firstWhere('soal_id', $soal->id);

            if (! $jawabanRecord) {
                // Not answered
                $jumlahKosong++;
                LmsJawabanSiswa::create([
                    'sesi_id' => $sesiId,
                    'soal_id' => $soal->id,
                    'is_correct' => false,
                    'poin_didapat' => 0,
                ]);

                continue;
            }

            $isCorrect = false;
            $poinDidapat = 0;

            if ($soal->tipe_soal === 'pg') {
                $chosen = strtoupper(trim($jawabanRecord->jawaban_dipilih ?? ''));
                $key = strtoupper(trim($soal->kunci_jawaban ?? ''));
                if (! empty($chosen) && $chosen === $key) {
                    $isCorrect = true;
                    $poinDidapat = $soal->poin;
                    $jumlahBenar++;
                } elseif (empty($chosen)) {
                    $jumlahKosong++;
                } else {
                    $jumlahSalah++;
                }
            } elseif ($soal->tipe_soal === 'benar_salah') {
                $chosen = strtolower(trim($jawabanRecord->jawaban_dipilih ?? ''));
                $key = strtolower(trim($soal->kunci_jawaban ?? ''));
                if (! empty($chosen) && $chosen === $key) {
                    $isCorrect = true;
                    $poinDidapat = $soal->poin;
                    $jumlahBenar++;
                } elseif (empty($chosen)) {
                    $jumlahKosong++;
                } else {
                    $jumlahSalah++;
                }
            } elseif ($soal->tipe_soal === 'menjodohkan') {
                $chosen = trim($jawabanRecord->jawaban_esai ?? '');
                $key = trim($soal->kunci_jawaban ?? '');

                if (! empty($chosen)) {
                    $arrChosen = json_decode($chosen, true);
                    $arrKey = json_decode($key, true);

                    if (is_array($arrChosen) && is_array($arrKey) && json_encode($arrChosen) === json_encode($arrKey)) {
                        $isCorrect = true;
                        $poinDidapat = $soal->poin;
                        $jumlahBenar++;
                    } else {
                        $jumlahSalah++;
                    }
                } else {
                    $jumlahKosong++;
                }
            } elseif ($soal->tipe_soal === 'isian') {
                $chosen = mb_strtolower(trim($jawabanRecord->jawaban_esai ?? ''));
                $key = mb_strtolower(trim($soal->kunci_jawaban ?? ''));
                if ($chosen === '') {
                    $jumlahKosong++;
                } elseif ($chosen === $key) {
                    $isCorrect = true;
                    $poinDidapat = $soal->poin;
                    $jumlahBenar++;
                } else {
                    $jumlahSalah++;
                }
            } elseif ($soal->tipe_soal === 'esai') {
                $chosen = trim($jawabanRecord->jawaban_esai ?? '');
                if (empty($chosen)) {
                    $jumlahKosong++;
                    $isCorrect = false;
                    $poinDidapat = 0;
                } else {
                    // Pending manual review by teacher
                    $isCorrect = null;
                    $poinDidapat = 0;
                }
            }

            $totalPoinDidapat += $poinDidapat;

            $jawabanRecord->update([
                'is_correct' => $isCorrect,
                'poin_didapat' => $poinDidapat,
            ]);
        }

        $waktuSelesai = now();
        // Carbon 3 diffInSeconds() bertanda negatif saat waktu_mulai di masa
        // lalu (kasus normal). Kolom durasi_aktual_detik bertipe unsignedInteger
        // sehingga nilai negatif memicu error di PostgreSQL. Selalu jadikan
        // non-negatif.
        $durasiAktualDetik = $sesi->waktu_mulai ? (int) abs($waktuSelesai->diffInSeconds($sesi->waktu_mulai)) : 0;

        $nilaiRaw = $totalPoinDidapat;
        $nilaiFinal = $maxPossiblePoints > 0 ? round(($nilaiRaw / $maxPossiblePoints) * 100, 2) : 0;

        $sesi->update([
            'waktu_selesai' => $waktuSelesai,
            'durasi_aktual_detik' => $durasiAktualDetik,
            'jumlah_benar' => $jumlahBenar,
            'jumlah_salah' => $jumlahSalah,
            'jumlah_kosong' => $jumlahKosong,
            'nilai_raw' => $nilaiRaw,
            'nilai_final' => $nilaiFinal,
            'status' => $status,
        ]);

        return $sesi->fresh(['ujian', 'siswa', 'jawaban']);
    }

    public function getHasilUjian(string $ujianId): array
    {
        $ujian = LmsUjian::with(['kisiKisi.subject', 'kelas', 'guru'])->find($ujianId);
        if (! $ujian) {
            return [];
        }

        $sesiList = LmsUjianSesi::with(['siswa', 'jawaban'])
            ->where('ujian_id', $ujianId)
            ->whereIn('status', ['selesai', 'timeout'])
            ->get();

        $totalSiswa = $sesiList->count();
        $lulusCount = $sesiList->where('nilai_final', '>=', $ujian->nilai_kkm)->count();
        $gagalCount = $totalSiswa - $lulusCount;

        $rataNilai = $totalSiswa > 0 ? round($sesiList->avg('nilai_final'), 2) : 0;
        $nilaiTertinggi = $totalSiswa > 0 ? $sesiList->max('nilai_final') : 0;
        $nilaiTerendah = $totalSiswa > 0 ? $sesiList->min('nilai_final') : 0;

        return [
            'ujian' => [
                'id' => $ujian->id,
                'judul_ujian' => $ujian->judul_ujian,
                'mata_pelajaran' => $ujian->kisiKisi->subject->name ?? '',
                'kelas' => $ujian->kelas->nama_kelas ?? '',
                'nilai_kkm' => (float) $ujian->nilai_kkm,
                'durasi_menit' => $ujian->durasi_menit,
            ],
            'ringkasan' => [
                'total_peserta' => $totalSiswa,
                'lulus_kkm' => $lulusCount,
                'tidak_lulus' => $gagalCount,
                'persentase_kelulusan' => $totalSiswa > 0 ? round(($lulusCount / $totalSiswa) * 100, 1) : 0,
                'rata_nilai' => $rataNilai,
                'nilai_tertinggi' => (float) $nilaiTertinggi,
                'nilai_terendah' => (float) $nilaiTerendah,
            ],
            'peserta' => $sesiList->map(function ($s) use ($ujian) {
                return [
                    'sesi_id' => $s->id,
                    'siswa_id' => $s->siswa_id,
                    'nama_siswa' => $s->siswa->full_name ?? 'Siswa',
                    'nis' => $s->siswa->nisn ?? $s->siswa->nis ?? '',
                    'waktu_mulai' => $s->waktu_mulai?->toIso8601String(),
                    'waktu_selesai' => $s->waktu_selesai?->toIso8601String(),
                    'durasi_menit' => round(($s->durasi_aktual_detik ?? 0) / 60, 1),
                    'jumlah_benar' => $s->jumlah_benar,
                    'jumlah_salah' => $s->jumlah_salah,
                    'jumlah_kosong' => $s->jumlah_kosong,
                    'nilai_final' => (float) $s->nilai_final,
                    'is_lulus' => (float) $s->nilai_final >= (float) $ujian->nilai_kkm,
                    'status' => $s->status,
                ];
            }),
        ];
    }

    public function gradeEssayAnswer(string $jawabanId, float $poinDidapat, ?string $catatanGuru = null, ?string $guruId = null): bool
    {
        $jawaban = LmsJawabanSiswa::with('sesi')->find($jawabanId);
        if (! $jawaban) {
            return false;
        }

        $jawaban->update([
            'poin_didapat' => $poinDidapat,
            'is_correct' => $poinDidapat > 0,
            'catatan_guru' => $catatanGuru,
            'dinilai_oleh' => $guruId ?? auth()->id(),
        ]);

        // Recalculate session final score
        $sesi = $jawaban->sesi;
        if ($sesi) {
            $totalPoin = LmsJawabanSiswa::where('sesi_id', $sesi->id)->sum('poin_didapat');
            $maxPossible = LmsBankSoal::where('kisi_kisi_id', $sesi->ujian->kisi_kisi_id)->sum('poin');
            $nilaiFinal = $maxPossible > 0 ? round(($totalPoin / $maxPossible) * 100, 2) : 0;

            $sesi->update([
                'nilai_raw' => $totalPoin,
                'nilai_final' => $nilaiFinal,
            ]);
        }

        return true;
    }
}
