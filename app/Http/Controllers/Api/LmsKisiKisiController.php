<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsKisiKisiRequest;
use App\Http\Requests\Lms\UpdateLmsKisiKisiRequest;
use App\Http\Resources\LmsKisiKisiResource;
use App\Models\LmsKisiKisi;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsKisiKisiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsKisiKisiController extends Controller
{
    public function __construct(
        protected LmsKisiKisiService $kisiKisiService,
        protected AccessScopeService $accessScope
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'mata_pelajaran_id',
            'jenis_ujian',
            'kurikulum_id',
            'cp_id',
            'tp_id',
            'kelas_id',
            'semester_id',
            'status',
            'with_trashed',
        ]);

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            $filters['unit_ids'] = $allowedUnitIds;

            if (! empty($filters['kelas_id'])) {
                abort_unless(in_array($filters['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            } else {
                $filters['kelas_ids'] = $accessibleRombelIds;
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');

        $kisiKisi = $this->kisiKisiService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsKisiKisiResource::collection($kisiKisi);
    }

    public function store(StoreLmsKisiKisiRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();
            if (! empty($validated['kelas_id'])) {
                abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel di luar cakupan unit Anda.');
            }
        }

        $kisi = $this->kisiKisiService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dibuat.',
            'data' => new LmsKisiKisiResource($kisi->load(['subject', 'cp', 'tp', 'kurikulum', 'kelas', 'semester', 'tahunAjaran', 'guru'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);

        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessKisiKisi($request->user(), $kisi);

        return response()->json([
            'success' => true,
            'data' => new LmsKisiKisiResource($kisi),
        ]);
    }

    public function update(UpdateLmsKisiKisiRequest $request, string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);
        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessKisiKisi($request->user(), $kisi);
        $validated = $request->validated();

        if ($request->user() && ! $this->accessScope->hasGlobalScope($request->user()) && ! empty($validated['kelas_id'])) {
            $accessibleRombelIds = $this->accessScope->accessibleRombels($request->user())->pluck('id')->all();
            abort_unless(in_array($validated['kelas_id'], $accessibleRombelIds, true), 403, 'Akses ditolak: Rombel tujuan di luar cakupan unit Anda.');
        }

        $updated = $this->kisiKisiService->ubah($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil diperbarui.',
            'data' => new LmsKisiKisiResource($updated),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);
        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessKisiKisi($request->user(), $kisi);

        $this->kisiKisiService->hapus($id);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);
        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessKisiKisi($request->user(), $kisi);

        $this->kisiKisiService->pulihkan($id);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil dipulihkan.',
        ]);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $kisi = $this->kisiKisiService->cariBerdasarkanId($id, true);
        if (! $kisi) {
            return response()->json([
                'success' => false,
                'message' => 'Kisi-kisi Ujian tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessKisiKisi($request->user(), $kisi);

        $duplicated = $this->kisiKisiService->duplikasi($id);

        return response()->json([
            'success' => true,
            'message' => 'Kisi-kisi Ujian berhasil diduplikasi.',
            'data' => new LmsKisiKisiResource($duplicated),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $stats = $this->kisiKisiService->statistik();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $mataPelajaranId = $request->query('mata_pelajaran_id');
        $cpId = $request->query('cp_id');
        $unitId = $request->query('unit_pendidikan_id') ?? $request->query('unit_id');

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            if ($unitId) {
                abort_unless(in_array($unitId, $allowedUnitIds, true), 403, 'Akses ditolak: Unit di luar cakupan wewenang Anda.');
            } else {
                $unitId = $this->accessScope->accessibleEducationUnits($user)->value('id');
            }
        }

        $options = $this->kisiKisiService->opsi($mataPelajaranId, $cpId, $unitId);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    private function assertCanAccessKisiKisi(?User $user, LmsKisiKisi $kisi): void
    {
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

        $unitMatch = false;
        if ($kisi->kelas_id && in_array($kisi->kelas_id, $accessibleRombelIds, true)) {
            $unitMatch = true;
        } elseif ($kisi->kelas && in_array($kisi->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
            $unitMatch = true;
        } elseif ($kisi->subject && in_array($kisi->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
            $unitMatch = true;
        }

        abort_unless($unitMatch, 403, 'Akses ditolak: Kisi-kisi ujian berada di luar unit wewenang Anda.');
    }
}
