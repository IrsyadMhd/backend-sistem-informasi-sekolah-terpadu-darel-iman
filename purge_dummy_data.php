<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

echo "=== Starting Complete Dummy Data Purge ===" . PHP_EOL;

// Preserved tables - DO NOT TRUNCATE
$preservedTables = [
    'migrations',
    'roles',
    'permissions',
    'role_has_permissions',
    'model_has_roles',        // Will be cleaned selectively for non-admin users
    'model_has_permissions',  // Will be cleaned selectively
    'users',                 // Will be cleaned selectively to keep superadmin & admin
    'site_settings',
    'school_settings',
    'student_card_settings',
    'mobile_app_configs',
    'app_menus',
    'app_modules',
    'quran_surahs',
    'doas',
    'indonesia_regions',
    'cache',
    'cache_locks',
    'failed_jobs',
    'job_batches',
    'jobs',
    'password_reset_tokens',
];

// Get all public tables in PostgreSQL
$allTablesResult = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
$allTables = array_map(fn($row) => $row->tablename, $allTablesResult);

// Identify tables to truncate
$tablesToTruncate = array_diff($allTables, $preservedTables);

DB::beginTransaction();

try {
    // Disable foreign key constraints during purge
    DB::statement("SET session_replication_role = 'replica';");

    $truncatedCount = 0;
    foreach ($tablesToTruncate as $table) {
        $countBefore = DB::table($table)->count();
        if ($countBefore > 0) {
            echo "Truncating {$table} ({$countBefore} records)..." . PHP_EOL;
            DB::statement("TRUNCATE TABLE \"{$table}\" CASCADE;");
            $truncatedCount++;
        }
    }
    echo "Truncated {$truncatedCount} tables." . PHP_EOL;

    // 2. Ensure SuperAdmin and Admin accounts are preserved / properly configured
    // Find or recreate Admin
    $adminUser = User::where('email', 'admin@dareliman.sch.id')->first();
    if (!$adminUser) {
        $adminUser = User::create([
            'name' => 'Admin Sistem',
            'email' => 'admin@dareliman.sch.id',
            'password' => Hash::make('Admin@2026!'),
            'phone' => '081299991001',
            'is_active' => true,
        ]);
    } else {
        $adminUser->update([
            'name' => 'Admin Sistem',
            'password' => Hash::make('Admin@2026!'),
            'is_active' => true,
            'metadata' => [
                'bootstrap_role' => 'Admin',
                'data_scope' => 'global',
            ],
        ]);
    }

    // Find or recreate Super Admin
    $superAdminUser = User::where('email', 'superadmin@dareliman.sch.id')->first();
    if (!$superAdminUser) {
        $superAdminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@dareliman.sch.id',
            'password' => Hash::make('SuperAdmin@2026!'),
            'phone' => '081299990001',
            'is_active' => true,
        ]);
    } else {
        $superAdminUser->update([
            'name' => 'Super Admin',
            'password' => Hash::make('SuperAdmin@2026!'),
            'is_active' => true,
            'metadata' => [
                'bootstrap_role' => 'Super Admin',
                'data_scope' => 'global',
            ],
        ]);
    }

    $preservedUserIds = [$adminUser->id, $superAdminUser->id];

    // 3. Purge all other users from users table permanently (force delete)
    $deletedUsers = DB::table('users')->whereNotIn('id', $preservedUserIds)->delete();
    echo "Permanently deleted {$deletedUsers} dummy users from users table." . PHP_EOL;

    // 4. Purge orphaned role/permission mappings
    $deletedRoles = DB::table('model_has_roles')
        ->where('model_type', User::class)
        ->whereNotIn('model_id', $preservedUserIds)
        ->delete();
    echo "Deleted {$deletedRoles} orphaned user-role mappings." . PHP_EOL;

    $deletedPermissions = DB::table('model_has_permissions')
        ->where('model_type', User::class)
        ->whereNotIn('model_id', $preservedUserIds)
        ->delete();
    echo "Deleted {$deletedPermissions} orphaned user-permission mappings." . PHP_EOL;

    // 5. Ensure Admin and Super Admin roles are cleanly assigned
    $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    $adminUser->syncRoles([$adminRole->name]);
    $superAdminUser->syncRoles([$superAdminRole->name]);

    // Re-enable foreign key constraints
    DB::statement("SET session_replication_role = 'origin';");

    DB::commit();
    echo "=== Purge Completed Successfully! ===" . PHP_EOL;

    echo PHP_EOL . "Current Active Users:" . PHP_EOL;
    foreach (User::all() as $u) {
        echo "- {$u->name} ({$u->email}) [Roles: " . implode(', ', $u->getRoleNames()->toArray()) . "]" . PHP_EOL;
    }

} catch (\Throwable $e) {
    DB::rollBack();
    DB::statement("SET session_replication_role = 'origin';");
    echo "ERROR during purge: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
