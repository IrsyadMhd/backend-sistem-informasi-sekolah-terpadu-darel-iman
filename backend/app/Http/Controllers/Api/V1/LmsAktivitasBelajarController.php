<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsAktivitasBelajarRequest;
use App\Http\Resources\V1\LmsAktivitasBelajarResource;
use App\Services\LmsAktivitasBelajarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsAktivitasBelajarController extends Controller
{
    public function __construct(
        protected LmsAktivitasBelajarService $aktivitasService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'modul_ajar_id', 'jenis_aktivitas', 'status']);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'urutan');
        $orderDir = $request->get('order_dir', 'asc');

        $data = $this->aktivitasService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsAktivitasBelajarResource::collection($data);
    }

    public function store(LmsAktivitasBelajarRequest $request): JsonResponse
    {
        $aktivitas = $this->aktivitasService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dibuat.',
            'data' => new LmsAktivitasBelajarResource($aktivitas->load(['modulAjar', 'creator'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $aktivitas = $this->aktivitasService->cariBerdasarkanId($id);

        if (!$aktivitas) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsAktivitasBelajarResource($aktivitas),
        ]);
    }

    public function update(LmsAktivitasBelajarRequest $request, string $id): JsonResponse
    {
        $aktivitas = $this->aktivitasService->ubah($id, $request->validated());

        if (!$aktivitas) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil diperbarui.',
            'data' => new LmsAktivitasBelajarResource($aktivitas),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->aktivitasService->hapus($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas Belajar tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $restored = $this->aktivitasService->pulihkan($id);

        if (!$restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Aktivitas Belajar.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas Belajar berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->aktivitasService->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $modulOptions = $this->aktivitasService->dapatkanOpsiModulAjar();

        return response()->json([
            'success' => true,
            'data' => [
                'modul_ajar' => $modulOptions,
                'jenis_aktivitas' => [
                    'Pendahuluan',
                    'Inti',
                    'Penutup',
                    'Diskusi',
                    'Kuis',
                    'Tugas',
                    'Presentasi',
                    'Refleksi',
                    'Eksperimen',
                    'Praktikum',
                ],
                'status' => [
                    'aktif' => 'Aktif',
                    'draft' => 'Draft',
                    'nonaktif' => 'Nonaktif',
                ],
            ],
        ]);
    }
}
