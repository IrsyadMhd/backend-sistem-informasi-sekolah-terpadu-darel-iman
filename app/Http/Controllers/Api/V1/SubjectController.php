<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanSubjectRequest;
use App\Http\Requests\V1\UbahSubjectRequest;
use App\Http\Resources\V1\SubjectResource;
use App\Exports\SubjectExport;
use App\Services\SubjectService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

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
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id'),
            'kurikulum_id' => $request->query('kurikulum_id'),
            'kelompok_mapel' => $request->query('kelompok_mapel'),
            'kategori' => $request->query('kategori'),
            'jenjang' => $request->query('jenjang'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $emp = \App\Models\Employee::where('user_id', $request->user()?->id)->first();
        $userUnitId = $emp?->unit_id 
            ?? data_get($request->user()?->metadata, 'education_unit_id') 
            ?? data_get($request->user()?->metadata, 'unit_id');

        if ($request->user() && ! app(\App\Services\AccessScopeService::class)->hasGlobalScope($request->user())) {
            $allowedUnitIds = app(\App\Services\AccessScopeService::class)
                ->accessibleEducationUnits($request->user())
                ->pluck('id')
                ->all();

            if (! empty($filters['unit_pendidikan_id']) && $filters['unit_pendidikan_id'] !== 'all' && $filters['unit_pendidikan_id'] !== 'semua') {
                abort_unless(
                    in_array($filters['unit_pendidikan_id'], $allowedUnitIds, true),
                    403,
                    'Unit pendidikan tidak berada dalam cakupan akun.'
                );
            } elseif ($userUnitId) {
                $filters['unit_pendidikan_id'] = $userUnitId;
            } elseif (! empty($allowedUnitIds)) {
                $filters['allowed_unit_ids'] = $allowedUnitIds;
            }
        }

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
            'statistik' => $this->subjectService->dapatkanStatistik($filters['unit_pendidikan_id'] ?? null),
        ]);
    }

    /**
     * Dapatkan opsi dropdown master mata pelajaran.
     */
    public function dropdown(Request $request): JsonResponse
    {
        $options = $this->subjectService->dapatkanOpsiDropdown($request->only([
            'search', 'unit_pendidikan_id', 'kurikulum_id',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar opsi mata pelajaran berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan statistik data mata pelajaran.
     */
    public function stats(Request $request): JsonResponse
    {
        $unitId = $request->query('unit_pendidikan_id');

        $emp = \App\Models\Employee::where('user_id', $request->user()?->id)->first();
        $userUnitId = $emp?->unit_id 
            ?? data_get($request->user()?->metadata, 'education_unit_id') 
            ?? data_get($request->user()?->metadata, 'unit_id');

        if ($request->user() && ! app(\App\Services\AccessScopeService::class)->hasGlobalScope($request->user())) {
            $allowedUnitIds = app(\App\Services\AccessScopeService::class)
                ->accessibleEducationUnits($request->user())
                ->pluck('id')
                ->all();

            if (! empty($unitId) && $unitId !== 'all' && $unitId !== 'semua') {
                abort_unless(
                    in_array($unitId, $allowedUnitIds, true),
                    403,
                    'Unit pendidikan tidak berada dalam cakupan akun.'
                );
            } elseif ($userUnitId) {
                $unitId = $userUnitId;
            }
        }

        $stats = $this->subjectService->dapatkanStatistik($unitId);

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

        if (! $subject) {
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

        if (! $subject) {
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

        if (! $berhasil) {
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

        if (! $berhasil) {
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

    /**
     * Ubah status mata pelajaran secara massal (Bulk Status).
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'status' => ['required', 'boolean'],
        ]);

        $jumlah = $this->subjectService->ubahStatusMassal($request->input('ids'), $request->input('status'));

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil memperbarui status {$jumlah} mata pelajaran.",
            'affected_rows' => $jumlah,
        ]);
    }

    /**
     * Hapus data mata pelajaran secara massal (Bulk Delete).
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
        ]);

        $jumlah = $this->subjectService->hapusMassal($request->input('ids'));

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menghapus {$jumlah} data mata pelajaran.",
            'affected_rows' => $jumlah,
        ]);
    }

    /**
     * Ekspor data mata pelajaran ke format Excel / CSV.
     */
    public function exportExcel(Request $request)
    {
        $filters = $request->only(['search', 'unit_pendidikan_id', 'kurikulum_id', 'kelompok_mapel', 'kategori', 'jenjang', 'status']);
        $subjects = $this->subjectService->dapatkanDataEkspor($filters);

        $data = $subjects->map(function ($s) {
            return [
                'Kode Mapel' => $s->kode_mapel ?? $s->code,
                'Nama Mapel' => $s->nama_mapel ?? $s->name,
                'Nama Singkat' => $s->nama_singkat ?? '-',
                'Unit Pendidikan' => $s->unitPendidikan->name ?? '-',
                'Kurikulum' => $s->kurikulum->nama_kurikulum ?? '-',
                'Kelompok' => $s->kelompok_mapel ?? 'Pelajaran Wajib',
                'Kategori' => $s->kategori ?? 'Wajib',
                'Jenjang' => $s->jenjang ?? 'SD',
                'Jam Pelajaran' => $s->jam_pelajaran ?? 2,
                'KKM' => $s->kkm ?? 75.00,
                'Bobot Pengetahuan' => $s->bobot_pengetahuan ?? 40,
                'Bobot Keterampilan' => $s->bobot_keterampilan ?? 40,
                'Bobot Sikap' => $s->bobot_sikap ?? 20,
                'Status' => $s->status ? 'Aktif' : 'Non-Aktif',
            ];
        });

        $format = strtolower((string) $request->query('format', 'xlsx'));
        $timestamp = date('Ymd_His');

        return match ($format) {
            'csv' => Excel::download(new SubjectExport($subjects), "master_mata_pelajaran_{$timestamp}.csv", \Maatwebsite\Excel\Excel::CSV),
            'xls' => Excel::download(new SubjectExport($subjects), "master_mata_pelajaran_{$timestamp}.xls", \Maatwebsite\Excel\Excel::XLS),
            default => Excel::download(new SubjectExport($subjects), "master_mata_pelajaran_{$timestamp}.xlsx", \Maatwebsite\Excel\Excel::XLSX),
        };
    }

    /**
     * Ekspor data mata pelajaran ke format PDF.
     */
    public function exportPdf(Request $request)
    {
        $filters = $request->only(['search', 'unit_pendidikan_id', 'kurikulum_id', 'kelompok_mapel', 'kategori', 'jenjang', 'status']);
        $subjects = $this->subjectService->dapatkanDataEkspor($filters);

        return Pdf::loadView('exports.subjects', ['subjects' => $subjects])
            ->setPaper('a4', 'landscape')
            ->download('laporan_master_mata_pelajaran_'.date('Ymd_His').'.pdf');
    }

    /**
     * Impor data mata pelajaran.
     */
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
            $tmpName = 'import_subj_' . uniqid() . '.' . $ext;
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
                            if (in_array($normHeader, ['kodemapel', 'kode', 'code', 'kodematapelajaran'])) {
                                $item['kode_mapel'] = $val;
                            } elseif (in_array($normHeader, ['namamapel', 'nama', 'name', 'namamatapelajaran'])) {
                                $item['nama_mapel'] = $val;
                            } elseif (in_array($normHeader, ['kelompok', 'kelompokmapel', 'group'])) {
                                $item['kelompok_mapel'] = $val;
                            } elseif (in_array($normHeader, ['kategori', 'category'])) {
                                $item['kategori'] = $val;
                            } elseif (in_array($normHeader, ['jenjang', 'level'])) {
                                $item['jenjang'] = $val;
                            } elseif (in_array($normHeader, ['tingkat', 'tingkatkelas', 'grade'])) {
                                $item['tingkat_kelas'] = $val;
                            } elseif (in_array($normHeader, ['jampelajaran', 'jp', 'hours'])) {
                                $item['jam_pelajaran'] = $val;
                            } elseif (in_array($normHeader, ['kkm', 'passinggrade'])) {
                                $item['kkm'] = $val;
                            } elseif (in_array($normHeader, ['status', 'isactive', 'aktif'])) {
                                $item['status'] = $val;
                            } elseif (in_array($normHeader, ['deskripsi', 'description', 'keterangan'])) {
                                $item['deskripsi'] = $val;
                            }
                        }
                        if (!empty($item['nama_mapel']) || !empty($item['kode_mapel'])) {
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
                'message' => 'Payload data impor mata pelajaran tidak boleh kosong.',
            ], 422);
        }

        $result = $this->subjectService->prosesImport($rows, $request->user()?->id, $overwrite);
        $totalBerhasil = ($result['berhasil'] ?? 0) + ($result['diperbarui'] ?? 0);

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Total: {$result['total']}, Berhasil: {$totalBerhasil} ({$result['berhasil']} baru, {$result['diperbarui']} ditimpa), {$result['duplikat']} dilewati, {$result['gagal']} gagal.",
            'data' => $result,
        ]);
    }
}
