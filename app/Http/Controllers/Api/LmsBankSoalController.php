<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\StoreLmsBankSoalRequest;
use App\Http\Requests\Lms\UpdateLmsBankSoalRequest;
use App\Http\Resources\LmsBankSoalResource;
use App\Models\LmsBankSoal;
use App\Models\User;
use App\Services\AccessScopeService;
use App\Services\LmsBankSoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsBankSoalController extends Controller
{
    public function __construct(
        protected LmsBankSoalService $bankSoalService,
        protected AccessScopeService $accessScope
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->only([
            'search',
            'kisi_kisi_id',
            'mata_pelajaran_id',
            'kelas_id',
            'tipe_soal',
            'tingkat_kesulitan',
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

        $soalList = $this->bankSoalService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsBankSoalResource::collection($soalList);
    }

    public function store(StoreLmsBankSoalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $soal = $this->bankSoalService->simpan($validated);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil ditambahkan ke Bank Soal.',
            'data' => new LmsBankSoalResource($soal->load(['kisiKisi.subject', 'kisiKisi.kelas', 'subject'])),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);

        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan di Bank Soal.',
            ], 404);
        }

        $this->assertCanAccessSoal($request->user(), $soal);

        return response()->json([
            'success' => true,
            'data' => new LmsBankSoalResource($soal),
        ]);
    }

    public function update(UpdateLmsBankSoalRequest $request, string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);
        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        $this->assertCanAccessSoal($request->user(), $soal);
        $validated = $request->validated();
        $updated = $this->bankSoalService->ubah($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil diperbarui.',
            'data' => new LmsBankSoalResource($updated),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);
        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        $this->assertCanAccessSoal($request->user(), $soal);

        $this->bankSoalService->hapus($id);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);
        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan atau tidak dalam status terhapus.',
            ], 404);
        }

        $this->assertCanAccessSoal($request->user(), $soal);

        $this->bankSoalService->pulihkan($id);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil dipulihkan.',
        ]);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $soal = $this->bankSoalService->cariBerdasarkanId($id, true);
        if (! $soal) {
            return response()->json([
                'success' => false,
                'message' => 'Butir soal tidak ditemukan.',
            ], 404);
        }

        $this->assertCanAccessSoal($request->user(), $soal);

        $duplicated = $this->bankSoalService->duplikasi($id);

        return response()->json([
            'success' => true,
            'message' => 'Butir soal berhasil diduplikasi.',
            'data' => new LmsBankSoalResource($duplicated),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['kisi_kisi_id', 'mata_pelajaran_id', 'kelas_id']);
        $stats = $this->bankSoalService->statistik($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $unitId = $request->query('unit_pendidikan_id') ?? $request->query('unit_id');

        if ($user && ! $this->accessScope->hasGlobalScope($user)) {
            $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
            if ($unitId) {
                abort_unless(in_array($unitId, $allowedUnitIds, true), 403, 'Akses ditolak: Unit di luar cakupan wewenang Anda.');
            } else {
                $unitId = $this->accessScope->accessibleEducationUnits($user)->value('id');
            }
        }

        $options = $this->bankSoalService->opsi($unitId);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    private function assertCanAccessSoal(?User $user, LmsBankSoal $soal): void
    {
        if (! $user || $this->accessScope->hasGlobalScope($user)) {
            return;
        }

        $allowedUnitIds = $this->accessScope->accessibleEducationUnits($user)->pluck('id')->all();
        $accessibleRombelIds = $this->accessScope->accessibleRombels($user)->pluck('id')->all();

        $match = false;
        if ($soal->subject && in_array($soal->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
            $match = true;
        } elseif ($soal->kisiKisi) {
            $kisi = $soal->kisiKisi;
            if ($kisi->kelas_id && in_array($kisi->kelas_id, $accessibleRombelIds, true)) {
                $match = true;
            } elseif ($kisi->kelas && in_array($kisi->kelas->unit_pendidikan_id, $allowedUnitIds, true)) {
                $match = true;
            } elseif ($kisi->subject && in_array($kisi->subject->unit_pendidikan_id, $allowedUnitIds, true)) {
                $match = true;
            }
        }

        abort_unless($match, 403, 'Akses ditolak: Butir soal berada di luar unit wewenang Anda.');
    }
}
