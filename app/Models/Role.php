<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
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
}
