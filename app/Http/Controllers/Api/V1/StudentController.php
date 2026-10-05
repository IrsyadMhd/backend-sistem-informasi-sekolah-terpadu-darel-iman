<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreStudentRequest;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    public function __construct(private readonly StudentRepositoryInterface $studentRepository) {}

    public function index(IndexRequest $request): JsonResponse
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($request->user());

        $requestedUnitId = $request->validated('unit_id')
            ?? $request->query('unit_id')
            ?? $request->query('unit_pendidikan_id');

        if (! $canAccessAllUnits) {
            abort_unless($unitId, 403, 'Akun tidak memiliki cakupan unit pendidikan.');
            abort_if($requestedUnitId && $requestedUnitId !== $unitId, 403, 'Akses data lintas unit tidak diizinkan.');
            $effectiveUnitId = $unitId;
        } else {
            $effectiveUnitId = $requestedUnitId;
        }

        $requestedKelasId = $request->validated('kelas_id')
            ?? $request->query('kelas_id')
            ?? $request->query('class_id');

        $requestedStatus = $request->validated('status')
            ?? $request->query('status');

        $data = $this->studentRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15),
            unitId: $effectiveUnitId,
            canAccessAllUnits: $canAccessAllUnits && empty($requestedUnitId),
            kelasId: $requestedKelasId,
            status: $requestedStatus
        );

        return response()->json($data);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $payload = $this->mappedPayload($request->validated());
        $payload['unit_id'] = $this->authorizedUnitId($request->user(), $payload['unit_id']);
        $payload['kelas_id'] = $this->authorizedKelasId($payload['kelas_id'], $payload['unit_id']);
        $student = Student::query()->create($payload);

        return response()->json([
            'message' => 'Data siswa berhasil disimpan.',
            'data' => $student,
        ], 201);
    }

    public function show(Request $request, string $student): JsonResponse
    {
        if (! Str::isUuid($student)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        return response()->json($this->scopedStudentQuery($request->user())->findOrFail($student));
    }

    public function update(StoreStudentRequest $request, string $student): JsonResponse
    {
        if (! Str::isUuid($student)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $model = $this->scopedStudentQuery($request->user())->findOrFail($student);
        $validated = $request->validated();
        $payload = $this->mappedPayload($validated);

        if (array_key_exists('unit_id', $validated)) {
            $payload['unit_id'] = $this->authorizedUnitId($request->user(), $payload['unit_id']);
        } else {
            unset($payload['unit_id']);
        }

        if (array_key_exists('kelas_id', $validated)) {
            $payload['kelas_id'] = $this->authorizedKelasId($payload['kelas_id'], $payload['unit_id'] ?? $model->unit_id, $student);
        } else {
            unset($payload['kelas_id']);
        }

        $model->update($payload);

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(Request $request, string $student): JsonResponse
    {
        if (! Str::isUuid($student)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $this->scopedStudentQuery($request->user())->findOrFail($student)->delete();

        return response()->json([
            'message' => 'Data siswa berhasil dihapus.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        [$bolehSemuaUnit, $unitPengguna, $employee] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id') ?? $request->query('unit_pendidikan_id');

        if (! $bolehSemuaUnit) {
            abort_unless($unitPengguna, 403, 'Akun tidak memiliki cakupan unit pendidikan.');
            abort_if($requestedUnitId && $requestedUnitId !== $unitPengguna, 403, 'Akses data lintas unit tidak diizinkan.');
            $effectiveUnitId = $unitPengguna;
        } else {
            $effectiveUnitId = $requestedUnitId;
        }

        $studentQuery = Student::query()
            ->with([
                'educationUnit:id,name,level',
                'kelas:id,nama_kelas,tingkat',
            ]);

        if (! empty($effectiveUnitId)) {
            $studentQuery->where('unit_id', $effectiveUnitId);
        } else {
            $this->applyUnitScope($studentQuery, $bolehSemuaUnit, $unitPengguna);
        }

        $counts = (clone $studentQuery)
            ->selectRaw("
                COUNT(*) as total_siswa,
                COUNT(CASE WHEN is_active = true THEN 1 END) as siswa_aktif,
                COUNT(CASE WHEN is_active = false THEN 1 END) as siswa_nonaktif,
                COUNT(CASE WHEN created_at >= ? THEN 1 END) as siswa_baru,
                COUNT(CASE WHEN metadata->>'mutasi_type' = 'keluar' OR (is_active = false AND metadata->>'mutasi_type' IS NOT NULL) THEN 1 END) as mutasi_keluar,
                COUNT(CASE WHEN metadata->>'is_alumni' = 'true' 
                            OR LOWER(COALESCE(metadata->>'status_siswa', '')) IN ('alumni', 'lulus') 
                            OR LOWER(COALESCE(metadata->>'status_alumni', '')) IN ('alumni', 'lulus', 'tamat')
                            OR LOWER(COALESCE(metadata->>'status', '')) IN ('alumni', 'lulus') THEN 1 END) as alumni,
                COUNT(CASE WHEN metadata->>'mutasi_type' = 'masuk' THEN 1 END) as mutasi_masuk,
                COUNT(CASE WHEN LOWER(gender) IN ('l', 'laki-laki', 'laki laki', 'male') THEN 1 END) as laki_laki,
                COUNT(CASE WHEN LOWER(gender) IN ('p', 'perempuan', 'female') THEN 1 END) as perempuan
            ", [now()->startOfYear()])
            ->first();

        $totalSiswa = (int) ($counts->total_siswa ?? 0);
        $siswaAktif = (int) ($counts->siswa_aktif ?? 0);
        $siswaNonaktif = (int) ($counts->siswa_nonaktif ?? 0);
        $siswaBaru = (int) ($counts->siswa_baru ?? 0);
        $mutasiKeluar = (int) ($counts->mutasi_keluar ?? 0);
        $alumni = (int) ($counts->alumni ?? 0);
        $mutasiMasuk = (int) ($counts->mutasi_masuk ?? 0);
        $lakiLaki = (int) ($counts->laki_laki ?? 0);
        $perempuan = (int) ($counts->perempuan ?? 0);

        $classQuery = Kelas::query();
        if (! empty($effectiveUnitId)) {
            $classQuery->where('unit_pendidikan_id', $effectiveUnitId);
        }
        $totalKelas = (clone $classQuery)->count();
        $classes = (clone $classQuery)
            ->withCount('siswa')
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas', 'tingkat', 'wali_kelas_id', 'kapasitas']);

        $daftarKelas = $classes->map(function (Kelas $class) {
            return [
                'id' => $class->id,
                'nama' => $class->nama_kelas,
                'level' => $class->tingkat,
                'wali_kelas_id' => $class->wali_kelas_id,
                'kapasitas' => (int) $class->kapasitas,
                'jumlah_siswa' => (int) ($class->siswa_count ?? 0),
            ];
        })->values();

        $tahunSekarang = (int) now()->format('Y');
        $grafikRaw = (clone $studentQuery)
            ->where('created_at', '>=', now()->subYears(3)->startOfYear())
            ->selectRaw("EXTRACT(YEAR FROM created_at)::int as tahun, COUNT(*) as jumlah")
            ->groupByRaw("EXTRACT(YEAR FROM created_at)")
            ->pluck('jumlah', 'tahun')
            ->all();

        $grafik = collect(range($tahunSekarang - 3, $tahunSekarang))
            ->map(fn (int $tahun) => [
                'tahun' => (string) $tahun,
                'jumlah' => (int) ($grafikRaw[$tahun] ?? 0),
            ])
            ->values();

        // Selected single student for quick preview card (limit 1)
        $selected = (clone $studentQuery)
            ->with([
                'educationUnit:id,name,level',
                'kelas:id,nama_kelas,tingkat',
            ])
            ->orderBy('full_name')
            ->first();

        // Include daftar_siswa if requested (with_list=true) or default for backwards compatibility
        // Uses high-speed DB query instead of hydrating thousands of Eloquent models
        $includeList = $request->boolean('with_list', true);
        $daftarSiswa = $includeList
            ? \Illuminate\Support\Facades\DB::table('students')
                ->leftJoin('education_units', 'students.unit_id', '=', 'education_units.id')
                ->leftJoin('tbl_kelas', 'students.kelas_id', '=', 'tbl_kelas.id')
                ->when(! empty($effectiveUnitId), fn ($q) => $q->where('students.unit_id', $effectiveUnitId))
                ->select([
                    'students.id',
                    'students.nis',
                    'students.full_name as nama',
                    'education_units.name as unit',
                    'education_units.level as jenjang',
                    'tbl_kelas.nama_kelas as kelas',
                    'students.gender as jenis_kelamin',
                    'students.is_active as aktif',
                    \Illuminate\Support\Facades\DB::raw("
                        CASE 
                            WHEN students.metadata->>'is_alumni' = 'true' OR LOWER(COALESCE(students.metadata->>'status_siswa', '')) IN ('alumni', 'lulus') OR LOWER(COALESCE(students.metadata->>'status_alumni', '')) IN ('alumni', 'lulus', 'tamat') THEN 'alumni'
                            WHEN students.metadata->>'mutasi_type' = 'keluar' THEN 'mutasi_keluar'
                            WHEN students.metadata->>'mutasi_type' = 'berhenti' THEN 'nonaktif'
                            WHEN students.metadata->>'mutasi_type' = 'antar_unit' THEN 'mutasi'
                            WHEN students.metadata->>'status' IS NOT NULL THEN students.metadata->>'status'
                            WHEN students.is_active = true THEN 'aktif'
                            ELSE 'nonaktif'
                        END as status
                    "),
                    \Illuminate\Support\Facades\DB::raw("
                        CASE 
                            WHEN students.metadata->>'is_alumni' = 'true' OR LOWER(COALESCE(students.metadata->>'status_siswa', '')) IN ('alumni', 'lulus') OR LOWER(COALESCE(students.metadata->>'status_alumni', '')) IN ('alumni', 'lulus', 'tamat') THEN true
                            ELSE false
                        END as is_alumni
                    "),
                    \Illuminate\Support\Facades\DB::raw("students.metadata->>'mutasi_type' as mutasi_type"),
                ])
                ->orderBy('students.full_name')
                ->get()
            : [];

        return response()->json([
            'akses' => [
                'semua_unit' => $bolehSemuaUnit,
                'unit_id' => $bolehSemuaUnit ? null : $unitPengguna,
                'unit_nama' => $bolehSemuaUnit ? 'Seluruh Unit Pendidikan' : ($employee?->unit?->name ?? null),
            ],
            'statistik' => [
                'total_siswa' => $totalSiswa,
                'total_kelas' => $totalKelas,
                'siswa_baru' => $siswaBaru,
                'mutasi_keluar' => $mutasiKeluar,
                'alumni' => $alumni,
                'siswa_aktif' => $siswaAktif,
                'siswa_nonaktif' => $siswaNonaktif,
            ],
            'komposisi_gender' => [
                'laki_laki' => $lakiLaki,
                'perempuan' => $perempuan,
            ],
            'daftar_siswa' => $daftarSiswa,
            'siswa_terpilih' => $selected ? [
                'id' => $selected->id,
                'nis' => $selected->nis,
                'nama' => $selected->full_name,
                'jenis_kelamin' => $selected->gender,
                'tempat_lahir' => $selected->birth_place,
                'tanggal_lahir' => optional($selected->birth_date)->toDateString(),
                'alamat' => $selected->address,
                'status' => $selected->is_active ? 'Aktif' : 'Nonaktif',
                'kelas' => $selected->kelas?->nama_kelas ?? '-',
                'tahun_masuk' => $selected->metadata['tahun_masuk'] ?? '-',
                'orang_tua' => [
                    'nama_ayah' => $selected->metadata['nama_ayah'] ?? '-',
                    'nama_ibu' => $selected->metadata['nama_ibu'] ?? '-',
                    'no_hp' => $selected->metadata['no_hp'] ?? '-',
                    'pekerjaan_ayah' => $selected->metadata['pekerjaan_ayah'] ?? '-',
                    'pekerjaan_ibu' => $selected->metadata['pekerjaan_ibu'] ?? '-',
                ],
            ] : null,
            'kelas_rombel' => $daftarKelas,
            'laporan_siswa' => [
                'siswa_baru' => $siswaBaru,
                'mutasi_masuk' => $mutasiMasuk,
                'mutasi_keluar' => $mutasiKeluar,
                'siswa_lulus' => $alumni,
                'grafik_tahunan' => $grafik,
            ],
        ]);
    }

    private function mappedPayload(array $validated): array
    {
        return [
            'parent_id' => $validated['parent_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'kelas_id' => $validated['kelas_id'] ?? null,
            'nis' => $validated['nis'],
            'nisn' => $validated['nisn'] ?? Arr::get($validated, 'metadata.nisn'),
            'full_name' => $validated['full_name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'] ?? null,
            'birth_place' => $validated['birth_place'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }

    private function scopedStudentQuery(User $user)
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($user);
        $query = Student::query();

        $this->applyUnitScope($query, $canAccessAllUnits, $unitId);

        return $query;
    }

    private function applyUnitScope($query, bool $canAccessAllUnits, ?string $unitId): void
    {
        if ($canAccessAllUnits) {
            return;
        }

        $query->when(
            $unitId,
            fn ($studentQuery) => $studentQuery->where('unit_id', $unitId),
            fn ($studentQuery) => $studentQuery->whereRaw('1 = 0')
        );
    }

    private function authorizedUnitId(User $user, ?string $requestedUnitId): ?string
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($user);

        if ($canAccessAllUnits) {
            return $requestedUnitId;
        }

        abort_unless($unitId, 403, 'Akun tidak memiliki cakupan unit pendidikan.');
        abort_if($requestedUnitId && $requestedUnitId !== $unitId, 403, 'Unit pendidikan tidak sesuai dengan cakupan akun.');

        return $unitId;
    }

    private function authorizedKelasId(?string $kelasId, ?string $unitId, ?string $currentStudentId = null): ?string
    {
        if (! $kelasId) {
            return null;
        }

        abort_unless($unitId, 422, 'Unit pendidikan wajib ditetapkan sebelum memilih kelas.');

        $kelas = Kelas::query()
            ->whereKey($kelasId)
            ->where('unit_pendidikan_id', $unitId)
            ->withCount(['siswa' => function ($q) {
                $q->where('is_active', true);
            }])
            ->first();

        abort_unless(
            $kelas,
            403,
            'Kelas tidak sesuai dengan unit pendidikan siswa.'
        );

        if ($kelas->kapasitas && (int) $kelas->kapasitas > 0) {
            $isAlreadyInThisClass = false;
            if ($currentStudentId) {
                $isAlreadyInThisClass = Student::query()
                    ->whereKey($currentStudentId)
                    ->where('kelas_id', $kelasId)
                    ->exists();
            }

            if (! $isAlreadyInThisClass && $kelas->siswa_count >= (int) $kelas->kapasitas) {
                abort(
                    422,
                    "Kuota kelas '{$kelas->nama_kelas}' sudah penuh ({$kelas->siswa_count}/{$kelas->kapasitas} siswa). Silakan pilih rombel lain."
                );
            }
        }

        return $kelasId;
    }

    private function scopeForUser(User $user): array
    {
        $employee = Employee::query()
            ->with([
                'position:id,name,scope_akses',
                'unit:id,name',
            ])
            ->where('user_id', $user->id)
            ->first();
        $canAccessAllUnits = $user->can('student.view_all')
            || $user->can('foundation.student.view')
            || $user->can('report.cross_unit.view')
            || $employee?->position?->scope_akses === 'semua_unit'
            || str_contains(strtolower((string) $employee?->position?->name), 'yayasan');
        $unitId = $employee?->unit_id
            ?? data_get($user->metadata, 'unit_id')
            ?? data_get($user->metadata, 'unit_pendidikan_id');

        return [$canAccessAllUnits, $unitId, $employee];
    }

    public function export(Request $request)
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id') ?? $request->query('unit_pendidikan_id');

        if (! $canAccessAllUnits) {
            abort_unless($unitId, 403, 'Akun tidak memiliki cakupan unit pendidikan.');
            abort_if($requestedUnitId && $requestedUnitId !== $unitId, 403, 'Akses ekspor data lintas unit tidak diizinkan.');
            $effectiveUnitId = $unitId;
        } else {
            $effectiveUnitId = $requestedUnitId;
        }

        $query = Student::query()
            ->with(['educationUnit', 'kelas.waliKelas', 'schoolClass', 'parent', 'user'])
            ->when($effectiveUnitId, fn ($q) => $q->where('unit_id', $effectiveUnitId));

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas_id')) {
            $kId = $request->query('kelas_id');
            if (Str::isUuid($kId)) {
                $query->where(fn ($q) => $q->where('kelas_id', $kId)->orWhere('class_id', $kId));
            } else {
                $query->whereHas('kelas', fn ($q) => $q->where('nama_kelas', 'ilike', "%{$kId}%"));
            }
        }

        if ($request->filled('status')) {
            $st = strtolower(trim($request->query('status')));
            if ($st === 'aktif') {
                $query->where('is_active', true);
            } elseif ($st === 'mutasi') {
                $query->where(fn ($q) => $q->whereRaw("LOWER(metadata->>'status_siswa') = 'mutasi'")->orWhereNotNull('metadata->mutasi_type'));
            } elseif ($st === 'lulus' || $st === 'alumni') {
                $query->where(fn ($q) => $q->whereRaw("LOWER(metadata->>'status_siswa') in ('lulus', 'alumni')")->orWhere('metadata->is_alumni', true));
            } elseif ($st === 'nonaktif') {
                $query->where('is_active', false);
            }
        }

        $query->orderBy('full_name', 'asc');

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $filename = 'data_siswa_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new StudentExport($query), $filename, $excelFormat);
        }

        $students = $query->get();
        $exporter = new StudentExport($query);
        $headings = $exporter->headings();

        $rows = $students->map(function ($std) use ($exporter) {
            return $exporter->map($std);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil diexport.',
            'headers' => $headings,
            'data' => $rows,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
            $tmpDir = storage_path('app/imports');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }
            $tmpName = 'import_' . uniqid() . '.' . $ext;
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
                        $norm = str_replace([' ', '_', '-', '(', ')', '/', '.'], '', $norm);
                        return $norm;
                    }, $sheetData[0]);

                    $rows = [];
                    for ($i = 1; $i < count($sheetData); $i++) {
                        $rawRow = $sheetData[$i];
                        if (empty(array_filter($rawRow, fn($v) => $v !== null && $v !== ''))) {
                            continue;
                        }
                        $item = [];
                        foreach ($headers as $idx => $normHeader) {
                            $val = $rawRow[$idx] ?? null;
                            if (in_array($normHeader, ['nis', 'nomorinduk', 'nomorinduksiswa', 'nissekolah'])) {
                                $item['nis'] = (string)$val;
                            } elseif (in_array($normHeader, ['nisn', 'nisnnasional'])) {
                                $item['nisn'] = (string)$val;
                            } elseif (in_array($normHeader, ['fullname', 'namalengkap', 'nama'])) {
                                $item['full_name'] = (string)$val;
                            } elseif (in_array($normHeader, ['gender', 'jeniskelamin', 'jk'])) {
                                $item['gender'] = (string)$val;
                            } elseif (in_array($normHeader, ['unitid', 'unit', 'unitpendidikan'])) {
                                $item['unit_id'] = $val;
                            } elseif (in_array($normHeader, ['kelasid', 'kelas'])) {
                                $item['kelas_id'] = $val;
                            } else {
                                $item[$normHeader] = $val;
                            }
                        }
                        $rows[] = $item;
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal membaca berkas import: ' . $e->getMessage(),
                ], 422);
            } finally {
                @unlink($tmpPath);
            }
        }

        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor siswa tidak boleh kosong.',
            ], 422);
        }

        $berhasil = 0;
        $gagal = 0;
        $duplikat = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $nama = trim($row['full_name'] ?? $row['namalengkap'] ?? $row['nama'] ?? '');
            $nis = trim($row['nis'] ?? '');
            $nisn = trim($row['nisn'] ?? '');

            if (empty($nama) || empty($nis)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Nama lengkap dan NIS siswa wajib diisi.";
                continue;
            }

            $nama = preg_replace('/\s+/', ' ', $nama);
            $nis = preg_replace('/\s+/', ' ', $nis);

            if (Student::withTrashed()->where('nis', $nis)->exists()) {
                $duplikat++;
                $errors[] = "Baris {$rowNum}: NIS '{$nis}' sudah terdaftar.";
                continue;
            }

            $rowMetadata = [];
            foreach ($row as $k => $v) {
                if (! in_array($k, ['nis', 'nisn', 'full_name', 'gender', 'unit_id', 'kelas_id', 'birth_date', 'birth_place', 'address'])) {
                    $rowMetadata[$k] = $v;
                }
            }

            try {
                Student::query()->create([
                    'nis' => $nis,
                    'nisn' => $nisn ?: null,
                    'full_name' => $nama,
                    'gender' => in_array(strtolower($row['gender'] ?? $row['jeniskelamin'] ?? ''), ['female', 'p', 'perempuan']) ? 'female' : 'male',
                    'unit_id' => $row['unit_id'] ?? null,
                    'kelas_id' => $row['kelas_id'] ?? null,
                    'birth_date' => ! empty($row['birth_date']) ? $row['birth_date'] : null,
                    'birth_place' => $row['birth_place'] ?? null,
                    'address' => $row['address'] ?? $row['alamatsiswa'] ?? null,
                    'is_active' => true,
                    'metadata' => $rowMetadata,
                ]);
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$berhasil}, Duplikat/Skip: {$duplikat}, Gagal: {$gagal}.",
            'data' => [
                'total' => count($rows),
                'berhasil' => $berhasil,
                'duplikat' => $duplikat,
                'gagal' => $gagal,
                'errors' => $errors,
            ],
        ]);
    }

    public function template(): JsonResponse
    {
        $exporter = new StudentExport(Student::query());
        return response()->json([
            'headers' => $exporter->headings(),
        ]);
    }
}
