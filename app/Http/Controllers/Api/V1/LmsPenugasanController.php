<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPengumpulanTugasRequest;
use App\Http\Requests\V1\LmsPenugasanRequest;
use App\Http\Resources\V1\LmsPengumpulanTugasResource;
use App\Http\Resources\V1\LmsPenugasanResource;
use App\Services\LmsPenugasanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPenugasanController extends Controller
{
    public function __construct(
        protected LmsPenugasanService $penugasanService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'modul_ajar_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'tipe', 'status', 'is_published']);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->penugasanService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPenugasanResource::collection($data);
    }

    public function store(LmsPenugasanRequest $request): JsonResponse
    {
        $penugasan = $this->penugasanService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dibuat.',
            'data' => new LmsPenugasanResource($penugasan->load(['modulAjar', 'guru', 'kelas', 'subject', 'semester', 'tahunAjaran', 'creator', 'pengumpulan'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsPenugasanResource($penugasan),
        ]);
    }

    public function update(LmsPenugasanRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->ubah($id, $request->validated());

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil diperbarui.',
            'data' => new LmsPenugasanResource($penugasan),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->penugasanService->hapus($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $restored = $this->penugasanService->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Penugasan.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dipulihkan.',
        ]);
    }

    public function togglePublish(string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->togglePublish($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $penugasan->is_published ? 'Penugasan berhasil dipublikasikan.' : 'Penugasan dikembalikan ke status Draft.',
            'data' => new LmsPenugasanResource($penugasan),
        ]);
    }

    public function gradeSubmission(LmsPengumpulanTugasRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $pengumpulan = $this->penugasanService->submitOrGrade($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan/penilaian tugas berhasil disimpan.',
            'data' => new LmsPengumpulanTugasResource($pengumpulan),
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->penugasanService->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->penugasanService->dapatkanOpsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
