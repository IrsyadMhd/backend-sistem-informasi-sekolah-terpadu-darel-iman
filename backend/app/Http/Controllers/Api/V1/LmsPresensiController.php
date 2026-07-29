<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LmsPresensiBulkRequest;
use App\Http\Requests\V1\LmsPresensiRequest;
use App\Http\Resources\V1\LmsPresensiResource;
use App\Services\LmsPresensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LmsPresensiController extends Controller
{
    public function __construct(
        protected LmsPresensiService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'jadwal_pelajaran_id',
            'siswa_id',
            'status_hadir',
            'tanggal',
            'tanggal_mulai',
            'tanggal_selesai',
            'kelas_id',
            'subject_id',
        ]);
        $perPage = (int) $request->get('per_page', 15);
        $orderBy = $request->get('order_by', 'tanggal');
        $orderDir = $request->get('order_dir', 'desc');

        $data = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return LmsPresensiResource::collection($data);
    }

    public function store(LmsPresensiRequest $request): JsonResponse
    {
        $presensi = $this->service->simpan($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Presensi pembelajaran berhasil disimpan.',
            'data'    => new LmsPresensiResource($presensi->load(['jadwalPelajaran.subject', 'jadwalPelajaran.kelas', 'siswa'])),
        ], 201);
    }

    public function bulkStore(LmsPresensiBulkRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jadwalPelajaranId = $validated['jadwal_pelajaran_id'];
        $tanggal = $validated['tanggal'];
        $pertemuanKe = $validated['pertemuan_ke'] ?? 1;
        $items = $validated['items'];

        $results = $this->service->simpanBulk($jadwalPelajaranId, $tanggal, $pertemuanKe, $items);

        return response()->json([
            'success' => true,
            'message' => sprintf('Berhasil mencatat presensi untuk %d siswa.', $results->count()),
            'data'    => LmsPresensiResource::collection($results),
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $presensi = $this->service->cariBerdasarkanId($id);

        if (!$presensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new LmsPresensiResource($presensi),
        ]);
    }

    public function update(LmsPresensiRequest $request, string $id): JsonResponse
    {
        $presensi = $this->service->ubah($id, $request->validated());

        if (!$presensi) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan atau gagal diperbarui.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil diperbarui.',
            'data'    => new LmsPresensiResource($presensi),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->service->hapus($id);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Data presensi tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $restored = $this->service->pulihkan($id);

        if (!$restored) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan data presensi.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data presensi berhasil dipulihkan.',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['jadwal_pelajaran_id', 'tanggal']);
        $stats = $this->service->dapatkanStatistik($filters);

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    public function options(): JsonResponse
    {
        $options = $this->service->dapatkanOpsi();

        return response()->json([
            'success' => true,
            'data'    => $options,
        ]);
    }
}
