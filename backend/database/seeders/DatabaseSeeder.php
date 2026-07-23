<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DataDummySiswaSeeder::class,
            DataDummyUnitPendidikanSeeder::class,
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
