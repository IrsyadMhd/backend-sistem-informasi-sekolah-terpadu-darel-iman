<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsBankSoalRequest;
use App\Http\Requests\Lms\UpdateLmsBankSoalRequest;
use App\Http\Resources\LmsBankSoalResource;
use App\Services\LmsBankSoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsBankSoalController extends Controller
{
    public function __construct(
        protected LmsBankSoalService $bankSoalService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'kisi_kisi_id',
            'mata_pelajaran_id',
            'tipe_soal',
            'tingkat_kesulitan',
            'status',
            'with_trashed',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $soalList = $this->bankSoalService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsBankSoalResource::collection($soalList);
    }

    public function store(StoreLmsBankSoalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $soal = $this->bankSoalService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil ditambahkan ke Bank Soal.',
            'data' => new LmsBankSoalResource($soal->load(['kisiKisi.subject', 'kisiKisi.kelas', 'subject'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);

        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan di Bank Soal.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsBankSoalResource($soal),
        ]);
    }

    public function update(UpdateLmsBankSoalRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $soal = $this->bankSoalService->ubah($id, $validated);

        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil diperbarui.',
            'data' => new LmsBankSoalResource($soal),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->bankSoalService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->bankSoalService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil dipulihkan.',
        ]);
    }

    public function duplicate(string $id): JsonResponse
    {
        $duplicated = $this->bankSoalService->duplikasi($id);

        if (! $duplicated) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menduplikasi butir soal.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil diduplikasi.',
            'data' => new LmsBankSoalResource($duplicated),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['kisi_kisi_id', 'mata_pelajaran_id']);
        $stats = $this->bankSoalService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->bankSoalService->opsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
