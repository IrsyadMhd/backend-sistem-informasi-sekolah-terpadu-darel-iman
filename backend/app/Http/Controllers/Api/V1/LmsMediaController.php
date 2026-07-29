<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanMediaRequest;
use App\Http\Requests\V1\UbahMediaRequest;
use App\Http\Resources\V1\LmsMediaResource;
use App\Services\LmsMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsMediaController extends Controller
{
    public function __construct(
        protected LmsMediaService $mediaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'materi_id' => $request->query('materi_id'),
            'tipe_file' => $request->query('tipe_file'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $medias = $this->mediaService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Media Pembelajaran berhasil dimuat.',
            'data' => LmsMediaResource::collection($medias),
            'meta' => [
                'current_page' => $medias->currentPage(),
                'from' => $medias->firstItem(),
                'last_page' => $medias->lastPage(),
                'per_page' => $medias->perPage(),
                'to' => $medias->lastItem(),
                'total' => $medias->total(),
            ],
            'statistik' => $this->mediaService->statistik(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $media = $this->mediaService->cariBerdasarkanId($id);

        if (!$media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Media Pembelajaran berhasil dimuat.',
            'data' => new LmsMediaResource($media),
        ]);
    }

    public function store(SimpanMediaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $media = $this->mediaService->simpan($data, $file);

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil disimpan.',
            'data' => new LmsMediaResource($media),
        ], 201);
    }

    public function update(UbahMediaRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $media = $this->mediaService->ubah($id, $data, $file);

        if (!$media) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil diperbarui.',
            'data' => new LmsMediaResource($media),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->mediaService->hapus($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus Media Pembelajaran atau data tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Media Pembelajaran berhasil dihapus.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'uuid'],
            'orders.*.urutan' => ['required', 'integer', 'min:1'],
        ]);

        $this->mediaService->reorder($request->input('orders'));

        return response()->json([
            'status' => 'success',
            'message' => 'Urutan Media Pembelajaran berhasil diperbarui.',
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->mediaService->statistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->mediaService->opsi(),
        ]);
    }
}
