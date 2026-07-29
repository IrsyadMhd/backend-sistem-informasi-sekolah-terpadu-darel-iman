<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsDiskusiKomentarRequest;
use App\Http\Requests\V1\LmsDiskusiRequest;
use App\Http\Resources\V1\LmsDiskusiKomentarResource;
use App\Http\Resources\V1\LmsDiskusiResource;
use App\Services\LmsDiskusiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsDiskusiController extends Controller
{
    public function __construct(
        protected LmsDiskusiService $diskusiService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'modul_ajar_id', 'kategori', 'status']);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->diskusiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsDiskusiResource::collection($data);
    }

    public function store(LmsDiskusiRequest $request): JsonResponse
    {
        $diskusi = $this->diskusiService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dibuat.',
            'data' => new LmsDiskusiResource($diskusi->load(['modulAjar', 'creator', 'komentar'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $diskusi = $this->diskusiService->cariBerdasarkanId($id);

        if (!$diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function update(LmsDiskusiRequest $request, string $id): JsonResponse
    {
        $diskusi = $this->diskusiService->ubah($id, $request->validated());

        if (!$diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil diperbarui.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->diskusiService->hapus($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $restored = $this->diskusiService->pulihkan($id);

        if (!$restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Diskusi Kelas.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Diskusi Kelas berhasil dipulihkan.',
        ]);
    }

    public function togglePin(string $id): JsonResponse
    {
        $diskusi = $this->diskusiService->togglePin($id);

        if (!$diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $diskusi->is_pinned ? 'Diskusi berhasil disematkan (pinned).' : 'Sematkan diskusi dilepas.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function toggleClose(string $id): JsonResponse
    {
        $diskusi = $this->diskusiService->toggleClose($id);

        if (!$diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $diskusi->is_closed ? 'Diskusi berhasil ditutup.' : 'Diskusi dibuka kembali.',
            'data' => new LmsDiskusiResource($diskusi),
        ]);
    }

    public function storeKomentar(LmsDiskusiKomentarRequest $request, string $id): JsonResponse
    {
        $diskusi = $this->diskusiService->cariBerdasarkanId($id);

        if (!$diskusi) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi Kelas tidak ditemukan.',
            ], 404);
        }

        if ($diskusi->is_closed) {
            return response()->json([
                'success' => false,
                'message' => 'Diskusi ini telah ditutup. Komentar baru tidak dapat ditambahkan.',
            ], 422);
        }

        $komentar = $this->diskusiService->tambahKomentar($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan.',
            'data' => new LmsDiskusiKomentarResource($komentar->load(['user', 'creator'])),
        ], 201);
    }

    public function destroyKomentar(string $diskusiId, string $komentarId): JsonResponse
    {
        $deleted = $this->diskusiService->hapusKomentar($komentarId);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Komentar tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil dihapus.',
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->diskusiService->dapatkanStatistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $modulOptions = $this->diskusiService->dapatkanOpsiModulAjar();

        return response()->json([
            'success' => true,
            'data' => [
                'modul_ajar' => $modulOptions,
                'modul_ajar_options' => $modulOptions,
                'kategori' => [
                    'Umum',
                    'Tanya Jawab',
                    'Tugas',
                    'Materi',
                    'Proyek',
                    'Refleksi',
                ],
                'peran' => [
                    'Guru',
                    'Siswa',
                    'Admin',
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
