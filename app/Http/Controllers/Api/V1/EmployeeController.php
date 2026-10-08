<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EmployeeExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Services\AccessScopeService;
use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    protected EmployeeService $employeeService;

    public function __construct(
        EmployeeService $employeeService,
        protected AccessScopeService $accessScopeService
    ) {
        $this->employeeService = $employeeService;
    }

    public function dashboard(Request $request)
    {
        $filters = $request->only(['unit_id', 'jabatan_id', 'status_pegawai', 'status', 'jenis_kelamin']);
        $emp = Employee::where('user_id', $request->user()->id)->first();
        $userUnitId = $emp?->unit_id ?? data_get($request->user()->metadata, 'education_unit_id') ?? data_get($request->user()->metadata, 'unit_id');

        $isGlobalUser = $this->accessScopeService->hasGlobalScope($request->user());

        if (! $isGlobalUser || $request->user()->hasAnyRole(['Tata Usaha', 'tata_usaha', 'TU', 'tu', 'staf_tu']) || $request->boolean('exclude_restricted_roles')) {
            $filters['exclude_restricted_roles'] = true;
        }

        if (! $isGlobalUser) {
            if (! empty($filters['unit_id'])) {
                $this->accessScopeService->assertEducationUnitAccess($request->user(), $filters['unit_id']);
            } elseif ($userUnitId) {
                $filters['unit_id'] = $userUnitId;
            } else {
                $filters['allowed_unit_ids'] = $this->accessScopeService->accessibleEducationUnits($request->user())->pluck('id')->all();
            }
        }

        $stats = $this->employeeService->getDashboardStats($filters);

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'unit_id', 'jabatan_id', 'status_pegawai', 'status', 'jenis_kelamin']);
        $emp = Employee::where('user_id', $request->user()->id)->first();
        $userUnitId = $emp?->unit_id ?? data_get($request->user()->metadata, 'education_unit_id') ?? data_get($request->user()->metadata, 'unit_id');

        $isGlobalUser = $this->accessScopeService->hasGlobalScope($request->user());

        if (! $isGlobalUser || $request->user()->hasAnyRole(['Tata Usaha', 'tata_usaha', 'TU', 'tu', 'staf_tu']) || $request->boolean('exclude_restricted_roles')) {
            $filters['exclude_restricted_roles'] = true;
        }

        if (! $isGlobalUser) {
            if (! empty($filters['unit_id'])) {
                $this->accessScopeService->assertEducationUnitAccess($request->user(), $filters['unit_id']);
            } elseif ($userUnitId) {
                $filters['unit_id'] = $userUnitId;
            } else {
                $filters['allowed_unit_ids'] = $this->accessScopeService->accessibleEducationUnits($request->user())->pluck('id')->all();
            }
        }

        $perPage = (int) $request->get('per_page', 15);

        $result = $this->employeeService->list($filters, $perPage);

        return response()->json($result);
    }

    public function show(Request $request, string $id)
    {
        if (! Str::isUuid($id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pegawai tidak ditemukan',
            ], 404);
        }

        $employee = $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->whereKey($id)
            ->first();

        if (! $employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pegawai tidak ditemukan',
            ], 404);
        }

        $employee->load([
            'unit:id,name,code',
            'position:id,name,code,level_jabatan',
            'division:id,name',
            'user:id,name,email',
            'role:id,name',
            'teachings.subject:id,name,nama_mapel,kode_mapel',
            'teachings.classroom:id,name',
            'schedules.subject:id,name,nama_mapel,kode_mapel',
            'schedules.kelas:id,nama_kelas',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $employee,
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->accessScopeService->assertGlobalEmployeeMutation($request->user());
        $data = $request->validated();
        $employee = $this->employeeService->create($data);

        // SYNC TEACHINGS jika diberikan saat pembuatan data pegawai baru
        $teachings = $request->get('teachings') ?? data_get($data, 'metadata.teachings');
        if (is_array($teachings) && ! empty($teachings)) {
            $this->employeeService->assignTeaching($employee->id, $teachings);
            $employee->load([
                'unit:id,name,code',
                'position:id,name,code,level_jabatan',
                'teachings.subject:id,name,nama_mapel,kode_mapel',
                'teachings.classroom:id,name',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pegawai berhasil ditambahkan',
            'data' => $employee,
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, string $id)
    {
        $employee = $this->scopedEmployee($request, $id);
        $data = $request->validated();

        if (! $this->accessScopeService->canManageGlobalAccess($request->user())) {
            $this->accessScopeService->assertEmployeeAssignment(
                $request->user(),
                $employee,
                $data['jabatan_id'] ?? null
            );
        }

        $employee = $this->employeeService->update($id, $data);

        // SYNC TEACHINGS jika dikirimkan dalam request (relasional atau via metadata.teachings).
        // Default behavior: jika teachings tidak dikirimkan atau dikirim sebagai array kosong tanpa instruksi
        // eksplisit clear_teachings, preserve existing assignment (jangan hapus).
        $hasTeachingsInRequest = $request->has('teachings') || $request->has('metadata.teachings');
        if ($hasTeachingsInRequest) {
            $teachings = $request->get('teachings') ?? data_get($data, 'metadata.teachings');
            if (is_array($teachings) && ! empty($teachings)) {
                $this->employeeService->assignTeaching($id, $teachings);
                $employee->load([
                    'unit:id,name,code',
                    'position:id,name,code,level_jabatan',
                    'teachings.subject:id,name,nama_mapel,kode_mapel',
                    'teachings.classroom:id,name',
                ]);
            } elseif (is_array($teachings) && empty($teachings) && $request->boolean('clear_teachings')) {
                // Hanya hapus jika klien secara eksplisit mengirimkan parameter clear_teachings: true
                $this->employeeService->assignTeaching($id, []);
                $employee->load([
                    'unit:id,name,code',
                    'position:id,name,code,level_jabatan',
                    'teachings.subject:id,name,nama_mapel,kode_mapel',
                    'teachings.classroom:id,name',
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data pegawai berhasil diperbarui',
            'data' => $employee,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->accessScopeService->assertGlobalEmployeeMutation($request->user());
        $this->scopedEmployee($request, $id);
        $this->employeeService->delete($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Pegawai berhasil dihapus',
        ]);
    }

    public function positions(Request $request)
    {
        $user = $request->user();
        if ($this->accessScopeService->canManageUnitAccess($user) && ! $this->accessScopeService->canManageGlobalAccess($user)) {
            $query = $this->accessScopeService->accessiblePositions($user);
        } else {
            $query = \App\Models\Position::query();
        }

        if (! $this->accessScopeService->hasGlobalScope($user) || $user->hasAnyRole(['Tata Usaha', 'tata_usaha', 'TU', 'tu', 'staf_tu'])) {
            $query->whereNotIn('level_jabatan', [1, 2, 7])
                  ->where(function ($q) {
                      $q->whereNull('satuan_kerja')
                        ->orWhereNotIn('satuan_kerja', ['Pengurus', 'Bidang Pendidikan']);
                  })
                  ->where(function ($q) {
                      $q->whereNull('scope_akses')
                        ->orWhereNotIn('scope_akses', ['semua_unit', 'lintas_unit']);
                  })
                  ->where(function ($q) {
                      $q->whereRaw('LOWER(name) NOT LIKE ?', ['%yayasan%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%pembina%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%pengawas%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%operator%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%superadmin%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%super admin%'])
                        ->whereRaw('LOWER(name) NOT LIKE ?', ['%administrator%']);
                  });
        }

        $positions = $query->orderBy('level_jabatan')->orderBy('code')->get();

        return response()->json([
            'status' => 'success',
            'data' => $positions,
        ]);
    }

    public function assignTeaching(Request $request, string $id)
    {
        $this->accessScopeService->assertGlobalEmployeeMutation($request->user());
        $this->scopedEmployee($request, $id);
        $request->validate([
            'teachings' => 'required|array',
            'teachings.*.classroom_id' => 'nullable|uuid',
            'teachings.*.subject_id' => 'nullable|uuid',
            'teachings.*.academic_year_id' => 'nullable|uuid',
            'teachings.*.semester_id' => 'nullable|uuid',
            'teachings.*.aktif' => 'nullable|boolean',
            'teachings.*.mapel' => 'nullable|string',
            'teachings.*.kelas' => 'nullable|string',
            'teachings.*.tahun' => 'nullable|string',
            'teachings.*.semester' => 'nullable|string',
            'teachings.*.metadata' => 'nullable|array',
        ]);

        $res = $this->employeeService->assignTeaching($id, $request->get('teachings'));
        $employee = Employee::with([
            'teachings.subject:id,name,nama_mapel,kode_mapel',
            'teachings.classroom:id,name',
        ])->find($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan mengajar berhasil diperbarui',
            'data' => $res,
            'employee' => $employee,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(['search', 'unit_id', 'jabatan_id', 'status_pegawai', 'status', 'jenis_kelamin']);
        $isGlobalUser = $this->accessScopeService->hasGlobalScope($request->user());
        if (! $isGlobalUser && ! empty($filters['unit_id'])) {
            $this->accessScopeService->assertEducationUnitAccess($request->user(), $filters['unit_id']);
        }
        $employees = $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->with(['unit', 'position'])
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($inner) use ($search) {
                    $inner->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('niy', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['unit_id']), fn ($q) => $q->where('unit_id', $filters['unit_id']))
            ->when(! empty($filters['jabatan_id']), fn ($q) => $q->where('jabatan_id', $filters['jabatan_id']))
            ->when(! empty($filters['status_pegawai']), fn ($q) => $q->where('status_pegawai', $filters['status_pegawai']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $filename = 'data_pegawai_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new EmployeeExport($employees), $filename, $excelFormat);
        }

        $rows = $employees->map(function ($emp, $idx) {
            return [
                'no' => $idx + 1,
                'niy' => $emp->niy ?? '-',
                'nik' => $emp->nik ?? '-',
                'nama_lengkap' => $emp->nama_lengkap,
                'jenis_kelamin' => $emp->jenis_kelamin === 'L' ? 'Laki-Laki' : 'Perempuan',
                'unit_pendidikan' => $emp->unit?->name ?? '-',
                'jabatan' => $emp->position?->name ?? '-',
                'status_pegawai' => $emp->status_pegawai ?? '-',
                'no_hp' => $emp->no_hp ?? '-',
                'email' => $emp->email ?? '-',
                'alamat' => $emp->alamat ?? '-',
                'tanggal_masuk' => $emp->tanggal_masuk ?? '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data pegawai berhasil diexport.',
            'data' => $rows,
        ]);
    }

    public function import(Request $request)
    {
        $this->accessScopeService->assertGlobalEmployeeMutation($request->user());

        $rows = $request->input('data', []);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
            $tmpDir = storage_path('app/imports');
            if (! is_dir($tmpDir)) {
                @mkdir($tmpDir, 0755, true);
            }
            $tmpName = 'import_emp_' . uniqid() . '.' . $ext;
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
                            if (in_array($normHeader, ['niy', 'nomorindukyayasan', 'nip', 'noinduk', 'idpegawai', 'nopegawai', 'nomorinduk', 'nomor', 'nikpegawai', 'kodepegawai'])) {
                                $item['niy'] = $val;
                            } elseif (in_array($normHeader, ['nik', 'nomorindukkependudukan', 'ktp', 'noktp', 'nomorktp'])) {
                                $item['nik'] = $val;
                            } elseif (in_array($normHeader, ['namalengkap', 'nama', 'fullname', 'name', 'namapegawai', 'namaguru', 'namakaryawan', 'namapendidik', 'pegawai', 'guru', 'namatenagapendidik', 'namastaf', 'namanama'])) {
                                $item['nama_lengkap'] = $val;
                            } elseif (in_array($normHeader, ['jeniskelamin', 'jk', 'gender', 'kelamin', 'sex'])) {
                                $item['jenis_kelamin'] = $val;
                            } elseif (in_array($normHeader, ['gelardepan', 'gelarawal', 'titlefront', 'prefix'])) {
                                $item['gelar_depan'] = $val;
                            } elseif (in_array($normHeader, ['gelarbelakang', 'gelarakhir', 'titleback', 'suffix'])) {
                                $item['gelar_belakang'] = $val;
                            } elseif (in_array($normHeader, ['jabatan', 'jabatanid', 'position', 'posisi', 'namajabatan', 'tugas', 'role', 'pekerjaan'])) {
                                $item['jabatan'] = $val;
                            } elseif (in_array($normHeader, ['unitkerja', 'unit', 'unitid', 'educationunit', 'namasatuan', 'unitpendidikan', 'satuanpendidikan', 'sekolah', 'cabang', 'jenjang'])) {
                                $item['unit'] = $val;
                            } elseif (in_array($normHeader, ['statuspegawai', 'employmentstatus', 'statuskepegawaian', 'jeniskepegawaian', 'tipepegawai'])) {
                                $item['status_pegawai'] = $val;
                            } elseif (in_array($normHeader, ['statuskeaktifan', 'status', 'isactive', 'keaktifan'])) {
                                $item['status'] = $val;
                            } elseif (in_array($normHeader, ['nohp', 'nomorhp', 'phone', 'telepon', 'telp', 'handphone', 'wa', 'whatsapp'])) {
                                $item['no_hp'] = $val;
                            } elseif (in_array($normHeader, ['email', 'surel', 'mail'])) {
                                $item['email'] = $val;
                            } elseif (in_array($normHeader, ['alamat', 'address', 'domisili', 'tempattinggal'])) {
                                $item['alamat'] = $val;
                            }
                        }

                        // Fallback jika header nama tidak terdeteksi tapi ada kolom teks nama
                        if (empty($item['nama_lengkap'])) {
                            foreach ($rawRow as $cIdx => $cVal) {
                                $cValStr = trim((string)$cVal);
                                if (!empty($cValStr) && strlen($cValStr) >= 3 && !is_numeric($cValStr) && preg_match('/[a-zA-Z]/', $cValStr)) {
                                    $item['nama_lengkap'] = $cValStr;
                                    break;
                                }
                            }
                        }

                        if (!empty($item['nama_lengkap']) || !empty($item['niy'])) {
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
                'message' => 'Payload data impor pegawai tidak boleh kosong atau format berkas tidak sesuai.',
            ], 422);
        }

        $berhasil = 0;
        $diperbarui = 0;
        $gagal = 0;
        $errors = [];

        // Preload Units and Positions for fast lookup without N+1 queries
        $allUnits = EducationUnit::query()->get(['id', 'name', 'code', 'level']);
        $allPositions = Position::query()->get(['id', 'name', 'code']);

        $emp = Employee::where('user_id', $request->user()->id)->first();
        $fallbackUnitId = $emp?->unit_id ?? data_get($request->user()->metadata, 'education_unit_id') ?? data_get($request->user()->metadata, 'unit_id');

        $resolveUnitId = function (?string $rawUnit) use ($allUnits, $fallbackUnitId): ?string {
            if (empty($rawUnit)) {
                return $fallbackUnitId;
            }
            $cleanRaw = strtolower(trim($rawUnit));
            $strippedRaw = preg_replace('/[^a-z0-9]/', '', $cleanRaw);

            // 1. Exact match by name or code
            foreach ($allUnits as $u) {
                if (strtolower(trim($u->name)) === $cleanRaw || strtolower(trim($u->code)) === $cleanRaw) {
                    return $u->id;
                }
            }

            // 2. Stripped match
            foreach ($allUnits as $u) {
                $strippedName = preg_replace('/[^a-z0-9]/', '', strtolower($u->name));
                $strippedCode = preg_replace('/[^a-z0-9]/', '', strtolower($u->code));
                if ($strippedRaw === $strippedName || $strippedRaw === $strippedCode) {
                    return $u->id;
                }
            }

            // 3. Keyword / Level match (e.g. "SD IT", "SDIT", "SMP IT", "SMPIT", "SMA IT", "SMAIT", "TK IT", "TKIT", "MIT")
            $levels = [
                'tkit' => 'TKIT', 'sdit' => 'SDIT', 'smpit' => 'SMPIT', 'smait' => 'SMAIT',
                'mit' => 'MIT', 'taud' => 'TAUD', 'ponpes' => 'PONPES',
                'tk' => 'TKIT', 'sd' => 'SDIT', 'smp' => 'SMPIT', 'sma' => 'SMAIT',
            ];
            foreach ($levels as $keyword => $targetLevel) {
                if (str_contains($strippedRaw, $keyword)) {
                    $matched = $allUnits->first(function ($u) use ($targetLevel, $strippedRaw) {
                        if (str_contains($strippedRaw, '2') && (str_contains(strtolower($u->name), '2') || str_contains(strtolower($u->code), '02'))) {
                            return true;
                        }
                        if (str_contains($strippedRaw, '1') && (str_contains(strtolower($u->name), '1') || str_contains(strtolower($u->code), '01'))) {
                            return true;
                        }
                        return strtoupper((string)$u->level) === $targetLevel || str_contains(strtoupper($u->name), $targetLevel) || str_contains(strtoupper($u->code), $targetLevel);
                    });
                    if ($matched) {
                        return $matched->id;
                    }
                }
            }

            // 4. Substring in name
            foreach ($allUnits as $u) {
                if (str_contains(strtolower($u->name), $cleanRaw) || str_contains($cleanRaw, strtolower($u->name))) {
                    return $u->id;
                }
            }

            return $fallbackUnitId;
        };

        $resolvePositionId = function (?string $rawJabatan) use ($allPositions): ?string {
            if (empty($rawJabatan)) {
                return null;
            }
            $cleanRaw = strtolower(trim($rawJabatan));
            $strippedRaw = preg_replace('/[^a-z0-9]/', '', $cleanRaw);

            // 1. Exact match
            foreach ($allPositions as $p) {
                if (strtolower(trim($p->name)) === $cleanRaw || strtolower(trim($p->code ?? '')) === $cleanRaw) {
                    return $p->id;
                }
            }

            // 2. Stripped match
            foreach ($allPositions as $p) {
                $strippedName = preg_replace('/[^a-z0-9]/', '', strtolower($p->name));
                if ($strippedRaw === $strippedName) {
                    return $p->id;
                }
            }

            // 3. Keyword match
            if (str_contains($cleanRaw, 'kepala') || str_contains($cleanRaw, 'kepsek')) {
                $found = $allPositions->first(fn($p) => str_contains(strtolower($p->name), 'kepala'));
                if ($found) return $found->id;
            }
            if (str_contains($cleanRaw, 'guru')) {
                $found = $allPositions->first(fn($p) => str_contains(strtolower($p->name), 'guru'));
                if ($found) return $found->id;
            }
            if (str_contains($cleanRaw, 'tu') || str_contains($cleanRaw, 'tata usaha') || str_contains($cleanRaw, 'admin') || str_contains($cleanRaw, 'staf')) {
                $found = $allPositions->first(fn($p) => str_contains(strtolower($p->name), 'tata usaha') || str_contains(strtolower($p->name), 'staf'));
                if ($found) return $found->id;
            }

            return null;
        };

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $nama = trim($row['nama_lengkap'] ?? $row['nama'] ?? '');
            $niy = trim($row['niy'] ?? '');
            $nik = trim($row['nik'] ?? '');
            $email = trim($row['email'] ?? '');

            if (empty($nama)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Nama lengkap pegawai wajib diisi.";
                continue;
            }

            $nama = preg_replace('/\s+/', ' ', $nama);
            if (! empty($niy)) {
                $niy = preg_replace('/\s+/', ' ', $niy);
            }

            // Resolve unit_id and jabatan_id
            $unitId = $row['unit_id'] ?? $resolveUnitId($row['unit'] ?? null);
            $jabatanId = $row['jabatan_id'] ?? $resolvePositionId($row['jabatan'] ?? null);

            // Check if existing employee by NIY, NIK, or Email (for upsert)
            $existing = null;
            if (! empty($niy)) {
                $existing = Employee::query()->where('niy', $niy)->first();
            }
            if (! $existing && ! empty($nik)) {
                $existing = Employee::query()->where('nik', $nik)->first();
            }
            if (! $existing && ! empty($email)) {
                $existing = Employee::query()->where('email', $email)->first();
            }

            $rawGender = strtoupper(trim((string)($row['jenis_kelamin'] ?? 'L')));
            $gender = in_array($rawGender, ['P', 'PEREMPUAN', 'WANITA', 'FEMALE']) ? 'P' : 'L';

            try {
                if ($existing) {
                    $updatePayload = [
                        'nama_lengkap' => $nama,
                    ];
                    if (!empty($nik)) $updatePayload['nik'] = $nik;
                    if (!empty($unitId)) $updatePayload['unit_id'] = $unitId;
                    if (!empty($jabatanId)) $updatePayload['jabatan_id'] = $jabatanId;
                    if (!empty($row['status_pegawai'])) $updatePayload['status_pegawai'] = $row['status_pegawai'];
                    if (!empty($row['no_hp'])) $updatePayload['no_hp'] = $row['no_hp'];
                    if (!empty($email)) $updatePayload['email'] = $email;
                    if (!empty($row['alamat'])) $updatePayload['alamat'] = $row['alamat'];
                    if (!empty($row['status'])) $updatePayload['status'] = $row['status'];
                    if (!empty($row['jenis_kelamin'])) $updatePayload['jenis_kelamin'] = $gender;
                    if (!empty($row['gelar_depan'])) $updatePayload['gelar_depan'] = $row['gelar_depan'];
                    if (!empty($row['gelar_belakang'])) $updatePayload['gelar_belakang'] = $row['gelar_belakang'];

                    $existing->update($updatePayload);
                    $diperbarui++;
                    $berhasil++;
                } else {
                    $niyToSave = $niy ?: ('NIY-' . date('Ym') . str_pad((string) rand(100, 999), 3, '0', STR_PAD_LEFT));
                    Employee::query()->create([
                        'niy' => $niyToSave,
                        'nik' => $nik ?: null,
                        'nama_lengkap' => $nama,
                        'gelar_depan' => $row['gelar_depan'] ?? null,
                        'gelar_belakang' => $row['gelar_belakang'] ?? null,
                        'jenis_kelamin' => $gender,
                        'unit_id' => $unitId,
                        'jabatan_id' => $jabatanId,
                        'status_pegawai' => $row['status_pegawai'] ?? 'Tetap',
                        'no_hp' => $row['no_hp'] ?? null,
                        'email' => $email ?: null,
                        'alamat' => $row['alamat'] ?? null,
                        'status' => $row['status'] ?? 'Aktif',
                    ]);
                    $berhasil++;
                }
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        $summaryMsg = "Proses impor selesai. Total: " . count($rows) . ", Berhasil: {$berhasil}";
        if ($diperbarui > 0) {
            $summaryMsg .= " ({$diperbarui} data diperbarui)";
        }
        if ($gagal > 0) {
            $summaryMsg .= ", Gagal: {$gagal}";
        }

        return response()->json([
            'status' => 'success',
            'message' => $summaryMsg,
            'data' => [
                'total' => count($rows),
                'berhasil' => $berhasil,
                'diperbarui' => $diperbarui,
                'duplikat' => 0,
                'gagal' => $gagal,
                'errors' => $errors,
            ],
        ]);
    }

    public function template()
    {
        return response()->json([
            'headers' => ['niy', 'nik', 'nama_lengkap', 'jenis_kelamin', 'status_pegawai', 'no_hp', 'email', 'alamat'],
            'sample' => [
                'niy' => 'PEG-2026-001',
                'nik' => '1371012345670001',
                'nama_lengkap' => 'Ustadz Ahmad Farhan, S.Pd',
                'jenis_kelamin' => 'L',
                'status_pegawai' => 'Guru Tetap',
                'no_hp' => '081234567890',
                'email' => 'ahmad.farhan@school.local',
                'alamat' => 'Kota Padang',
            ],
        ]);
    }

    private function scopedEmployee(Request $request, string $id): Employee
    {
        if (! Str::isUuid($id)) {
            abort(404, 'Data pegawai tidak ditemukan');
        }

        return $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->whereKey($id)
            ->firstOrFail();
    }

}
