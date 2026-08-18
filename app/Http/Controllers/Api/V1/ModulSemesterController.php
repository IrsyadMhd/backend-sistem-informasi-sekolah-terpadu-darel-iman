<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanModulSemesterRequest;
use App\Http\Requests\V1\UbahModulSemesterRequest;
use App\Http\Resources\V1\ModulSemesterResource;
use App\Services\ModulSemesterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ModulSemesterController extends Controller
{
    public function __construct(
        protected ModulSemesterService $modulSemesterService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id') ?? $request->query('unit_id'),
            'kelas_id' => $request->query('kelas_id'),
            'guru_id' => $request->query('guru_id'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $moduls = $this->modulSemesterService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data Master Modul Semester berhasil dimuat.',
            'data' => ModulSemesterResource::collection($moduls),
            'meta' => [
                'current_page' => $moduls->currentPage(),
                'from' => $moduls->firstItem(),
                'last_page' => $moduls->lastPage(),
                'per_page' => $moduls->perPage(),
                'to' => $moduls->lastItem(),
                'total' => $moduls->total(),
            ],
            'statistik' => $this->modulSemesterService->dapatkanStatistik(),
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->modulSemesterService->dapatkanOpsiMaster();

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master modul semester berhasil dimuat.',
            'data' => $options,
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->modulSemesterService->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function store(SimpanModulSemesterRequest $request): JsonResponse
    {
        $modul = $this->modulSemesterService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Master Modul Semester berhasil ditambahkan.',
            'data' => new ModulSemesterResource($modul),
        ], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $modul = $this->modulSemesterService->cariBerdasarkanId($id);

        if (! $modul) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Master Modul Semester tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail Master Modul Semester berhasil ditemukan.',
            'data' => new ModulSemesterResource($modul),
        ]);
    }

    public function update(UbahModulSemesterRequest $request, string $id): JsonResponse
    {
        $modul = $this->modulSemesterService->ubah($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data Master Modul Semester berhasil diperbarui.',
            'data' => new ModulSemesterResource($modul),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->modulSemesterService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data modul semester.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data Master Modul Semester berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->modulSemesterService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data modul semester.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data Master Modul Semester berhasil dipulihkan.',
        ]);
    }

    public function duplicate(string $id): JsonResponse
    {
        $modulBaru = $this->modulSemesterService->duplikasi($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Modul Semester berhasil diduplikasi.',
            'data' => new ModulSemesterResource($modulBaru),
        ], Response::HTTP_CREATED);
    }

    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $status = $request->input('status', 'Aktif');
        $modul = $this->modulSemesterService->gantiStatus($id, $status);

        return response()->json([
            'status' => 'success',
            'message' => "Status Modul Semester berhasil diubah menjadi {$status}.",
            'data' => new ModulSemesterResource($modul),
        ]);
    }
}
