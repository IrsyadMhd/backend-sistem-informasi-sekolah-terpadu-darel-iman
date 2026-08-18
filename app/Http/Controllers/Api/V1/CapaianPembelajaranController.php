<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanCapaianPembelajaranRequest;
use App\Http\Requests\V1\UbahCapaianPembelajaranRequest;
use App\Services\CapaianPembelajaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CapaianPembelajaranController extends Controller
{
    public function __construct(
        protected CapaianPembelajaranService $cpService
    ) {}

    /**
     * GET /api/capaian-pembelajaran/dropdown
     * Parameter filter: unit_pendidikan_id, tahun_ajaran_id, kurikulum_id, mata_pelajaran_id
     * Returns active CP list with fields: id, kode_cp, nama_cp
     */
    public function dropdown(Request $request): JsonResponse
    {
        $filters = [
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'kurikulum_id' => $request->query('kurikulum_id'),
            'mata_pelajaran_id' => $request->query('mata_pelajaran_id'),
        ];

        $cpList = $this->cpService->dapatkanDropdown($filters);

        $mappedData = $cpList->map(function ($cp) {
            return [
                'id' => $cp->id,
                'kode_cp' => $cp->kode_cp,
                'nama_cp' => $cp->nama_cp,
                'fase' => $cp->fase,
                'kelas_target' => $cp->kelas_target,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar dropdown Capaian Pembelajaran berhasil dimuat.',
            'data' => $mappedData,
        ]);
    }

    /**
     * Dapatkan daftar terpaginasi Capaian Pembelajaran.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'kurikulum_id' => $request->query('kurikulum_id'),
            'mata_pelajaran_id' => $request->query('mata_pelajaran_id'),
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $cpPaginator = $this->cpService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar Capaian Pembelajaran berhasil dimuat.',
            'data' => $cpPaginator->items(),
            'meta' => [
                'current_page' => $cpPaginator->currentPage(),
                'from' => $cpPaginator->firstItem(),
                'last_page' => $cpPaginator->lastPage(),
                'per_page' => $cpPaginator->perPage(),
                'to' => $cpPaginator->lastItem(),
                'total' => $cpPaginator->total(),
            ],
            'statistik' => $this->cpService->statistik(),
        ]);
    }

    /**
     * Dapatkan statistik Capaian Pembelajaran.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->cpService->statistik(),
        ]);
    }

    /**
     * Simpan data Capaian Pembelajaran baru.
     */
    public function store(SimpanCapaianPembelajaranRequest $request): JsonResponse
    {
        $cp = $this->cpService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Capaian Pembelajaran berhasil ditambahkan.',
            'data' => $cp,
        ], Response::HTTP_CREATED);
    }

    /**
     * Detail data Capaian Pembelajaran.
     */
    public function show(string $id): JsonResponse
    {
        $cp = $this->cpService->cariBerdasarkanId($id);

        if (! $cp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Capaian Pembelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'data' => $cp,
        ]);
    }

    /**
     * Ubah data Capaian Pembelajaran.
     */
    public function update(UbahCapaianPembelajaranRequest $request, string $id): JsonResponse
    {
        $cp = $this->cpService->ubah($id, $request->validated());

        if (! $cp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui. Capaian Pembelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Capaian Pembelajaran berhasil diperbarui.',
            'data' => $cp,
        ]);
    }

    /**
     * Hapus data Capaian Pembelajaran (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->cpService->hapus($id);

        if (! $deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus. Capaian Pembelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Capaian Pembelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Pulihkan data Capaian Pembelajaran yang dihapus.
     */
    public function restore(string $id): JsonResponse
    {
        $restored = $this->cpService->pulihkan($id);

        if (! $restored) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan. Capaian Pembelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Capaian Pembelajaran berhasil dipulihkan.',
        ]);
    }
}
