<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultRoleUserSeeder extends Seeder
{
    /**
     * Akun bootstrap untuk pengujian setiap role.
     *
     * Password dapat dioverride lewat environment dan wajib diganti setelah
     * instalasi produksi. updateOrCreate membuat seeder aman dijalankan ulang.
     */
    public function run(): void
    {
        $accounts = [
            'Super Admin' => [
                'name' => 'Super Admin',
                'email' => 'superadmin@school-erp.local',
                'password' => env('DEFAULT_SUPER_ADMIN_PASSWORD', 'SuperAdmin@2026!'),
            ],
            'Kepala Sekolah' => [
                'name' => 'Kepala Sekolah',
                'email' => 'kepsek@school-erp.local',
                'password' => env('DEFAULT_KEPSEK_PASSWORD', 'Kepsek@2026!'),
            ],
            'Divisi Pendidikan' => [
                'name' => 'Divisi Pendidikan',
                'email' => 'divisi.pendidikan@school-erp.local',
                'password' => env('DEFAULT_DIVISI_PASSWORD', 'Divisi@2026!'),
            ],
            'Tata Usaha' => [
                'name' => 'Tata Usaha',
                'email' => 'tu@school-erp.local',
                'password' => env('DEFAULT_TU_PASSWORD', 'TU@2026!'),
            ],
            'Guru' => [
                'name' => 'Guru',
                'email' => 'guru@school-erp.local',
                'password' => env('DEFAULT_GURU_PASSWORD', 'Guru@2026!'),
            ],
            'Orang Tua' => [
                'name' => 'Orang Tua',
                'email' => 'orangtua@school-erp.local',
                'password' => env('DEFAULT_ORANG_TUA_PASSWORD', 'OrangTua@2026!'),
            ],
            'Siswa' => [
                'name' => 'Siswa',
                'email' => 'siswa@school-erp.local',
                'password' => env('DEFAULT_SISWA_PASSWORD', 'Siswa@2026!'),
            ],
        ];

        foreach ($accounts as $role => $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'is_active' => true,
                    'metadata' => [
                        'created_by' => 'default_role_user_seeder',
                        'must_change_password' => true,
                        'bootstrap_role' => $role,
                    ],
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
