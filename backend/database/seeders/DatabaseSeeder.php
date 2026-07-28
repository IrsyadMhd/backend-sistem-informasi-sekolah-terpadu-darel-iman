<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Order specified:
     * 1. Role & Permission
     * 2. Unit
     * 3. Guru
     * 4. Pegawai
     * 5. OrangTua
     * 6. Siswa
     * 7. Mapel
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            MasterJenisUnitPendidikanSeeder::class,
            DataDummyUnitPendidikanSeeder::class,
            TeacherSeeder::class,
            MasterJabatanSeeder::class,
            DataDummyPegawaiSeeder::class,
            ParentSeeder::class,
            DataDummySiswaSeeder::class,
            SubjectSeeder::class,
            ModulSemesterSeeder::class,
        ]);

        $superAdmin = User::query()->firstOrCreate([
            'email' => 'superadmin@school-erp.local',
        ], [
            'name' => 'Super Admin',
            'password' => 'Password123!',
            'is_active' => true,
            'metadata' => [
                'created_by' => 'system_seeder',
            ],
        ]);

        $superAdmin->assignRole('Super Admin');
    }
}
