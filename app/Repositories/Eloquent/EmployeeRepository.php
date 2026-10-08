<?php

namespace App\Repositories\Eloquent;

use App\Models\Employee;
use App\Repositories\Contracts\EmployeeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::query()->with([
            'unit:id,name,code',
            'position:id,name,code,level_jabatan',
            'division:id,name',
            'user:id,name,email',
            'teacher:id,employee_id',
            'teachings.subject:id,name,nama_mapel,kode_mapel',
            'teachings.classroom:id,name',
        ]);

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
            $query->where(function ($q) use ($filters) {
                $q->whereIn('unit_id', $filters['allowed_unit_ids']);
                if (! empty($filters['include_null_unit'])) {
                    $q->orWhereNull('unit_id');
                }
            });
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

        if (! empty($filters['exclude_restricted_roles'])) {
            $query->whereDoesntHave('position', function ($q) {
                $q->whereIn('level_jabatan', [1, 2, 7])
                    ->orWhere('satuan_kerja', 'Pengurus')
                    ->orWhere('satuan_kerja', 'Bidang Pendidikan')
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%yayasan%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%pembina%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%pengawas%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%bidang pendidikan%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%divisi pendidikan%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%operator%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%superadmin%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%super admin%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%administrator%']);
            });
            $query->whereDoesntHave('user.roles', function ($q) {
                $q->whereIn('name', [
                    'Super Admin',
                    'super_admin',
                    'superadmin',
                    'Admin',
                    'admin',
                    'administrator',
                    'Operator',
                    'operator',
                    'operator_sekolah',
                    'Pengurus Yayasan',
                    'pengurus_yayasan',
                    'Yayasan',
                    'yayasan',
                    'Ketua Yayasan',
                    'ketua_yayasan',
                    'Sekretaris Yayasan',
                    'sekretaris_yayasan',
                    'Bendahara Yayasan',
                    'bendahara_yayasan',
                    'Divisi Pendidikan',
                    'divisi_pendidikan',
                    'Kepala Bidang Pendidikan',
                    'kepala_bidang_pendidikan',
                ]);
            });
            $query->whereDoesntHave('role', function ($q) {
                $q->whereIn('name', [
                    'Super Admin',
                    'super_admin',
                    'superadmin',
                    'Admin',
                    'admin',
                    'administrator',
                    'Operator',
                    'operator',
                    'operator_sekolah',
                    'Pengurus Yayasan',
                    'pengurus_yayasan',
                    'Yayasan',
                    'yayasan',
                    'Ketua Yayasan',
                    'ketua_yayasan',
                    'Sekretaris Yayasan',
                    'sekretaris_yayasan',
                    'Bendahara Yayasan',
                    'bendahara_yayasan',
                    'Divisi Pendidikan',
                    'divisi_pendidikan',
                    'Kepala Bidang Pendidikan',
                    'kepala_bidang_pendidikan',
                ]);
            });
            $query->where(function ($q) {
                $q->whereNull('nama_lengkap')
                  ->orWhere(function ($sq) {
                      $sq->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%yayasan%'])
                         ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%divisi pendidikan%'])
                         ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%bidang pendidikan%'])
                         ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%superadmin%'])
                         ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%super admin%'])
                         ->whereRaw('LOWER(nama_lengkap) NOT LIKE ?', ['%operator%']);
                  });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc');
        if (! in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        if ($sortBy === 'nama_lengkap') {
            $query->orderBy('nama_lengkap', $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('nama_lengkap', 'asc');
        }

        return $query->paginate($perPage);
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
