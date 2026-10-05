<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $perms = ['teacher.student_note.view', 'kesiswaan.catatan_siswa'];
        $tuRoles = DB::table('roles')->whereIn('name', ['Tata Usaha', 'TU', 'tu', 'operator', 'tata_usaha', 'Operator'])->get();

        foreach ($perms as $permName) {
            $perm = DB::table('permissions')->where('name', $permName)->first();
            if (! $perm) {
                DB::table('permissions')->insert([
                    'name' => $permName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $perm = DB::table('permissions')->where('name', $permName)->first();
            }

            if ($perm) {
                foreach ($tuRoles as $role) {
                    $exists = DB::table('role_has_permissions')
                        ->where('permission_id', $perm->id)
                        ->where('role_id', $role->id)
                        ->exists();
                    if (! $exists) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $perm->id,
                            'role_id' => $role->id,
                        ]);
                    }
                }
            }
        }

        try {
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
