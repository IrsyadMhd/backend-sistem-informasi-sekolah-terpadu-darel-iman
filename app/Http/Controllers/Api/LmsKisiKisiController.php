<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsKisiKisiRequest;
use App\Http\Requests\Lms\UpdateLmsKisiKisiRequest;
use App\Http\Resources\LmsKisiKisiResource;
use App\Services\LmsKisiKisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsKisiKisiController extends Controller
{
    public function __construct(
        protected LmsKisiKisiService $kisiKisiService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'mata_pelajaran_id',
            'jenis_ujian',
            'kurikulum_id',
            'cp_id',
            'tp_id',
            'kelas_id',
            'semester_id',
            'status',
            'with_trashed',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $kisiKisi = $this->kisiKisiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsKisiKisiResource::collection($kisiKisi);
    }

    public function store(StoreLmsKisiKisiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $kisi = $this->kisiKisiService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dibuat.',
            'data' => new LmsKisiKisiResource($kisi->load(['subject', 'cp', 'tp', 'kurikulum', 'kelas', 'semester', 'tahunAjaran', 'guru'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);

        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsKisiKisiResource($kisi),
        ]);
    }

    public function update(UpdateLmsKisiKisiRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $kisi = $this->kisiKisiService->ubah($id, $validated);

        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil diperbarui.',
            'data' => new LmsKisiKisiResource($kisi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->kisiKisiService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->kisiKisiService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dipulihkan.',
        ]);
    }

    public function duplicate(string $id): JsonResponse
    {
        $duplicated = $this->kisiKisiService->duplikasi($id);

        if (! $duplicated) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menduplikasi Kisi-kisi Ujian.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil diduplikasi.',
            'data' => new LmsKisiKisiResource($duplicated),
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->kisiKisiService->statistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $cpId = $request->query('cp_id');

        $options = $this->kisiKisiService->opsi($mataPelajaranId, $cpId);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
