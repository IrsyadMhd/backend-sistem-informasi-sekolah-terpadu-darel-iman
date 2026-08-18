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

        $isGlobalUser = $request->user()->hasAnyRole(['Super Admin', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Bidang Pendidikan']);

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

        $isGlobalUser = $request->user()->hasAnyRole(['Super Admin', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Kepala Bidang Pendidikan']);

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
        $data = $request->validated();
        $this->assertUnitScope($request, $data['unit_id'] ?? null);
        $this->assertPositionScope($request, $data['jabatan_id'] ?? null);
        $employee = $this->employeeService->create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Pegawai berhasil ditambahkan',
            'data' => $employee,
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, string $id)
    {
        $this->scopedEmployee($request, $id);
        $data = $request->validated();
        $this->assertUnitScope($request, $data['unit_id'] ?? null);
        $this->assertPositionScope($request, $data['jabatan_id'] ?? null);
        $employee = $this->employeeService->update($id, $data);

        return response()->json([
            'status' => 'success',
            'message' => 'Data pegawai berhasil diperbarui',
            'data' => $employee,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
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
        $isFoundationUser = $user && $user->hasAnyRole([
            'Super Admin', 'super_admin',
            'Ketua Yayasan', 'ketua_yayasan',
            'Pengurus Yayasan', 'pengurus_yayasan',
            'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
            'Kepala Bidang Pendidikan', 'divisi_pendidikan',
        ]);

        $query = \App\Models\Position::query();

        if (! $isFoundationUser) {
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

    public function import(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Proses import data pegawai berhasil dilakukan',
        ]);
    }

    public function export(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Data pegawai berhasil diexport',
        ]);
    }

    private function scopedEmployee(Request $request, string $id): Employee
    {
        return $this->accessScopeService
            ->accessibleEmployees($request->user())
            ->whereKey($id)
            ->firstOrFail();
    }

    private function assertUnitScope(Request $request, ?string $unitId): void
    {
        if (! $unitId) {
            return;
        }

        abort_unless(
            $this->accessScopeService->accessibleEducationUnits($request->user())->whereKey($unitId)->exists(),
            403,
            'Unit pegawai berada di luar cakupan akun.'
        );
    }

    private function assertPositionScope(Request $request, ?string $positionId): void
    {
        if (! $positionId) {
            return;
        }

        $user = $request->user();
        $isFoundationUser = $user && $user->hasAnyRole([
            'Super Admin', 'super_admin',
            'Ketua Yayasan', 'ketua_yayasan',
            'Pengurus Yayasan', 'pengurus_yayasan',
            'Sekretaris Yayasan', 'sekretaris_yayasan',
            'Bendahara Yayasan', 'bendahara_yayasan',
            'Kepala Bidang Pendidikan', 'divisi_pendidikan',
        ]);

        if (! $isFoundationUser) {
            $pos = \App\Models\Position::find($positionId);
            if ($pos && in_array((int) $pos->level_jabatan, [1, 2], true)) {
                abort(403, 'Kepala Sekolah tidak memiliki hak akses untuk menentukan atau mengubah posisi Pengurus Yayasan dan Divisi Pendidikan.');
            }
        }
    }
}
