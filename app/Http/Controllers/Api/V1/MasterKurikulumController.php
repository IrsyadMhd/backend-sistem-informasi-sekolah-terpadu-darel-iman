<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanMasterKurikulumRequest;
use App\Http\Requests\V1\UbahMasterKurikulumRequest;
use App\Http\Resources\V1\MasterKurikulumResource;
use App\Services\MasterKurikulumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MasterKurikulumController extends Controller
{
    public function __construct(
        protected MasterKurikulumService $service
    ) {}

    /**
     * Daftar Master Kurikulum (Paginated & Filtered).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'jenis_kurikulum' => $request->query('jenis_kurikulum'),
            'jenjang' => $request->query('jenjang'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $result = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data master kurikulum berhasil dimuat.',
            'data' => MasterKurikulumResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'from' => $result->firstItem(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'to' => $result->lastItem(),
                'total' => $result->total(),
            ],
            'statistik' => $this->service->dapatkanStatistik(),
        ]);
    }

    /**
     * Dropdown pilihan kurikulum.
     */
    public function dropdown(Request $request): JsonResponse
    {
        $unitId = $request->query('unit_pendidikan_id');
        $data = $this->service->dapatkanDropdown($unitId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data dropdown kurikulum berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Ringkasan statistik master kurikulum.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->service->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data master kurikulum baru.
     */
    public function store(SimpanMasterKurikulumRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $kurikulum = $this->service->simpan($request->validated(), $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data master kurikulum berhasil ditambahkan.',
            'data' => new MasterKurikulumResource($kurikulum),
        ], Response::HTTP_CREATED);
    }

    /**
     * Detail data master kurikulum.
     */
    public function show(string $id): JsonResponse
    {
        $kurikulum = $this->service->cariBerdasarkanId($id);

        if (! $kurikulum) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data master kurikulum tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data master kurikulum berhasil ditemukan.',
            'data' => new MasterKurikulumResource($kurikulum),
        ]);
    }

    /**
     * Perbarui data master kurikulum.
     */
    public function update(UbahMasterKurikulumRequest $request, string $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $kurikulum = $this->service->ubah($id, $request->validated(), $userId);

        if (! $kurikulum) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data master kurikulum tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data master kurikulum berhasil diperbarui.',
            'data' => new MasterKurikulumResource($kurikulum),
        ]);
    }

    /**
     * Hapus data master kurikulum (Soft Delete).
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $result = $this->service->hapus($id, $userId);

        if (! $result['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
        ]);
    }

    /**
     * Pulihkan data master kurikulum terhapus (Soft Delete Restore).
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->service->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data master kurikulum.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data master kurikulum berhasil dipulihkan.',
        ]);
    }

    /**
     * Impor batch data kurikulum.
     */
    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $userId = $request->user()?->id;
        $hasil = $this->service->prosesImport($rows, $userId);

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$hasil['berhasil']}, Gagal: {$hasil['gagal']}.",
            'data' => $hasil,
        ]);
    }

    /**
     * Ekspor data master kurikulum.
     */
    public function export(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'jenis_kurikulum' => $request->query('jenis_kurikulum'),
            'jenjang' => $request->query('jenjang'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
        ];

        $data = $this->service->eksporData($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Data ekspor master kurikulum berhasil dibuat.',
            'data' => $data,
        ]);
    }
}
