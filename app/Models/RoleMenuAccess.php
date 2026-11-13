<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleMenuAccess extends Model
{
    protected $table = 'role_menu_access';

    protected $fillable = [
        'role_id',
        'app_menu_id',
        'access_mode',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(AppMenu::class, 'app_menu_id', 'id');
    }
}

