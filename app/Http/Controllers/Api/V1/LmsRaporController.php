<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\GenerateLmsRaporRequest;
use App\Http\Requests\Lms\StoreLmsRaporRequest;
use App\Http\Requests\Lms\UpdateLmsRaporRequest;
use App\Http\Resources\LmsRaporResource;
use App\Models\LmsRapor;
use App\Services\AccessScopeService;
use App\Services\LmsRaporService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsRaporController extends Controller
{
    public function __construct(
        protected LmsRaporService $raporService,
        protected AccessScopeService $accessScope
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'kelas_id',
            'semester_id',
            'tahun_ajaran_id',
            'status_rapor',
            'siswa_id',
            'with_trashed',
        ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }

            if (! empty($filters['siswa_id'])) {
                $accessibleStudentIds = $this->accessScope->accessibleStudents($user)->pluck('id')->all();
                abort_unless(in_array($filters['siswa_id'], $accessibleStudentIds, true), 403, 'Akses ditolak: Siswa di luar penugasan mengajar Anda.');
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $rapors = $this->raporService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsRaporResource::collection($rapors);
    }

    public function store(StoreLmsRaporRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertKelasAccess($request, $validated['kelas_id']);
        $rapor = $this->raporService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rapor Digital berhasil dibuat.',
            'data' => new LmsRaporResource($rapor->load(['siswa', 'kelas', 'semester', 'tahunAjaran', 'waliKelas'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $rapor = $this->assertRaporAccess($request, $id);

        return response()->json([
            'success' => true,
            'data' => new LmsRaporResource($rapor),
        ]);
    }

    public function update(UpdateLmsRaporRequest $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
        $validated = $request->validated();
        if (! empty($validated['kelas_id'])) {
            $this->assertKelasAccess($request, $validated['kelas_id']);
        }
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
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

    public function restore(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
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
        $this->assertKelasAccess($request, $validated['kelas_id']);

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

    public function exportPdf(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);

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
        $user = $request->user();
        $filters = $request->only(['kelas_id', 'semester_id', 'tahun_ajaran_id']);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $stats = $this->raporService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $options = $this->raporService->opsi();

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombels = $this->accessScope->accessibleRombels($user)->get();
            $accessibleStudents = $this->accessScope->accessibleStudents($user)->get();

            $options['kelases'] = $accessibleRombels->map(fn ($k) => [
                'id' => $k->id,
                'nama_kelas' => $k->nama_kelas ?? $k->name,
                'tingkat' => $k->tingkat ?? $k->level,
            ])->values()->all();

            $options['students'] = $accessibleStudents->map(fn ($s) => [
                'id' => $s->id,
                'full_name' => $s->full_name ?? $s->name,
                'nisn' => $s->nisn,
                'nis' => $s->nis,
                'kelas_id' => $s->kelas_id,
            ])->values()->all();
        }

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
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

    public function approve(Request $request, string $id): JsonResponse
    {
        $this->assertRaporAccess($request, $id);
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

    private function assertKelasAccess(Request $request, string $kelasId): void
    {
        $user = $request->user();
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
        abort_unless(in_array($kelasId, $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar penugasan mengajar Anda.');
    }

    private function assertRaporAccess(Request $request, string $id): LmsRapor
    {
        $rapor = $this->raporService->cariBerdasarkanId($id, true);
        abort_unless($rapor, 404, 'Rapor Digital tidak ditemukan.');

        $user = $request->user();
        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            abort_unless(in_array($rapor->kelas_id, $accessibleRombelIds, true), 403, 'Akses ditolak: Rapor berada di luar rombel penugasan Anda.');
        }

        return $rapor;
    }
}
