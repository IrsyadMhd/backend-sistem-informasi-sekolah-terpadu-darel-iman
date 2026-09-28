<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\MasterArrayExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanTahunAjaranRequest;
use App\Http\Requests\V1\UbahTahunAjaranRequest;
use App\Http\Resources\V1\TahunAjaranResource;
use App\Services\TahunAjaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class TahunAjaranController extends Controller
{
    public function __construct(
        protected TahunAjaranService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'start_date');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $result = $this->service->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data tahun ajaran berhasil dimuat.',
            'data' => TahunAjaranResource::collection($result),
            'meta' => [
                'current_page' => $result->currentPage(),
                'from' => $result->firstItem(),
                'last_page' => $result->lastPage(),
                'per_page' => $result->perPage(),
                'to' => $result->lastItem(),
                'total' => $result->total(),
            ],
            'statistik' => $this->service->dapatkanStatistik(),
        ]);
    }

    public function dropdown(): JsonResponse
    {
        $data = $this->service->dapatkanDropdown();

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi dropdown tahun ajaran berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->service->dapatkanStatistik();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function store(SimpanTahunAjaranRequest $request): JsonResponse
    {
        $item = $this->service->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data tahun ajaran berhasil ditambahkan.',
            'data' => new TahunAjaranResource($item),
        ], Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->service->dapatkanBerdasarkanId($id);
        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tahun ajaran tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data tahun ajaran berhasil dimuat.',
            'data' => new TahunAjaranResource($item),
        ]);
    }

    public function update(UbahTahunAjaranRequest $request, string $id): JsonResponse
    {
        $item = $this->service->ubah($id, $request->validated());
        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui data tahun ajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data tahun ajaran berhasil diperbarui.',
            'data' => new TahunAjaranResource($item),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $success = $this->service->hapus($id);
        if (! $success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data tahun ajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data tahun ajaran berhasil dihapus.',
        ]);
    }

    public function restore(string $id): JsonResponse
    {
        $success = $this->service->pulihkan($id);
        if (! $success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data tahun ajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data tahun ajaran berhasil dipulihkan.',
        ]);
    }

    public function setAktif(string $id): JsonResponse
    {
        $item = $this->service->setAktif($id);
        if (! $item) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengaktifkan tahun ajaran.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Tahun ajaran berhasil diaktifkan sebagai periode utama.',
            'data' => new TahunAjaranResource($item),
        ]);
    }

    public function export(Request $request)
    {
        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $data = $this->service->eksporData($filters);

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $headings = [
                'No',
                'ID',
                'Nama Tahun Ajaran',
                'Tanggal Mulai',
                'Tanggal Selesai',
                'Status Aktif',
                'Keterangan',
                'Dibuat Pada',
            ];
            $rows = collect($data)->map(fn ($r) => array_values($r));
            $filename = 'data_master_tahun_ajaran_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new MasterArrayExport($rows, $headings), $filename, $excelFormat);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data ekspor tahun ajaran berhasil dibuat.',
            'data' => $data,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'rows' => ['required', 'array'],
        ]);

        $rows = $request->input('rows', []);
        $totalImported = $this->service->prosesImport($rows);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil mengimpor {$totalImported} data tahun ajaran.",
            'total_imported' => $totalImported,
        ]);
    }
}
