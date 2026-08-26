<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Services\AccessScopeService;
use App\Services\EmployeeService;
use Illuminate\Http\Request;

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

        if (empty($filters['unit_id']) && ! $isGlobalUser && $userUnitId) {
            $filters['unit_id'] = $userUnitId;
        }

        $filters['allowed_unit_ids'] = $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->select('unit_id')
            ->distinct()
            ->pluck('unit_id')
            ->all();

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

        if (empty($filters['unit_id']) && ! $isGlobalUser && $userUnitId) {
            $filters['unit_id'] = $userUnitId;
        }

        $filters['allowed_unit_ids'] = $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->select('unit_id')
            ->distinct()
            ->pluck('unit_id')
            ->all();
        $perPage = (int) $request->get('per_page', 15);

        $result = $this->employeeService->list($filters, $perPage);

        return response()->json($result);
    }

    public function show(Request $request, string $id)
    {
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

        if (! $this->accessScopeService->hasGlobalScope($user) && ! $this->accessScopeService->canManageUnitAccess($user)) {
            $query->whereNotIn('level_jabatan', [1, 2])
                  ->where(function ($q) {
                      $q->whereNull('scope_akses')
                        ->orWhereNotIn('scope_akses', ['semua_unit', 'lintas_unit']);
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
        ]);

        $res = $this->employeeService->assignTeaching($id, $request->get('teachings'));

        return response()->json([
            'status' => 'success',
            'message' => 'Penugasan mengajar berhasil diperbarui',
            'data' => $res,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(['search', 'unit_id', 'jabatan_id', 'status_pegawai', 'status', 'jenis_kelamin']);
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
        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor pegawai tidak boleh kosong.',
            ], 422);
        }

        $berhasil = 0;
        $gagal = 0;
        $duplikat = 0;
        $errors = [];

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
                if (Employee::query()->where('niy', $niy)->exists()) {
                    $duplikat++;
                    $errors[] = "Baris {$rowNum}: NIY '{$niy}' sudah terdaftar.";
                    continue;
                }
            }

            try {
                Employee::query()->create([
                    'niy' => $niy ?: null,
                    'nik' => $nik ?: null,
                    'nama_lengkap' => $nama,
                    'jenis_kelamin' => in_array(strtoupper($row['jenis_kelamin'] ?? 'L'), ['P', 'PEREMPUAN']) ? 'P' : 'L',
                    'unit_id' => $row['unit_id'] ?? null,
                    'jabatan_id' => $row['jabatan_id'] ?? null,
                    'status_pegawai' => $row['status_pegawai'] ?? 'Tetap',
                    'no_hp' => $row['no_hp'] ?? null,
                    'email' => $email ?: null,
                    'alamat' => $row['alamat'] ?? null,
                    'status' => $row['status'] ?? 'Aktif',
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
        return $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->whereKey($id)
            ->firstOrFail();
    }

}
