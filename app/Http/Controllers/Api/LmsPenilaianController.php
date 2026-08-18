<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\CalculateLmsPenilaianRequest;
use App\Http\Requests\Lms\StoreLmsPenilaianRequest;
use App\Http\Resources\LmsPenilaianResource;
use App\Services\LmsPenilaianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPenilaianController extends Controller
{
    public function __construct(
        protected LmsPenilaianService $penilaianService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'kelas_id',
            'subject_id',
            'semester_id',
            'is_passed',
            'with_trashed',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $grades = $this->penilaianService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPenilaianResource::collection($grades);
    }

    public function store(StoreLmsPenilaianRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $grade = $this->penilaianService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rekap Penilaian Siswa berhasil disimpan.',
            'data' => new LmsPenilaianResource($grade->load(['student', 'subject', 'kelas', 'semester'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $grade = $this->penilaianService->cariBerdasarkanId($id, true);

        if (! $grade) {
            return response()->json([
                'success' => false,
                'message' => 'Rekap Penilaian Siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsPenilaianResource($grade),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->all();
        $grade = $this->penilaianService->ubah($id, $validated);

        if (! $grade) {
            return response()->json([
                'success' => false,
                'message' => 'Rekap Penilaian Siswa tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekap Penilaian Siswa berhasil diperbarui.',
            'data' => new LmsPenilaianResource($grade),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->penilaianService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rekap Penilaian Siswa tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekap Penilaian Siswa berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->penilaianService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rekap Penilaian Siswa tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rekap Penilaian Siswa berhasil dipulihkan.',
        ]);
    }

    public function calculateAuto(CalculateLmsPenilaianRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $weights = [
            'bobot_tugas' => (float) ($validated['bobot_tugas'] ?? 20.0),
            'bobot_uh' => (float) ($validated['bobot_uh'] ?? 25.0),
            'bobot_uts' => (float) ($validated['bobot_uts'] ?? 25.0),
            'bobot_uas' => (float) ($validated['bobot_uas'] ?? 30.0),
            'nilai_kkm' => (float) ($validated['nilai_kkm'] ?? 75.0),
        ];

        $records = $this->penilaianService->kalkulasiKeterkaitanCbt(
            $validated['kelas_id'],
            $validated['subject_id'],
            $validated['semester_id'],
            $weights
        );

        return response()->json([
            'success' => true,
            'message' => "Berhasil melakukan auto-kalkulasi & sinkronisasi nilai CBT + Penugasan untuk {$records->count()} siswa.",
            'data' => LmsPenilaianResource::collection($records),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['kelas_id', 'subject_id', 'semester_id']);
        $stats = $this->penilaianService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->penilaianService->opsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
