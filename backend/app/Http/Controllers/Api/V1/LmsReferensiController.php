<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanReferensiRequest;
use App\Http\Requests\V1\UbahReferensiRequest;
use App\Http\Resources\V1\LmsReferensiResource;
use App\Services\LmsReferensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsReferensiController extends Controller
{
    public function __construct(
        protected LmsReferensiService $referensiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'modul_ajar_id' => $request->query('modul_ajar_id'),
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $referensis = $this->referensiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Referensi Pembelajaran berhasil dimuat.',
            'data' => LmsReferensiResource::collection($referensis),
            'meta' => [
                'current_page' => $referensis->currentPage(),
                'from' => $referensis->firstItem(),
                'last_page' => $referensis->lastPage(),
                'per_page' => $referensis->perPage(),
                'to' => $referensis->lastItem(),
                'total' => $referensis->total(),
            ],
            'statistik' => $this->referensiService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $referensi = $this->referensiService->cariBerdasarkanId($id);

        if (!$referensi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referensi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Referensi Pembelajaran berhasil dimuat.',
            'data' => new LmsReferensiResource($referensi),
        ]);
    }

    public function store(SimpanReferensiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $referensi = $this->referensiService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil disimpan.',
            'data' => new LmsReferensiResource($referensi),
        ], 201);
    }

    public function update(UbahReferensiRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $referensi = $this->referensiService->ubah($id, $data, $file);

        if (!$referensi) {
            return response()->json([
                'status' => 'error',
                'message' => 'Referensi Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil diperbarui.',
            'data' => new LmsReferensiResource($referensi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->referensiService->hapus($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Referensi Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->referensiService->pulihkan($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan Referensi Pembelajaran.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Referensi Pembelajaran berhasil dipulihkan.',
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->referensiService->statistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->referensiService->opsi(),
        ]);
    }
}
