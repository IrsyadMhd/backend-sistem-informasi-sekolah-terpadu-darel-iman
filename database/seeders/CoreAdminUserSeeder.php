<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Roles exist
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        // 2. Super Admin Account
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@dareliman.sch.id'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make((string) env('DEFAULT_SUPER_ADMIN_PASSWORD', 'SuperAdmin@2026!')),
                'phone' => '081299990001',
                'is_active' => true,
                'metadata' => [
                    'bootstrap_role' => 'Super Admin',
                    'data_scope' => 'global',
                ],
            ]
        );
        $superAdmin->syncRoles([$superAdminRole->name]);

        // 3. Admin Account
        $admin = User::updateOrCreate(
            ['email' => 'admin@dareliman.sch.id'],
            [
                'name' => 'Admin Sistem',
                'password' => Hash::make((string) env('DEFAULT_ADMIN_PASSWORD', 'Admin@2026!')),
                'phone' => '081299991001',
                'is_active' => true,
                'metadata' => [
                    'bootstrap_role' => 'Admin',
                    'data_scope' => 'global',
                ],
            ]
        );
        $admin->syncRoles([$adminRole->name]);
    }
}
