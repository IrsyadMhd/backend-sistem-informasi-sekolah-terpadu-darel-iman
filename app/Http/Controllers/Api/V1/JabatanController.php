<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanJabatanRequest;
use App\Http\Requests\V1\UbahJabatanRequest;
use App\Http\Resources\V1\JabatanResource;
use App\Models\Position;
use App\Services\AccessScopeService;
use App\Services\JabatanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class JabatanController extends Controller
{
    public function __construct(
        protected JabatanService $jabatanService,
        protected AccessScopeService $accessScopeService,
    ) {}

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

        $filters = $this->applyUnitReadScope($request, $filters);
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
            'statistik' => $this->jabatanService->dapatkanStatistik($filters),
        ]);
    }

    /**
     * Dapatkan opsi masukan untuk dropdown form.
     */
    public function options(Request $request): JsonResponse
    {
        $allowedUnitIds = $this->unitScopeIds($request);
        $options = $this->jabatanService->dapatkanOpsiMaster($allowedUnitIds);

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master jabatan berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan ringkasan statistik jabatan.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->jabatanService->dapatkanStatistik(
            $this->applyUnitReadScope($request, [])
        );

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
        if ($denied = $this->positionMutationDenied($user, null, $validated)) {
            return $denied;
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
    public function show(Request $request, string $id): JsonResponse
    {
        $jabatan = $this->jabatanService->cariBerdasarkanId($id);

        if ($jabatan && $this->isUnitOnlyManager($request) && ! $this->accessScopeService->accessiblePositions($request->user())->whereKey($id)->exists()) {
            $jabatan = null;
        }

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
        $validated = $request->validated();
        if ($denied = $this->positionMutationDenied($user, $existing, $validated)) {
            return $denied;
        }

        $userId = $user?->id;
        $jabatan = $this->jabatanService->ubah($id, $validated, $userId);

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
        if ($denied = $this->positionMutationDenied($user, $existing)) {
            return $denied;
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
    public function restore(Request $request, string $id): JsonResponse
    {
        $existing = Position::onlyTrashed()->find($id);
        if ($denied = $this->positionMutationDenied($request->user(), $existing)) {
            return $denied;
        }
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

        if ($this->isUnitOnlyManager($request)) {
            foreach ($rows as $row) {
                $denied = $this->positionMutationDenied($request->user(), null, [
                    'unit_sekolah_id' => $row['unit_sekolah_id'] ?? $row['unit_id'] ?? null,
                    'level_jabatan' => $row['level_jabatan'] ?? 8,
                    'satuan_kerja' => $row['satuan_kerja'] ?? 'Unit Pendidikan',
                    'scope_akses' => $row['scope_akses'] ?? 'unit_sendiri',
                ]);
                if ($denied) {
                    return $denied;
                }
            }
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

        $data = $this->jabatanService->eksporData($this->applyUnitReadScope($request, $filters));

        return response()->json([
            'status' => 'success',
            'message' => 'Data ekspor master jabatan berhasil dibuat.',
            'data' => $data,
        ]);
    }

    private function isUnitOnlyManager(Request $request): bool
    {
        return $this->accessScopeService->canManageUnitAccess($request->user())
            && ! $this->accessScopeService->canManageGlobalAccess($request->user());
    }

    private function unitScopeIds(Request $request): ?array
    {
        if (! $this->isUnitOnlyManager($request)) {
            return null;
        }

        return $this->accessScopeService
            ->accessibleEducationUnits($request->user())
            ->pluck('id')
            ->all();
    }

    private function applyUnitReadScope(Request $request, array $filters): array
    {
        $unitIds = $this->unitScopeIds($request);
        if ($unitIds !== null) {
            $filters['allowed_unit_ids'] = $unitIds;
        }

        return $filters;
    }

    private function positionMutationDenied(object $user, ?Position $position, array $payload = []): ?JsonResponse
    {
        try {
            $this->accessScopeService->assertPositionDefinitionMutation($user, $position, $payload);
        } catch (HttpException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode() ?: Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
