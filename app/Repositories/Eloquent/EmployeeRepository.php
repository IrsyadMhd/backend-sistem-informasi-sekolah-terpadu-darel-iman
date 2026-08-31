<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::query()->with(['unit:id,name,code', 'position:id,name,code,level_jabatan', 'division:id,name', 'user:id,name,email']);

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('niy', 'like', $search)
                    ->orWhere('nik', 'like', $search)
                    ->orWhere('nama_lengkap', 'like', $search)
                    ->orWhere('nama_panggilan', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('no_hp', 'like', $search);
            });
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (array_key_exists('allowed_unit_ids', $filters)) {
            $query->whereIn('unit_id', $filters['allowed_unit_ids']);
        }

        if (! empty($filters['jabatan_id'])) {
            $query->where('jabatan_id', $filters['jabatan_id']);
        }

        if (! empty($filters['status_pegawai'])) {
            $query->where('status_pegawai', $filters['status_pegawai']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['jenis_kelamin'])) {
            $query->where('jenis_kelamin', $filters['jenis_kelamin']);
        }

        return $query->orderBy('nama_lengkap', 'asc')->paginate($perPage);
    }

    public function findById(string $id): ?Employee
    {
        return Employee::with(['unit', 'position', 'user', 'role', 'teachings.subject', 'teachings.classroom', 'schedules.subject', 'schedules.kelas'])->find($id);
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(string $id, array $data): Employee
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);

        return $employee->fresh(['unit', 'position', 'user', 'role', 'teachings.subject', 'teachings.classroom']);
    }

    public function delete(string $id): bool
    {
        $employee = Employee::findOrFail($id);

        return $employee->delete();
    }
}
