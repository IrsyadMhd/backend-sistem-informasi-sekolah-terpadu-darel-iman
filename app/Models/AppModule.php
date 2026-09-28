<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppModule extends Model
{
    protected $table = 'app_modules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'icon',
        'description',
        'sort_order',
        'platform',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menus(): HasMany
    {
        return $this->hasMany(AppMenu::class, 'module_id', 'id')
            ->orderBy('sort_order', 'asc');
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
