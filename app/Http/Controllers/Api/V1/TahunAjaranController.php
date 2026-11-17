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
        $overwrite = filter_var($request->input('overwrite', false), FILTER_VALIDATE_BOOLEAN);
        $rows = $request->input('data', $request->input('rows', []));
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rows = $decoded;
            }
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
            $tmpDir = storage_path('app/imports');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }
            $tmpName = 'import_ta_' . uniqid() . '.' . $ext;
            $file->move($tmpDir, $tmpName);
            $tmpPath = $tmpDir . '/' . $tmpName;

            try {
                if (in_array($ext, ['csv', 'txt'])) {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                } elseif ($ext === 'xls') {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                } else {
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                }
                $spreadsheet = $reader->load($tmpPath);
                $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
                if (count($sheetData) > 1) {
                    $headers = array_map(function ($h) {
                        $norm = strtolower(trim((string)$h));
                        return str_replace([' ', '_', '-', '.', ':', '/'], '', $norm);
                    }, $sheetData[0]);

                    $rows = [];
                    for ($i = 1; $i < count($sheetData); $i++) {
                        $rawRow = $sheetData[$i];
                        if (empty(array_filter($rawRow, fn($v) => $v !== null && $v !== ''))) {
                            continue;
                        }
                        $item = [];
                        foreach ($headers as $idx => $normHeader) {
                            $val = isset($rawRow[$idx]) ? trim((string)$rawRow[$idx]) : '';
                            if (in_array($normHeader, ['nama', 'namatahunajaran', 'tahunajaran', 'name', 'tahun'])) {
                                $item['name'] = $val;
                            } elseif (in_array($normHeader, ['startdate', 'tanggalmulai', 'tglmulai', 'mulai'])) {
                                $item['start_date'] = $val;
                            } elseif (in_array($normHeader, ['enddate', 'tanggalselesai', 'tglselesai', 'selesai', 'akhir'])) {
                                $item['end_date'] = $val;
                            } elseif (in_array($normHeader, ['isactive', 'status', 'aktif', 'keaktifan'])) {
                                $item['is_active'] = $val;
                            } elseif (in_array($normHeader, ['keterangan', 'deskripsi', 'catatan'])) {
                                $item['keterangan'] = $val;
                            }
                        }
                        if (!empty($item['name'])) {
                            $rows[] = $item;
                        }
                    }
                }
                @unlink($tmpPath);
            } catch (\Exception $e) {
                @unlink($tmpPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membaca berkas: ' . $e->getMessage(),
                ], 422);
            }
        }

        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tahun ajaran tidak boleh kosong.',
            ], 422);
        }

        $result = $this->service->prosesImport($rows, $overwrite);
        $totalBerhasil = $result['berhasil'] + $result['diperbarui'];

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Total: {$result['total']}, Berhasil: {$totalBerhasil} ({$result['berhasil']} baru, {$result['diperbarui']} ditimpa), {$result['duplikat']} dilewati, {$result['gagal']} gagal.",
            'data' => $result,
        ]);
    }

    public function importTemplate(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'columns' => ['name', 'start_date', 'end_date', 'is_active', 'keterangan'],
            'sample' => [
                [
                    'name' => '2026/2027',
                    'start_date' => '2026-07-01',
                    'end_date' => '2027-06-30',
                    'is_active' => '1',
                    'keterangan' => 'Tahun Ajaran 2026/2027',
                ],
            ],
        ]);
    }
}
