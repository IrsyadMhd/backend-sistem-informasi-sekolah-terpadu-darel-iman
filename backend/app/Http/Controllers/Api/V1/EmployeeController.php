<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Services\EmployeeService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    protected EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function dashboard()
    {
        $stats = $this->employeeService->getDashboardStats();
        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['search', 'unit_id', 'jabatan_id', 'status_pegawai', 'status', 'jenis_kelamin']);
        $perPage = (int) $request->get('per_page', 15);

        $result = $this->employeeService->list($filters, $perPage);

        return response()->json($result);
    }

    public function show(string $id)
    {
        $employee = $this->employeeService->getById($id);

        if (!$employee) {
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
        $employee = $this->employeeService->create($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Pegawai berhasil ditambahkan',
            'data' => $employee,
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, string $id)
    {
        $employee = $this->employeeService->update($id, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Data pegawai berhasil diperbarui',
            'data' => $employee,
        ]);
    }

    public function destroy(string $id)
    {
        $this->employeeService->delete($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Pegawai berhasil dihapus',
        ]);
    }

    public function positions()
    {
        $positions = $this->employeeService->getPositions();
        return response()->json([
            'status' => 'success',
            'data' => $positions,
        ]);
    }

    public function assignTeaching(Request $request, string $id)
    {
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
}
