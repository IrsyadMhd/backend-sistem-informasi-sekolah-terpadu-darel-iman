<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTujuanPembelajaranRequest;
use App\Http\Requests\UpdateTujuanPembelajaranRequest;
use App\Http\Resources\TujuanPembelajaranResource;
use App\Models\CapaianPembelajaran;
use App\Services\TujuanPembelajaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TujuanPembelajaranController extends Controller
{
    public function __construct(
        protected TujuanPembelajaranService $tpService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'cp_id', 'status', 'dengan_sampah']);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'urutan');
        $orderDir = $request->get('order_dir', 'asc');

        $tps = $this->tpService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return TujuanPembelajaranResource::collection($tps);
    }

    public function store(StoreTujuanPembelajaranRequest $request): JsonResponse
    {
        $tp = $this->tpService->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tujuan Pembelajaran berhasil ditambahkan.',
            'data' => new TujuanPembelajaranResource($tp->load(['capaianPembelajaran.subject', 'creator'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $tp = $this->tpService->cariBerdasarkanId($id, true);

        if (! $tp) {
            return response()->json([
                'success' => false,
                'message' => 'Tujuan Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TujuanPembelajaranResource($tp),
        ]);
    }

    public function update(UpdateTujuanPembelajaranRequest $request, string $id): JsonResponse
    {
        $tp = $this->tpService->ubah($id, $request->validated());

        if (! $tp) {
            return response()->json([
                'success' => false,
                'message' => 'Tujuan Pembelajaran tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tujuan Pembelajaran berhasil diperbarui.',
            'data' => new TujuanPembelajaranResource($tp),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->tpService->hapus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus Tujuan Pembelajaran.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tujuan Pembelajaran berhasil dihapus (soft delete).',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->tpService->pulihkan($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan Tujuan Pembelajaran.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tujuan Pembelajaran berhasil dipulihkan.',
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $filters = $request->only([
            'unit_pendidikan_id',
            'tahun_ajaran_id',
            'kurikulum_id',
            'mata_pelajaran_id',
        ]);

        $cps = CapaianPembelajaran::with(['subject', 'kurikulum'])
            ->filter($filters)
            ->where('status', true)
            ->orderBy('kode_cp', 'asc')
            ->get(['id', 'kode_cp', 'nama_cp', 'fase', 'kelas_target', 'mata_pelajaran_id', 'kurikulum_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'capaian_pembelajaran' => $cps->map(fn ($cp) => [
                    'id' => $cp->id,
                    'kode_cp' => $cp->kode_cp,
                    'nama_cp' => $cp->nama_cp,
                    'label' => "{$cp->kode_cp} - {$cp->nama_cp} (Fase {$cp->fase})",
                ]),
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tpService->statistik(),
        ]);
    }
}
