<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPengumpulanTugasRequest;
use App\Http\Resources\V1\LmsPengumpulanTugasResource;
use App\Services\LmsPengumpulanTugasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPengumpulanTugasController extends Controller
{
    public function __construct(
        protected LmsPengumpulanTugasService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'penugasan_id', 'siswa_id', 'status', 'is_graded']);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPengumpulanTugasResource::collection($data);
    }

    public function store(LmsPengumpulanTugasRequest $request): JsonResponse
    {
        $pengumpulan = $this->service->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan tugas berhasil disimpan.',
            'data' => new LmsPengumpulanTugasResource($pengumpulan->load(['penugasan.subject', 'penugasan.kelas', 'siswa', 'penilai'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $pengumpulan = $this->service->cariBerdasarkanId($id);

        if (! $pengumpulan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumpulan tugas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsPengumpulanTugasResource($pengumpulan),
        ]);
    }

    public function update(LmsPengumpulanTugasRequest $request, string $id): JsonResponse
    {
        $pengumpulan = $this->service->ubah($id, $request->validated());

        if (! $pengumpulan) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumpulan tugas tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan tugas berhasil diperbarui.',
            'data' => new LmsPengumpulanTugasResource($pengumpulan),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->service->hapus($id);

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Pengumpulan tugas tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan tugas berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $restored = $this->service->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Pengumpulan tugas.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan tugas berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->service->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->service->dapatkanOpsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
