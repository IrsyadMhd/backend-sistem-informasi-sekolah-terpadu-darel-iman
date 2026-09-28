<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppMenu extends Model
{
    protected $table = 'app_menus';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'module_id',
        'name',
        'path',
        'icon',
        'required_permissions',
        'sort_order',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'required_permissions' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AppModule::class, 'module_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform(Builder $query, ?string $platform = null): Builder
    {
        if (empty($platform) || $platform === 'both') {
            return $query;
        }

        return $query->whereIn('platform', [$platform, 'both']);
    }
}
