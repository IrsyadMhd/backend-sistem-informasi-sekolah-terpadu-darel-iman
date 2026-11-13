<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'level',
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

    protected $casts = [
        'level' => 'integer',
        'is_read_only' => 'boolean',
        'can_switch_unit' => 'boolean',
        'is_above_tu' => 'boolean',
        'can_manage_global_access' => 'boolean',
        'is_system_protected' => 'boolean',
        'use_custom_menu_access' => 'boolean',
    ];

    /**
     * A role belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        $guard = $this->attributes['guard_name'] ?? config('auth.defaults.guard') ?? 'web';
        $model = \Spatie\Permission\Guard::getModelForGuard($guard) ?? \App\Models\User::class;

        return $this->morphedByMany(
            $model,
            'model',
            config('permission.table_names.model_has_roles', 'model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key', 'model_id')
        );
    }

    public function menuAccesses(): HasMany
    {
        return $this->hasMany(RoleMenuAccess::class, 'role_id');
    }

    public function allowedMenus(): BelongsToMany
    {
        return $this->belongsToMany(AppMenu::class, 'role_menu_access', 'role_id', 'app_menu_id')
            ->withPivot('access_mode')
            ->wherePivot('access_mode', 'allow');
    }
}
