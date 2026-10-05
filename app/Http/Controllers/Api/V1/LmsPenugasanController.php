<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPengumpulanTugasRequest;
use App\Http\Requests\V1\LmsPenugasanRequest;
use App\Http\Resources\V1\LmsPengumpulanTugasResource;
use App\Http\Resources\V1\LmsPenugasanResource;
use App\Models\LmsPenugasan;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsPenugasanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPenugasanController extends Controller
{
    public function __construct(
        protected LmsPenugasanService $penugasanService,
        protected AccessScopeService $accessScope
    ) {}

    private function assertCanAccessPenugasan(?User $user, LmsPenugasan $penugasan): void
    {
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

        $match = false;
        if ($penugasan->kelas_id && in_array($penugasan->kelas_id, $accessibleRombelIds, true)) {
            $match = true;
        } elseif ($penugasan->kelas && in_array($penugasan->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($penugasan->modulAjar && in_array($penugasan->modulAjar->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($penugasan->subject && in_array($penugasan->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        }

        abort_unless($match, 403, 'Akses ditolak: Penugasan berada di luar unit wewenang Anda.');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only(['search', 'modul_ajar_id', 'kelas_id', 'guru_id', 'mata_pelajaran_id', 'tipe', 'status', 'is_published', 'unit_pendidikan_id']);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

            if (! empty($filters['unit_pendidikan_id'])) {
                abort_unless(
                    in_array($filters['unit_pendidikan_id'], $allowedUnitIds, false) ||
                    in_array((int) $filters['unit_pendidikan_id'], array_map('intval', $allowedUnitIds), true),
                    403,
                    'Akses ditolak: Unit pendidikan di luar cakupan wewenang Anda.'
                );
            } else {
                $filters['unit_ids'] = $allowedUnitIds;
            }

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, false), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->penugasanService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPenugasanResource::collection($data);
    }

    public function store(LmsPenugasanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($validated['kelas_id'])) {
                abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            }
        }

        $penugasan = $this->penugasanService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dibuat.',
            'data' => new LmsPenugasanResource($penugasan->load(['modulAjar', 'guru', 'kelas', 'subject', 'semester', 'tahunAjaran', 'creator', 'pengumpulan'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        return response()->json([
            'success' => true,
            'data' => new LmsPenugasanResource($penugasan),
        ]);
    }

    public function update(LmsPenugasanRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);
        $validated = $request->validated();

        if ($request->user() && ! $this->accessScope->hasGlobalScope($request->user()) && ! empty($validated['kelas_id'])) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($request->user())->pluck('id')->all();
            abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel tujuan di luar cakupan unit Anda.');
        }

        $updated = $this->penugasanService->ubah($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil diperbarui.',
            'data' => new LmsPenugasanResource($updated),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);
        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        $this->penugasanService->hapus($id);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dihapus.',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);
        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        $restored = $this->penugasanService->pulihkan($id);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan berhasil dipulihkan.',
        ]);
    }

    public function togglePublish(Request $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        $updated = $this->penugasanService->togglePublish($id);

        return response()->json([
            'success' => true,
            'message' => $updated->is_published ? 'Penugasan berhasil dipublikasikan.' : 'Penugasan dikembalikan ke status Draft.',
            'data' => new LmsPenugasanResource($updated),
        ]);
    }

    public function gradeSubmission(LmsPengumpulanTugasRequest $request, string $id): JsonResponse
    {
        $penugasan = $this->penugasanService->cariBerdasarkanId($id);

        if (! $penugasan) {
            return response()->json([
                'success' => false,
                'message' => 'Penugasan tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessPenugasan($request->user(), $penugasan);

        $pengumpulan = $this->penugasanService->submitOrGrade($id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Pengumpulan/penilaian tugas berhasil disimpan.',
            'data' => new LmsPengumpulanTugasResource($pengumpulan),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only([
            'unit_pendidikan_id',
            'kelas_id',
            'modul_ajar_id',
            'guru_id',
            'mata_pelajaran_id',
            'tipe',
        ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

            if (! empty($filters['unit_pendidikan_id'])) {
                abort_unless(
                    in_array($filters['unit_pendidikan_id'], $allowedUnitIds, false) ||
                    in_array((int) $filters['unit_pendidikan_id'], array_map('intval', $allowedUnitIds), true),
                    403,
                    'Akses ditolak: Unit pendidikan di luar cakupan wewenang Anda.'
                );
            } else {
                $filters['unit_ids'] = $allowedUnitIds;
            }

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, false), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $stats = $this->penugasanService->dapatkanStatistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $options = $this->penugasanService->dapatkanOpsi($request->user());

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }
}
