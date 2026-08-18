<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\GenerateLmsRaporRequest;
use App\Http\Requests\Lms\StoreLmsRaporRequest;
use App\Http\Requests\Lms\UpdateLmsRaporRequest;
use App\Http\Resources\LmsRaporResource;
use App\Services\LmsRaporService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsRaporController extends Controller
{
    public function __construct(
        protected LmsRaporService $raporService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'kelas_id',
            'semester_id',
            'tahun_ajaran_id',
            'status_rapor',
            'siswa_id',
            'with_trashed',
        ]);

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $rapors = $this->raporService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsRaporResource::collection($rapors);
    }

    public function store(StoreLmsRaporRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $rapor = $this->raporService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dibuat.',
            'data' => new LmsRaporResource($rapor->load(['siswa', 'kelas', 'semester', 'tahunAjaran', 'waliKelas'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $rapor = $this->raporService->cariBerdasarkanId($id, true);

        if (! $rapor) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function update(UpdateLmsRaporRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $rapor = $this->raporService->ubah($id, $validated);

        if (! $rapor) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil diperbarui.',
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->raporService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->raporService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Rapor Digital tidak ditemukan atau tidak dalam status terhapus.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dipulihkan.',
        ]);
    }

    public function generateClass(GenerateLmsRaporRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $records = $this->raporService->generateClass(
            $validated['kelas_id'],
            $validated['semester_id'],
            $validated['tahun_ajaran_id']
        );

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengolah & mengkalkulasi {$records->count()} Rapor Digital siswa dalam kelas.",
            'data' => LmsRaporResource::collection($records),
        ]);
    }

    public function exportPdf(string $id): JsonResponse
    {
        try {
            $pdfData = $this->raporService->getPdfData($id);

            return response()->json([
                'success' => true,
                'message' => 'Data cetak Rapor PDF berhasil disiapkan.',
                'data' => [
                    'rapor' => new LmsRaporResource($pdfData['rapor']),
                    'school_info' => $pdfData['school_info'],
                    'grades' => $pdfData['grades'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['kelas_id', 'semester_id', 'tahun_ajaran_id']);
        $stats = $this->raporService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->raporService->opsi();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function publish(string $id): JsonResponse
    {
        $rapor = $this->raporService->ubah($id, [
            'status_rapor' => 'published',
            'tanggal_terbit' => now()->toDateString(),
        ]);

        if (! $rapor) {
            return response()->json(['success' => false, 'message' => 'Rapor Digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dipublikasikan.',
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function approve(string $id): JsonResponse
    {
        $rapor = $this->raporService->ubah($id, ['status_rapor' => 'final']);

        if (! $rapor) {
            return response()->json(['success' => false, 'message' => 'Rapor Digital tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil disetujui (Approved).',
            'data' => new LmsRaporResource($rapor),
        ]);
    }
}
