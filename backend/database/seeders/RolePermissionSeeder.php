<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Yayasan',
            'Kepala Sekolah',
            'Wakil Kepala Sekolah',
            'Divisi Pendidikan',
            'Tata Usaha',
            'Guru',
            'Wali Kelas',
            'Orang Tua',
            'Siswa',
        ];

        $permissions = [
            'dashboard.view',
            'dashboard.pemantauan.lihat',
            'dashboard.pemantauan.kelola',
            'students.view',
            'students.manage',
            'teachers.view',
            'teachers.manage',
            'classes.view',
            'classes.manage',
            'attendance.view',
            'attendance.manage',
            'tahfizh.view',
            'tahfizh.manage',
            'mutabaah.view',
            'mutabaah.manage',
            'materials.view',
            'materials.manage',
            'assignments.view',
            'assignments.manage',
            'exams.view',
            'exams.manage',
            'alumni.view',
            'alumni.manage',
            'notifications.view',
            'notifications.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        foreach ($roles as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($roleName === 'Super Admin') {
                $role->syncPermissions($permissions);
            }

            if (in_array($roleName, ['Yayasan', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Divisi Pendidikan', 'Guru', 'Wali Kelas'], true)) {
                $role->givePermissionTo([
                    'dashboard.view',
                    'dashboard.pemantauan.lihat',
                    'dashboard.pemantauan.kelola',
                ]);
            }
        }
    }
}
