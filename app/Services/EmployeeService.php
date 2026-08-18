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

    public function getDashboardStats(array $filters = [])
    {
        $query = Employee::query();

        if (! empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $query->where('unit_id', $filters['unit_id']);
        } elseif (array_key_exists('allowed_unit_ids', $filters) && is_array($filters['allowed_unit_ids'])) {
            $query->whereIn('unit_id', $filters['allowed_unit_ids']);
        }

        $totalPegawai = (clone $query)->count();
        $totalAktif = (clone $query)->where('status', 'Aktif')->count();

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $totalGuru = (clone $query)->where(function ($q) use ($like) {
            $q->whereHas('teacher')
              ->orWhereHas('teachings')
              ->orWhere('status_pegawai', $like, '%Guru%')
              ->orWhereHas('position', function ($p) use ($like) {
                  $p->where('name', $like, '%Guru%')
                    ->orWhere('name', $like, '%Pendidik%')
                    ->orWhere('name', $like, '%Wali Kelas%');
              });
        })->count();

        $totalTUOperator = (clone $query)->where(function ($q) use ($like) {
            $q->whereHas('position', function ($p) use ($like) {
                $p->where('name', $like, '%Tata Usaha%')
                  ->orWhere('name', $like, '%Operator%')
                  ->orWhere('name', $like, '%Staf%')
                  ->orWhere('name', $like, '%Bendahara%')
                  ->orWhere('name', $like, '%Keamanan%');
            });
        })->count();

        $byUnit = (clone $query)->selectRaw('unit_id, count(*) as count')
            ->with('unit')
            ->groupBy('unit_id')
            ->get();

        $byJabatan = (clone $query)->selectRaw('jabatan_id, count(*) as count')
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
            $data['niy'] = 'NIY-'.date('Ym').str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
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
