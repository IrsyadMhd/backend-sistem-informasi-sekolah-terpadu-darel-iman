<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsUjianRequest;
use App\Http\Requests\Lms\UpdateLmsUjianRequest;
use App\Http\Resources\LmsUjianResource;
use App\Http\Resources\LmsUjianSesiResource;
use App\Models\Student;
use App\Services\LmsUjianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsUjianController extends Controller
{
    public function __construct(
        protected LmsUjianService $ujianService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'kisi_kisi_id',
            'kelas_id',
            'status',
            'with_trashed',
        ]);

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

    public function show(string $id): JsonResponse
    {
        $ujian = $this->ujianService->cariBerdasarkanId($id, true);

        if (!$ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsUjianResource($ujian),
        ]);
    }

    public function update(UpdateLmsUjianRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $ujian = $this->ujianService->ubah($id, $validated);

        if (!$ujian) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diperbarui.',
            'data' => new LmsUjianResource($ujian),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->ujianService->hapus($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->ujianService->pulihkan($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi CBT Ujian tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil dipulihkan.',
        ]);
    }

    public function duplicate(string $id): JsonResponse
    {
        $duplicated = $this->ujianService->duplikasi($id);

        if (!$duplicated) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menduplikasi Sesi CBT Ujian.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diduplikasi.',
            'data' => new LmsUjianResource($duplicated),
        ]);
    }

    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,berlangsung,selesai,dibatalkan'],
        ]);

        $ujian = $this->ujianService->ubahStatusPublish($id, $request->status);

        if (!$ujian) {
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
        $siswaId = $request->input('siswa_id');
        if (!$siswaId) {
            $siswa = Student::first();
            $siswaId = $siswa?->id;
        }

        if (!$siswaId) {
            return response()->json([
                'success' => false,
                'message' => 'Data Siswa tidak ditemukan untuk pengerjaan ujian.',
            ], 400);
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
        $jawaban = $request->input('jawaban', []);
        $success = $this->ujianService->simpanJawaban($sesiId, $jawaban);

        if (!$success) {
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
        // Optional final save before ending
        if ($request->has('jawaban')) {
            $this->ujianService->simpanJawaban($sesiId, $request->input('jawaban'));
        }

        $finalSesi = $this->ujianService->selesaikanSesi($sesiId);

        if (!$finalSesi) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengakhiri sesi CBT Ujian.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesi CBT Ujian berhasil diselesaikan dan dinilai otomatis!',
            'data' => new LmsUjianSesiResource($finalSesi),
        ]);
    }

    public function results(string $id): JsonResponse
    {
        $hasil = $this->ujianService->hasilUjian($id);

        return response()->json([
            'success' => true,
            'data' => $hasil,
        ]);
    }

    public function gradeEssay(Request $request, string $jawabanId): JsonResponse
    {
        $request->validate([
            'poin_didapat' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan_guru' => ['nullable', 'string'],
        ]);

        $success = $this->ujianService->nilaiEssay($jawabanId, (float) $request->poin_didapat, $request->catatan_guru);

        if (!$success) {
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
