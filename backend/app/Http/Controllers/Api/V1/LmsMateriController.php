<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanMateriRequest;
use App\Http\Requests\V1\UbahMateriRequest;
use App\Http\Resources\V1\LmsMateriResource;
use App\Services\LmsMateriService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsMateriController extends Controller
{
    public function __construct(
        protected LmsMateriService $materiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'modul_ajar_id' => $request->query('modul_ajar_id'),
            'tipe' => $request->query('tipe'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $materis = $this->materiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Materi Pembelajaran berhasil dimuat.',
            'data' => LmsMateriResource::collection($materis),
            'meta' => [
                'current_page' => $materis->currentPage(),
                'from' => $materis->firstItem(),
                'last_page' => $materis->lastPage(),
                'per_page' => $materis->perPage(),
                'to' => $materis->lastItem(),
                'total' => $materis->total(),
            ],
            'statistik' => $this->materiService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $materi = $this->materiService->cariBerdasarkanId($id, true);

        if (!$materi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Materi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Materi Pembelajaran berhasil dimuat.',
            'data' => new LmsMateriResource($materi),
        ]);
    }

    public function store(SimpanMateriRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $materi = $this->materiService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil disimpan.',
            'data' => new LmsMateriResource($materi),
        ], 201);
    }

    public function update(UbahMateriRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $materi = $this->materiService->ubah($id, $data, $file);

        if (!$materi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Materi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil diperbarui.',
            'data' => new LmsMateriResource($materi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->materiService->hapus($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Materi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->materiService->pulihkan($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Materi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Materi Pembelajaran berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->materiService->statistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->materiService->opsi(),
        ]);
    }
}
