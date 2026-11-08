<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\KelasExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SimpanKelasRequest;
use App\Http\Requests\V1\UbahKelasRequest;
use App\Http\Resources\V1\KelasResource;
use App\Models\Kelas;
use App\Services\AccessScopeService;
use App\Services\KelasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Class KelasController
 * Controller API untuk manajemen Master Data Kelas / Rombongan Belajar.
 */
class KelasController extends Controller
{
    public function __construct(
        protected KelasService $kelasService,
        protected AccessScopeService $accessScopeService
    ) {}

    /**
     * Dapatkan daftar kelas berpaginasi dengan pencarian, filter, dan sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id') ?? $request->query('unit_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'jenjang' => $request->query('jenjang'),
            'tingkat' => $request->query('tingkat'),
            'status' => $request->query('status'),
            'dengan_sampah' => $request->query('dengan_sampah'),
        ];

        $emp = \App\Models\Employee::where('user_id', $request->user()->id)->first();
        $userUnitId = $emp?->unit_id 
            ?? data_get($request->user()->metadata, 'education_unit_id') 
            ?? data_get($request->user()->metadata, 'unit_id');

        $canAccessAllUnits = $this->accessScopeService->hasGlobalScope($request->user())
            || $request->user()->hasAnyRole([
                'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
                'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus',
            ])
            || $request->user()->can('report.cross_unit.view');

        if (! $canAccessAllUnits) {
            $allowedUnitIds = $this->accessScopeService
                ->accessibleEducationUnits($request->user())
                ->pluck('id')
                ->all();

            if (! empty($filters['unit_pendidikan_id']) && $filters['unit_pendidikan_id'] !== 'all') {
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
        } else {
            if (! empty($filters['unit_pendidikan_id']) && in_array(strtolower((string) $filters['unit_pendidikan_id']), ['all', 'semua'], true)) {
                $filters['unit_pendidikan_id'] = null;
            }
        }

        $allowedKelasIds = $this->accessibleRombelIds(
            $request,
            $request->query('dengan_sampah') === 'true'
        );
        $filters['allowed_kelas_ids'] = $allowedKelasIds;

        $perPage = (int) $request->query('per_page', 15);
        $orderBy = (string) $request->query('order_by', 'created_at');
        $orderDir = (string) $request->query('order_dir', 'desc');

        $kelas = $this->kelasService->dapatkanDaftar($filters, $perPage, $orderBy, $orderDir);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar data kelas berhasil dimuat.',
            'data' => KelasResource::collection($kelas),
            'meta' => [
                'current_page' => $kelas->currentPage(),
                'from' => $kelas->firstItem(),
                'last_page' => $kelas->lastPage(),
                'per_page' => $kelas->perPage(),
                'to' => $kelas->lastItem(),
                'total' => $kelas->total(),
            ],
            'statistik' => $this->kelasService->dapatkanStatistik(
                $this->accessibleRombelIds($request),
                $filters['unit_pendidikan_id'] ?? null
            ),
        ]);
    }

    /**
     * Dapatkan opsi data master dropdown (Unit, Tahun Ajaran, Semester, Pegawai/Guru).
     */
    public function options(Request $request): JsonResponse
    {
        $canAccessAllUnits = $this->accessScopeService->hasGlobalScope($request->user())
            || $request->user()->hasAnyRole([
                'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
                'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus',
            ])
            || $request->user()->can('report.cross_unit.view');

        $allowedUnitIds = $canAccessAllUnits
            ? null
            : $this->accessScopeService
                ->accessibleEducationUnits($request->user())
                ->pluck('education_units.id')
                ->all();
        $options = $this->kelasService->dapatkanOpsiMaster($allowedUnitIds);

        return response()->json([
            'status' => 'success',
            'message' => 'Data opsi master berhasil dimuat.',
            'data' => $options,
        ]);
    }

    /**
     * Dapatkan ringkasan statistik kelas.
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->kelasService->dapatkanStatistik($this->accessibleRombelIds($request));

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * Simpan data kelas baru.
     */
    public function store(SimpanKelasRequest $request): JsonResponse
    {
        $this->assertUnitScope($request, $request->validated('unit_pendidikan_id'));
        $kelas = $this->kelasService->simpan($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil ditambahkan.',
            'data' => new KelasResource($kelas->load(['unitPendidikan', 'tahunAjaran', 'semester', 'waliKelas'])),
        ], Response::HTTP_CREATED);
    }

    /**
     * Tampilkan detail data kelas berdasarkan ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $kelas = $this->scopedKelas($request, $id);

        if (! $kelas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data kelas tidak ditemukan.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data kelas berhasil ditemukan.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Perbarui data kelas.
     */
    public function update(UbahKelasRequest $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $this->assertUnitScope($request, $request->validated('unit_pendidikan_id'));
        $kelas = $this->kelasService->ubah($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil diperbarui.',
            'data' => new KelasResource($kelas),
        ]);
    }

    /**
     * Hapus data kelas (Soft Delete).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $berhasil = $this->kelasService->hapus($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dihapus (soft delete).',
        ]);
    }

    /**
     * Pulihkan data kelas yang terhapus.
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id, true);
        $berhasil = $this->kelasService->pulihkan($id);

        if (! $berhasil) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan data kelas.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kelas/rombel berhasil dipulihkan.',
        ]);
    }

    /**
     * Dapatkan daftar siswa dalam kelas tertentu.
     */
    public function siswa(Request $request, string $id): JsonResponse
    {
        $this->scopedKelas($request, $id);
        $data = $this->kelasService->dapatkanSiswaRombel($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar siswa rombel berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Ekspor data kelas ke format XLSX, XLS, CSV, atau JSON.
     */
    public function export(Request $request)
    {
        $filters = [
            'search' => $request->query('search'),
            'unit_pendidikan_id' => $request->query('unit_pendidikan_id') ?? $request->query('unit_id'),
            'tahun_ajaran_id' => $request->query('tahun_ajaran_id'),
            'semester_id' => $request->query('semester_id'),
            'jenjang' => $request->query('jenjang'),
            'tingkat' => $request->query('tingkat'),
            'status' => $request->query('status'),
        ];

        $query = $this->accessScopeService->accessibleRombels($request->user())
            ->with(['unitPendidikan', 'waliKelas', 'siswa'])
            ->withCount('siswa');

        if (! empty($filters['unit_pendidikan_id']) && $filters['unit_pendidikan_id'] !== 'all') {
            $query->where('unit_pendidikan_id', $filters['unit_pendidikan_id']);
        }
        if (! empty($filters['tahun_ajaran_id']) && $filters['tahun_ajaran_id'] !== 'all') {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }
        if (! empty($filters['semester_id']) && $filters['semester_id'] !== 'all') {
            $query->where('semester_id', $filters['semester_id']);
        }
        if (! empty($filters['jenjang']) && $filters['jenjang'] !== 'all') {
            $query->where('jenjang', $filters['jenjang']);
        }
        if (! empty($filters['tingkat']) && $filters['tingkat'] !== 'all') {
            $query->where('tingkat', $filters['tingkat']);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                  ->orWhere('kode_kelas', 'like', "%{$search}%")
                  ->orWhere('ruangan', 'like', "%{$search}%");
            });
        }

        $classes = $query->orderBy('nama_kelas', 'asc')->get();

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $filename = 'data_kelas_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new KelasExport($classes), $filename, $excelFormat);
        }

        return response()->json([
            'status' => 'success',
            'data' => $classes,
        ]);
    }

    /**
     * Impor data kelas dari file / payload JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (is_string($rows)) {
            $decoded = json_decode($rows, true);
            if (is_array($decoded)) {
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
            $tmpName = 'import_kelas_' . uniqid() . '.' . $ext;
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
                        $norm = strtolower(trim((string) $h));
                        return str_replace([' ', '_', '-', '.', ':', '/'], '', $norm);
                    }, $sheetData[0]);

                    $rows = [];
                    for ($i = 1; $i < count($sheetData); $i++) {
                        $rawRow = $sheetData[$i];
                        if (empty(array_filter($rawRow, fn ($v) => $v !== null && $v !== ''))) {
                            continue;
                        }
                        $item = [];
                        foreach ($headers as $idx => $normHeader) {
                            $val = isset($rawRow[$idx]) ? trim((string) $rawRow[$idx]) : '';
                            if (in_array($normHeader, ['kode', 'kodekelas', 'code', 'classcode'])) {
                                $item['kode_kelas'] = $val;
                            } elseif (in_array($normHeader, ['nama', 'namakelas', 'name', 'classname', 'rombel'])) {
                                $item['nama_kelas'] = $val;
                            } elseif (in_array($normHeader, ['tingkat', 'level', 'grade', 'tingkatkelas'])) {
                                $item['tingkat'] = $val;
                            } elseif (in_array($normHeader, ['jenjang', 'jenjangpendidikan'])) {
                                $item['jenjang'] = $val;
                            } elseif (in_array($normHeader, ['unitpendidikanid', 'unitpendidikan', 'unitid', 'unit', 'school'])) {
                                $item['unit_pendidikan_id'] = $val;
                            } elseif (in_array($normHeader, ['tahunajaranid', 'tahunajaran', 'tahunid', 'tahun', 'academicyear'])) {
                                $item['tahun_ajaran_id'] = $val;
                            } elseif (in_array($normHeader, ['semesterid', 'semester'])) {
                                $item['semester_id'] = $val;
                            } elseif (in_array($normHeader, ['kapasitas', 'capacity', 'kuota'])) {
                                $item['kapasitas'] = $val;
                            } elseif (in_array($normHeader, ['ruangan', 'room', 'ruang'])) {
                                $item['ruangan'] = $val;
                            } elseif (in_array($normHeader, ['status', 'statusaktif', 'isactive'])) {
                                $item['status'] = $val;
                            }
                        }
                        if (! empty($item['nama_kelas']) || ! empty($item['kode_kelas'])) {
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
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong atau tidak ada baris data yang valid.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $overwrite = filter_var($request->input('overwrite', false), FILTER_VALIDATE_BOOLEAN);

        $scopedRows = $this->scopedImportRows($request, $rows);
        $hasil = $this->kelasService->prosesImport($scopedRows, $overwrite);

        $msgParts = [];
        if ($hasil['berhasil'] > 0) {
            $msgParts[] = "{$hasil['berhasil']} baru ditambahkan";
        }
        if ($hasil['diperbarui'] > 0) {
            $msgParts[] = "{$hasil['diperbarui']} berhasil ditimpa";
        }
        if ($hasil['duplikat'] > 0) {
            $msgParts[] = "{$hasil['duplikat']} duplikat dilewati";
        }
        if ($hasil['gagal'] > 0) {
            $msgParts[] = "{$hasil['gagal']} gagal";
        }
        $summary = $msgParts ? implode(', ', $msgParts) : 'Tidak ada baris yang diproses';

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai: {$summary}.",
            'data' => $hasil,
        ]);
    }

    /**
     * Dapatkan format/template impor data kelas.
     */
    public function template(): JsonResponse
    {
        return response()->json([
            'headers' => ['kode_kelas', 'nama_kelas', 'jenjang', 'tingkat', 'unit_pendidikan', 'tahun_ajaran', 'semester', 'kapasitas', 'ruangan', 'status'],
            'sample' => [
                'kode_kelas' => 'KLS-1A',
                'nama_kelas' => 'Kelas 1A Abu Bakar',
                'jenjang' => 'SDIT',
                'tingkat' => '1',
                'unit_pendidikan' => 'SDIT 1',
                'tahun_ajaran' => '2026/2027',
                'semester' => 'Ganjil',
                'kapasitas' => 30,
                'ruangan' => 'Ruang 101',
                'status' => 'Aktif',
            ],
        ]);
    }

    private function scopedKelas(Request $request, string $id, bool $withTrashed = false): Kelas
    {
        $query = $this->accessScopeService->accessibleRombels($request->user());
        if ($withTrashed) {
            $query->withTrashed();
        }

        $isUuid = Str::isUuid($id);

        $kelas = (clone $query)->where(function ($q) use ($id, $isUuid) {
            if ($isUuid) {
                $q->where('id', $id)->orWhere('kode_kelas', $id);
            } else {
                $q->where('kode_kelas', $id);
            }
        })->first();

        if ($kelas) {
            return $kelas;
        }

        if ($isUuid) {
            $legacyClass = \App\Models\SchoolClass::find($id);
            if ($legacyClass) {
                $matchedKelas = (clone $query)->where(function ($mq) use ($legacyClass) {
                    $mq->where('nama_kelas', $legacyClass->name)
                       ->orWhere('kode_kelas', $legacyClass->code ?? $legacyClass->name);
                })->first();
                if ($matchedKelas) {
                    return $matchedKelas;
                }
            }

            if (Kelas::where('id', $id)->exists() || \App\Models\SchoolClass::where('id', $id)->exists()) {
                abort(Response::HTTP_FORBIDDEN, 'Akses data kelas di luar unit wewenang Anda ditolak.');
            }
        }

        abort(Response::HTTP_NOT_FOUND, 'Data kelas tidak ditemukan.');
    }

    private function accessibleRombelIds(Request $request, bool $withTrashed = false): array
    {
        $query = $this->accessScopeService->accessibleRombels($request->user());
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->pluck('tbl_kelas.id')->all();
    }

    private function scopedImportRows(Request $request, array $rows): array
    {
        $allowedUnits = $this->accessScopeService
            ->accessibleEducationUnits($request->user())
            ->get(['education_units.id', 'education_units.code', 'education_units.name']);

        $allowedUnitIds = $allowedUnits->pluck('id')->all();
        abort_if($allowedUnitIds === [], 403, 'Tidak ada unit pendidikan dalam cakupan akun.');

        $fallbackUnitId = $request->input('unit_pendidikan_id') ?: $allowedUnitIds[0];
        if (! in_array($fallbackUnitId, $allowedUnitIds, true)) {
            $fallbackUnitId = $allowedUnitIds[0];
        }

        $allowedKelasIds = $this->accessibleRombelIds($request, true);
        $codes = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => trim((string) ($row['kode_kelas'] ?? $row['kode'] ?? '')))
            ->filter()
            ->unique();
        $existingByCode = Kelas::withTrashed()
            ->whereIn('kode_kelas', $codes)
            ->get(['id', 'kode_kelas'])
            ->keyBy('kode_kelas');

        return collect($rows)->map(function ($row) use ($allowedUnits, $allowedUnitIds, $fallbackUnitId, $allowedKelasIds, $existingByCode) {
            abort_unless(is_array($row), 422, 'Setiap baris impor harus berupa objek data.');

            $rawUnit = trim((string) ($row['unit_pendidikan_id'] ?? $row['unit_id'] ?? ''));
            $resolvedUnitId = null;

            if ($rawUnit !== '') {
                if (in_array($rawUnit, $allowedUnitIds, true)) {
                    $resolvedUnitId = $rawUnit;
                } else {
                    $matchedUnit = $allowedUnits->first(function ($u) use ($rawUnit) {
                        return strcasecmp((string) $u->code, $rawUnit) === 0
                            || strcasecmp((string) $u->name, $rawUnit) === 0
                            || strcasecmp((string) $u->id, $rawUnit) === 0;
                    });
                    if ($matchedUnit) {
                        $resolvedUnitId = $matchedUnit->id;
                    }
                }
            }

            // Jika nilai unit diberikan pada data namun tidak termasuk dalam unit wewenang akun
            if ($rawUnit !== '' && ! $resolvedUnitId) {
                abort(403, 'Baris impor memuat unit pendidikan di luar cakupan akun.');
            }

            $unitId = $resolvedUnitId ?: $fallbackUnitId;

            $code = trim((string) ($row['kode_kelas'] ?? $row['kode'] ?? ''));
            $existing = $code !== '' ? $existingByCode->get($code) : null;
            abort_unless(
                ! $existing || in_array($existing->id, $allowedKelasIds, true),
                403,
                'Data kelas yang akan diperbarui berada di luar cakupan akun.'
            );

            $row['unit_pendidikan_id'] = $unitId;
            unset($row['unit_id']);

            return $row;
        })->all();
    }

    private function assertUnitScope(Request $request, ?string $unitId): void
    {
        if (! $unitId) {
            return;
        }

        abort_unless(
            $this->accessScopeService->accessibleEducationUnits($request->user())->whereKey($unitId)->exists(),
            403,
            'Kelas berada di luar cakupan unit akun.'
        );
    }
}
