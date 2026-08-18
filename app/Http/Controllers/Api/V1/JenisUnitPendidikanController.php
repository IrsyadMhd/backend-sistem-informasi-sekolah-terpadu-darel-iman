<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanJenisUnitPendidikanRequest;
use App\Http\Requests\V1\UbahJenisUnitPendidikanRequest;
use App\Http\Resources\V1\JenisUnitPendidikanResource;
use App\Services\JenisUnitPendidikanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JenisUnitPendidikanController extends Controller
{
    public function __construct(
        protected JenisUnitPendidikanService $service
    ) {}

    /**
     * Dapatkan daftar master jenis unit pendidikan (Paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'jenjang' => $request->query('jenjang'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $result = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data master jenis unit pendidikan berhasil dimuat.',
            'data' => JenisUnitPendidikanResource::collection($result),
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
     * Opsi dropdown untuk form pilihan jenis unit.
     */
    public function dropdown(): JsonResponse
    {
        $data = $this->service->dapatkanDropdown();

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi dropdown jenis unit berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Ringkasan statistik jenis unit pendidikan.
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
     * Simpan data jenis unit pendidikan baru.
     */
    public function store(SimpanJenisUnitPendidikanRequest $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $jenisUnit = $this->service->simpan($request->validated(), $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data jenis unit pendidikan berhasil ditambahkan.',
            'data' => new JenisUnitPendidikanResource($jenisUnit),
        ], Response::HTTP_CREATED);
    }

    /**
     * Detail data jenis unit pendidikan berdasarkan ID/UUID.
     */
    public function show(string $id): JsonResponse
    {
        $jenisUnit = $this->service->cariBerdasarkanId($id);

        if (! $jenisUnit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data jenis unit pendidikan tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data jenis unit pendidikan berhasil ditemukan.',
            'data' => new JenisUnitPendidikanResource($jenisUnit),
        ]);
    }

    /**
     * Perbarui data jenis unit pendidikan.
     */
    public function update(UbahJenisUnitPendidikanRequest $request, string $id): JsonResponse
    {
        $userId = $request->user()?->id;
        $jenisUnit = $this->service->ubah($id, $request->validated(), $userId);

        if (! $jenisUnit) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data jenis unit pendidikan tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data jenis unit pendidikan berhasil diperbarui.',
            'data' => new JenisUnitPendidikanResource($jenisUnit),
        ]);
    }

    /**
     * Hapus data jenis unit pendidikan (Soft Delete dengan proteksi relasi).
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
     * Pulihkan data jenis unit terhapus (Soft Delete Restore).
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->service->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data jenis unit pendidikan.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data jenis unit pendidikan berhasil dipulihkan.',
        ]);
    }

    /**
     * Impor batch data jenis unit dari Excel / JSON.
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
     * Ekspor data master jenis unit pendidikan.
     */
    public function export(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'jenjang' => $request->query('jenjang'),
        ];

        $data = $this->service->eksporData($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Data ekspor master jenis unit pendidikan berhasil dibuat.',
            'data' => $data,
        ]);
    }
}
