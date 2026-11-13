<?php

use App\Support\RolePolicyDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                if (! Schema::hasColumn('roles', 'description')) {
                    $table->text('description')->nullable()->after('level');
                }
                if (! Schema::hasColumn('roles', 'scope_type')) {
                    $table->string('scope_type', 32)->default('education_unit')->after('description');
                }
                if (! Schema::hasColumn('roles', 'portal_group')) {
                    $table->string('portal_group', 32)->default('staff')->after('scope_type');
                }
                if (! Schema::hasColumn('roles', 'default_route')) {
                    $table->string('default_route', 120)->nullable()->after('portal_group');
                }
                if (! Schema::hasColumn('roles', 'is_read_only')) {
                    $table->boolean('is_read_only')->default(false)->after('default_route');
                }
                if (! Schema::hasColumn('roles', 'can_switch_unit')) {
                    $table->boolean('can_switch_unit')->default(false)->after('is_read_only');
                }
                if (! Schema::hasColumn('roles', 'is_above_tu')) {
                    $table->boolean('is_above_tu')->default(false)->after('can_switch_unit');
                }
                if (! Schema::hasColumn('roles', 'can_manage_global_access')) {
                    $table->boolean('can_manage_global_access')->default(false)->after('is_above_tu');
                }
                if (! Schema::hasColumn('roles', 'is_system_protected')) {
                    $table->boolean('is_system_protected')->default(false)->after('can_manage_global_access');
                }
                if (! Schema::hasColumn('roles', 'use_custom_menu_access')) {
                    $table->boolean('use_custom_menu_access')->default(false)->after('is_system_protected');
                }
            });

            // Backfill canonical dynamic policies for existing roles
            $roles = DB::table('roles')->select(['id', 'name', 'level'])->get();
            foreach ($roles as $role) {
                $defaults = RolePolicyDefaults::forRoleName((string) $role->name, (int) ($role->level ?? 5));
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'description' => $defaults['description'] ?? null,
                        'scope_type' => $defaults['scope_type'],
                        'portal_group' => $defaults['portal_group'],
                        'default_route' => $defaults['default_route'],
                        'is_read_only' => (bool) $defaults['is_read_only'],
                        'can_switch_unit' => (bool) $defaults['can_switch_unit'],
                        'is_above_tu' => (bool) $defaults['is_above_tu'],
                        'can_manage_global_access' => (bool) $defaults['can_manage_global_access'],
                        'is_system_protected' => (bool) $defaults['is_system_protected'],
                    ]);
            }
        }

        if (! Schema::hasTable('role_menu_access')) {
            Schema::create('role_menu_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
                $table->string('app_menu_id');
                $table->string('access_mode', 16)->default('allow'); // 'allow' | 'deny'
                $table->timestamps();

                $table->unique(['role_id', 'app_menu_id']);
                $table->index(['app_menu_id', 'access_mode']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_access');

        if (Schema::hasTable('roles')) {
            Schema::table('roles', function (Blueprint $table) {
                $cols = [
                    'description',
                    'scope_type',
                    'portal_group',
                    'default_route',
                    'is_read_only',
                    'can_switch_unit',
                    'is_above_tu',
                    'can_manage_global_access',
                    'is_system_protected',
                    'use_custom_menu_access',
                ];
                $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('roles', $c)));
                if (! empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};

