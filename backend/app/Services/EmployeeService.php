<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeTeaching;
use App\Models\Position;
use App\Repositories\Contracts\EmployeeRepositoryInterface;

class EmployeeService
{
    protected EmployeeRepositoryInterface $employeeRepository;

    public function __construct(EmployeeRepositoryInterface $employeeRepository)
    {
        $this->employeeRepository = $employeeRepository;
    }

    public function getDashboardStats()
    {
        $totalPegawai = Employee::count();
        $totalAktif = Employee::where('status', 'Aktif')->count();
        $totalGuru = Employee::whereHas('position', function ($q) {
            $q->where('name', 'like', '%Guru%');
        })->count();
        $totalTUOperator = Employee::whereHas('position', function ($q) {
            $q->where('name', 'like', '%Tata Usaha%')
                ->orWhere('name', 'like', '%Operator%');
        })->count();

        $byUnit = Employee::selectRaw('unit_id, count(*) as count')
            ->with('unit')
            ->groupBy('unit_id')
            ->get();

        $byJabatan = Employee::selectRaw('jabatan_id, count(*) as count')
            ->with('position')
            ->groupBy('jabatan_id')
            ->get();

        return [
            'total_pegawai' => $totalPegawai,
            'total_aktif' => $totalAktif,
            'total_guru' => $totalGuru,
            'total_tu_operator' => $totalTUOperator,
            'by_unit' => $byUnit,
            'by_jabatan' => $byJabatan,
        ];
    }

    public function list(array $filters, int $perPage = 15)
    {
        return $this->employeeRepository->paginate($filters, $perPage);
    }

    public function getById(string $id)
    {
        return $this->employeeRepository->findById($id);
    }

    public function create(array $data)
    {
        if (empty($data['niy'])) {
            $data['niy'] = 'NIY-' . date('Ym') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        }

        return $this->employeeRepository->create($data);
    }

    public function update(string $id, array $data)
    {
        return $this->employeeRepository->update($id, $data);
    }

    public function delete(string $id)
    {
        return $this->employeeRepository->delete($id);
    }

    public function assignTeaching(string $employeeId, array $teachingsData)
    {
        EmployeeTeaching::where('employee_id', $employeeId)->delete();

        $created = [];
        foreach ($teachingsData as $item) {
            $created[] = EmployeeTeaching::create([
                'employee_id' => $employeeId,
                'classroom_id' => $item['classroom_id'] ?? null,
                'subject_id' => $item['subject_id'] ?? null,
                'academic_year_id' => $item['academic_year_id'] ?? null,
                'semester_id' => $item['semester_id'] ?? null,
                'aktif' => $item['aktif'] ?? true,
                'metadata' => $item['metadata'] ?? null,
            ]);
        }

        return $created;
    }

    public function getPositions()
    {
        return Position::where('is_active', true)->orderBy('name', 'asc')->get();
    }
}
