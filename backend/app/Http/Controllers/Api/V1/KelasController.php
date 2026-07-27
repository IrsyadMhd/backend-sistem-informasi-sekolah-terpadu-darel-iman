<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanKelasRequest;
use App\Http\Requests\V1\UbahKelasRequest;
use App\Http\Resources\V1\KelasResource;
use App\Services\KelasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class KelasController
 * Controller API untuk manajemen Master Data Kelas / Rombongan Belajar.
 */
class KelasController extends Controller
{
    public function __construct(
        protected KelasService $kelasService
    ) {}

    /**
     * Dapatkan daftar kelas berpaginasi dengan pencarian, filter, dan sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id') ?? $request->query('unit_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'jenjang' => $request->query('jenjang'),
            'tingkat' => $request->query('tingkat'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $kelas = $this->kelasService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data kelas berhasil dimuat.',
            'data' => KelasResource::collection($kelas),
            'meta' => [
                'current_page' => $kelas->currentPage(),
                'from' => $kelas->firstItem(),
                'last_page' => $kelas->lastPage(),
                'per_page' => $kelas->perPage(),
                'to' => $kelas->lastItem(),
                'total' => $kelas->total(),
            ],
            'statistik' => $this->kelasService->dapatkanStatistik(),
        ]);
    }

    /**
     * Dapatkan opsi data master dropdown (Unit, Tahun Ajaran, Semester, Pegawai/Guru).
     */
    public function options(): JsonResponse
    {
        $options = $this->kelasService->dapatkanOpsiMaster();
        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan ringkasan statistik kelas.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->kelasService->dapatkanStatistik();
        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data kelas baru.
     */
    public function store(SimpanKelasRequest $request): JsonResponse
    {
        $kelas = $this->kelasService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil ditambahkan.',
            'data' => new KelasResource($kelas->load(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Tampilkan detail data kelas berdasarkan ID.
     */
    public function show(string $id): JsonResponse
    {
        $kelas = $this->kelasService->cariBerdasarkanId($id);

        if (!$kelas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data kelas tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data kelas berhasil ditemukan.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Perbarui data kelas.
     */
    public function update(UbahKelasRequest $request, string $id): JsonResponse
    {
        $kelas = $this->kelasService->ubah($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil diperbarui.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Hapus data kelas (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->kelasService->hapus($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dihapus (soft delete).',
        ]);
    }

    /**
     * Pulihkan data kelas yang terhapus.
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->kelasService->pulihkan($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dipulihkan.',
        ]);
    }

    /**
     * Dapatkan daftar siswa dalam kelas tertentu.
     */
    public function siswa(string $id): JsonResponse
    {
        $data = $this->kelasService->dapatkanSiswaRombel($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar siswa rombel berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Impor data kelas dari file / payload JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (!is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $hasil = $this->kelasService->prosesImport($rows);

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$hasil['berhasil']}, Gagal: {$hasil['gagal']}.",
            'data' => $hasil,
        ]);
    }
}
