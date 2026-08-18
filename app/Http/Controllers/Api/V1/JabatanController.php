<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanJabatanRequest;
use App\Http\Requests\V1\UbahJabatanRequest;
use App\Http\Resources\V1\JabatanResource;
use App\Models\Position;
use App\Services\JabatanService;
use App\Support\RoleName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JabatanController extends Controller
{
    public function __construct(
        protected JabatanService $jabatanService
    ) {}

    /**
     * Helper untuk memeriksa apakah user merupakan Kepala Sekolah
     */
    protected function userAdalahKepalaSekolah(?object $user): bool
    {
        if (! $user) {
            return false;
        }

        return RoleName::userHasAny($user, ['Kepala Sekolah', 'kepala_sekolah', 'kepsek']);
    }

    /**
     * Dapatkan daftar master jabatan berpaginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_sekolah_id' => $request->query('unit_sekolah_id') ?? $request->query('unit_id'),
            'satuan_kerja' => $request->query('satuan_kerja'),
            'level_jabatan' => $request->query('level_jabatan'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'urutan');
        $orderDir = (string) $request->query('order_dir', 'asc');

        $jabatan = $this->jabatanService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data master jabatan berhasil dimuat.',
            'data' => JabatanResource::collection($jabatan),
            'meta' => [
                'current_page' => $jabatan->currentPage(),
                'from' => $jabatan->firstItem(),
                'last_page' => $jabatan->lastPage(),
                'per_page' => $jabatan->perPage(),
                'to' => $jabatan->lastItem(),
                'total' => $jabatan->total(),
            ],
            'statistik' => $this->jabatanService->dapatkanStatistik(),
        ]);
    }

    /**
     * Dapatkan opsi masukan untuk dropdown form.
     */
    public function options(): JsonResponse
    {
        $options = $this->jabatanService->dapatkanOpsiMaster();

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master jabatan berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan ringkasan statistik jabatan.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->jabatanService->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data jabatan baru.
     */
    public function store(SimpanJabatanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($this->userAdalahKepalaSekolah($user)) {
            $isLevel1 = isset($validated['level_jabatan']) && (int) $validated['level_jabatan'] === 1;
            $isPengurus = isset($validated['satuan_kerja']) && $validated['satuan_kerja'] === 'Pengurus';
            $nameLower = strtolower($validated['nama_jabatan'] ?? '');

            if ($isLevel1 || $isPengurus || str_contains($nameLower, 'pengurus yayasan') || str_contains($nameLower, 'yayasan')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Role Kepala Sekolah tidak diizinkan untuk membuat atau mengubah data jabatan Pengurus Yayasan.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $userId = $user?->id;
        $jabatan = $this->jabatanService->simpan($validated, $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data jabatan berhasil ditambahkan.',
            'data' => new JabatanResource($jabatan),
        ], Response::HTTP_CREATED);
    }

    /**
     * Detail data jabatan berdasarkan ID.
     */
    public function show(string $id): JsonResponse
    {
        $jabatan = $this->jabatanService->cariBerdasarkanId($id);

        if (! $jabatan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data jabatan tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data jabatan berhasil ditemukan.',
            'data' => new JabatanResource($jabatan),
        ]);
    }

    /**
     * Perbarui data jabatan.
     */
    public function update(UbahJabatanRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $existing = Position::withTrashed()->find($id);

        if ($existing && $this->userAdalahKepalaSekolah($user) && $existing->isPengurusYayasan()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role Kepala Sekolah tidak diizinkan untuk mengubah data jabatan Pengurus Yayasan.',
            ], Response::HTTP_FORBIDDEN);
        }

        $userId = $user?->id;
        $jabatan = $this->jabatanService->ubah($id, $request->validated(), $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Data jabatan berhasil diperbarui.',
            'data' => new JabatanResource($jabatan),
        ]);
    }

    /**
     * Hapus data jabatan (Soft Delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $existing = Position::find($id);

        if ($existing && $this->userAdalahKepalaSekolah($user) && $existing->isPengurusYayasan()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role Kepala Sekolah tidak diizinkan untuk menghapus data jabatan Pengurus Yayasan.',
            ], Response::HTTP_FORBIDDEN);
        }

        $berhasil = $this->jabatanService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data jabatan.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data jabatan berhasil dihapus (soft delete).',
        ]);
    }

    /**
     * Pulihkan data jabatan terhapus.
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->jabatanService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data jabatan.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data jabatan berhasil dipulihkan.',
        ]);
    }

    /**
     * Impor batch data jabatan.
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
        $hasil = $this->jabatanService->prosesImport($rows, $userId);

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$hasil['berhasil']}, Gagal: {$hasil['gagal']}.",
            'data' => $hasil,
        ]);
    }

    /**
     * Ekspor data master jabatan.
     */
    public function export(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_sekolah_id' => $request->query('unit_sekolah_id') ?? $request->query('unit_id'),
            'satuan_kerja' => $request->query('satuan_kerja'),
            'level_jabatan' => $request->query('level_jabatan'),
            'status' => $request->query('status'),
        ];

        $data = $this->jabatanService->eksporData($filters);

        return response()->json([
            'status' => 'success',
            'message' => 'Data ekspor master jabatan berhasil dibuat.',
            'data' => $data,
        ]);
    }
}
