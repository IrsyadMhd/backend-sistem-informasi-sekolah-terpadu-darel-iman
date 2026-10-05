<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsUjianRequest;
use App\Http\Requests\Lms\UpdateLmsUjianRequest;
use App\Http\Resources\LmsUjianResource;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\Student;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsUjianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class LmsUjianController extends Controller
{
    public function __construct(
        protected LmsUjianService $ujianService,
        protected AccessScopeService $accessScope
    ) {}

    /** Pengguna internal (guru/operator/admin); bukan Siswa/Orang Tua/Alumni. */
    private function isStaffUser(?object $user): bool
    {
        if (! $user || ! method_exists($user, 'hasAnyRole')) {
            return false;
        }

        return ! $user->hasAnyRole(['Siswa', 'Orang Tua', 'Alumni']);
    }

    /**
     * Sesi CBT hanya boleh diakses pemiliknya (Siswa yang login) atau staf
     * (untuk kepentingan proktor). Pengguna lain (Orang Tua, Alumni, akun
     * tanpa relasi) ditolak.
     */
    private function canAccessSession(Request $request, ?LmsUjianSesi $sesi): bool
    {
        if (! $sesi) {
            return false;
        }

        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Siswa')) {
            $siswa = Student::where('user_id', $user->id)->first();

            return $siswa !== null && $sesi->siswa_id === $siswa->id;
        }

        return $this->isStaffUser($user);
    }

    private function assertCanAccessUjian(?User $user, LmsUjian $ujian): void
    {
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

        $match = false;
        if ($ujian->kelas_id && in_array($ujian->kelas_id, $accessibleRombelIds, true)) {
            $match = true;
        } elseif ($ujian->kelas && in_array($ujian->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($ujian->kisiKisi) {
            $kisi = $ujian->kisiKisi;
            if ($kisi->kelas_id && in_array($kisi->kelas_id, $accessibleRombelIds, true)) {
                $match = true;
            } elseif ($kisi->kelas && in_array($kisi->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
                $match = true;
            } elseif ($kisi->subject && in_array($kisi->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
                $match = true;
            }
        }

        abort_unless($match, 403, 'Akses ditolak: Ujian CBT berada di luar unit wewenang Anda.');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'kisi_kisi_id',
            'kelas_id',
            'status',
            'with_trashed',
        ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            $filters['unit_ids'] = $allowedUnitIds;

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $ujianList = $this->ujianService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsUjianResource::collection($ujianList);
    }

    public function store(StoreLmsUjianRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $ujian = $this->ujianService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil dibuat.',
            'data' => new LmsUjianResource($ujian->load(['kisiKisi.subject', 'kelas', 'semester', 'guru'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);

        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);

        return response()->json([
            'success' => true,
            'data' => new LmsUjianResource($ujian),
        ]);
    }

    public function update(UpdateLmsUjianRequest $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);
        $validated = $request->validated();
        $updated = $this->ujianService->ubah($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diperbarui.',
            'data' => new LmsUjianResource($updated),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);

        $this->ujianService->hapus($id);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);

        $this->ujianService->pulihkan($id);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil dipulihkan.',
        ]);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menduplikasi Sesi CBT Ujian.',
            ], 400);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);

        $duplicated = $this->ujianService->duplikasi($id);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diduplikasi.',
            'data' => new LmsUjianResource($duplicated),
        ]);
    }

    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessUjian($request->user(), $ujian);

        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,berlangsung,selesai,dibatalkan'],
        ]);

        $updated = $this->ujianService->ubahStatusPublish($id, $request->status);

        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status publish CBT Ujian.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => "Status CBT Ujian diubah menjadi '{$request->status}'.",
            'data' => new LmsUjianResource($ujian),
        ]);
    }

    // CBT Student Test Engine API
    public function startSession(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Ujian tidak ditemukan.',
            ], 404);
        }

        $ujian = LmsUjian::query()->find($id);
        if (! $ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Ujian tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();
        $isSimulation = false;

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('Siswa')) {
            $siswa = Student::where('user_id', $user->id)->where('is_active', true)->first();
            if (! $siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Siswa tidak ditemukan untuk akun ini.',
                ], 403);
            }
            $siswaId = $siswa->id;
        } else {
            if (! $this->isStaffUser($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak memulai sesi ujian.',
                ], 403);
            }

            $siswaId = $request->input('siswa_id');
            if (! $siswaId) {
                // Mode Simulasi oleh staf (Guru / Kurikulum / Superadmin):
                // Ambil siswa pertama dari kelas sasaran ujian atau siswa aktif di sistem sebagai aktor simulasi
                $siswaId = Student::query()
                    ->when($ujian->kelas_id, fn ($sq) => $sq->where('kelas_id', $ujian->kelas_id))
                    ->where('is_active', true)
                    ->value('id');

                if (! $siswaId) {
                    $siswaId = Student::where('is_active', true)->value('id');
                }

                if (! $siswaId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Belum ada data siswa aktif di sistem untuk menjalankan simulasi CBT.',
                    ], 422);
                }

                $isSimulation = true;
            }
        }

        // Sesi 'proses' yang sudah ada dilanjutkan; selain itu berlaku gate
        // jadwal & batas percobaan agar tidak ada pengerjaan ganda/tanpa batas (kecuali simulasi staf).
        $active = LmsUjianSesi::query()
            ->where('ujian_id', $id)
            ->where('siswa_id', $siswaId)
            ->where('status', 'proses')
            ->first();

        if (! $active && ! $isSimulation) {
            if (! in_array($ujian->status, ['published', 'berlangsung'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian belum dapat dimulai.',
                ], 422);
            }
            if (($ujian->waktu_mulai && now()->lt($ujian->waktu_mulai)) || ($ujian->waktu_selesai && now()->gt($ujian->waktu_selesai))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ujian berada di luar jadwal pengerjaan.',
                ], 422);
            }
            $attempts = LmsUjianSesi::query()
                ->where('ujian_id', $id)
                ->where('siswa_id', $siswaId)
                ->whereIn('status', ['selesai', 'timeout'])
                ->count();
            if ($attempts >= (int) $ujian->max_attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Batas percobaan ujian telah tercapai.',
                ], 422);
            }
        }

        try {
            $sessionData = $this->ujianService->mulaiSesi($id, $siswaId);

            return response()->json([
                'success' => true,
                'message' => 'Sesi pengerjaan CBT Ujian dimulai.',
                'data' => $sessionData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function submitAnswers(Request $request, string $sesiId): JsonResponse
    {
        if (! Str::isUuid($sesiId)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.',
            ], 403);
        }

        $sesi = LmsUjianSesi::find($sesiId);
        if (! $this->canAccessSession($request, $sesi)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.',
            ], 403);
        }

        $jawaban = $request->input('jawaban', []);
        $success = $this->ujianService->simpanJawaban($sesiId, $jawaban);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jawaban sementara atau sesi ujian telah berakhir.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jawaban sementara berhasil tersimpan.',
        ]);
    }

    public function finishSession(Request $request, string $sesiId): JsonResponse
    {
        if (! Str::isUuid($sesiId)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.',
            ], 403);
        }

        $sesi = LmsUjianSesi::find($sesiId);
        if (! $this->canAccessSession($request, $sesi)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.',
            ], 403);
        }

        // Optional final save before ending
        if ($request->has('jawaban')) {
            $this->ujianService->simpanJawaban($sesiId, $request->input('jawaban'));
        }

        $finalSesi = $this->ujianService->selesaikanSesi($sesiId);

        if (! $finalSesi) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengakhiri sesi CBT Ujian.',
            ], 400);
        }

        // Nilai hanya ditampilkan bila ujian diatur tampilkan_nilai_langsung.
        $showScore = (bool) ($finalSesi->ujian?->tampilkan_nilai_langsung ?? false);

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diselesaikan dan dinilai otomatis!',
            'data' => [
                'id' => $finalSesi->id,
                'ujian_id' => $finalSesi->ujian_id,
                'status' => $finalSesi->status,
                'waktu_mulai' => $finalSesi->waktu_mulai?->toIso8601String(),
                'waktu_selesai' => $finalSesi->waktu_selesai?->toIso8601String(),
                'nilai_tersedia' => $showScore,
                'nilai_final' => $showScore ? (float) $finalSesi->nilai_final : null,
                'nilai_kkm' => $showScore ? (float) ($finalSesi->ujian?->nilai_kkm ?? 0) : null,
                'jumlah_benar' => $showScore ? (int) $finalSesi->jumlah_benar : null,
                'jumlah_salah' => $showScore ? (int) $finalSesi->jumlah_salah : null,
                'jumlah_kosong' => $showScore ? (int) $finalSesi->jumlah_kosong : null,
            ],
        ]);
    }

    public function results(Request $request, string $id): JsonResponse
    {
        if (! $this->isStaffUser($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak melihat hasil ujian.',
            ], 403);
        }

        $hasil = $this->ujianService->hasilUjian($id);

        return response()->json([
            'success' => true,
            'data' => $hasil,
        ]);
    }

    public function gradeEssay(Request $request, string $jawabanId): JsonResponse
    {
        if (! $this->isStaffUser($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak menilai jawaban ujian.',
            ], 403);
        }

        $request->validate([
            'poin_didapat' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan_guru' => ['nullable', 'string'],
        ]);

        $success = $this->ujianService->nilaiEssay($jawabanId, (float) $request->poin_didapat, $request->catatan_guru);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menilai jawaban esai.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nilai esai berhasil disimpan dan skor akhir dikalkulasi.',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['kelas_id']);
        $stats = $this->ujianService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->ujianService->opsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
