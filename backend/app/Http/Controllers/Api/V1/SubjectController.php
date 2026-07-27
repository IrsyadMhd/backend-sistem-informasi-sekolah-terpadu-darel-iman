<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanSubjectRequest;
use App\Http\Requests\V1\UbahSubjectRequest;
use App\Http\Resources\V1\SubjectResource;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class SubjectController
 * Controller REST API untuk manajemen Master Data Mata Pelajaran (Subject).
 */
class SubjectController extends Controller
{
    public function __construct(
        protected SubjectService $subjectService
    ) {}

    /**
     * Dapatkan daftar mata pelajaran terpaginasi.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $subjects = $this->subjectService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data mata pelajaran berhasil dimuat.',
            'data' => SubjectResource::collection($subjects),
            'meta' => [
                'current_page' => $subjects->currentPage(),
                'from' => $subjects->firstItem(),
                'last_page' => $subjects->lastPage(),
                'per_page' => $subjects->perPage(),
                'to' => $subjects->lastItem(),
                'total' => $subjects->total(),
            ],
            'statistik' => $this->subjectService->dapatkanStatistik(),
        ]);
    }

    /**
     * Dapatkan opsi dropdown master mata pelajaran.
     */
    public function dropdown(): JsonResponse
    {
        $options = $this->subjectService->dapatkanOpsiDropdown();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar opsi mata pelajaran berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan statistik data mata pelajaran.
     */
    public function stats(): JsonResponse
    {
        $stats = $this->subjectService->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data mata pelajaran baru.
     */
    public function store(SimpanSubjectRequest $request): JsonResponse
    {
        $subject = $this->subjectService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil ditambahkan.',
            'data' => new SubjectResource($subject),
        ], Response::HTTP_CREATED);
    }

    /**
     * Tampilkan detail data mata pelajaran berdasarkan ID.
     */
    public function show(string $id): JsonResponse
    {
        $subject = $this->subjectService->cariBerdasarkanId($id);

        if (!$subject) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mata pelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data mata pelajaran berhasil ditemukan.',
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function update(UbahSubjectRequest $request, string $id): JsonResponse
    {
        $subject = $this->subjectService->ubah($id, $request->validated());

        if (!$subject) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data mata pelajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil diperbarui.',
            'data' => new SubjectResource($subject),
        ]);
    }

    /**
     * Hapus data mata pelajaran (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $berhasil = $this->subjectService->hapus($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data mata pelajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Pulihkan data mata pelajaran yang telah terhapus.
     */
    public function restore(string $id): JsonResponse
    {
        $berhasil = $this->subjectService->pulihkan($id);

        if (!$berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data mata pelajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data mata pelajaran berhasil dipulihkan.',
        ]);
    }
}
