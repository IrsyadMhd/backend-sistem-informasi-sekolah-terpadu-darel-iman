<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'application_name', 'school_name', 'logo_text', 'logo_path', 'favicon_path',
        'footer_text', 'header_style', 'header_sticky', 'sidebar_style',
        'sidebar_position', 'sidebar_collapsed', 'template', 'sidebar_color',
        'sidebar_accent_color', 'body_color', 'header_color',
    ];

    protected $appends = ['logo_url', 'favicon_url'];

    protected $hidden = ['logo_path', 'favicon_path'];

    protected function casts(): array
    {
        return [
            'header_sticky' => 'boolean',
            'sidebar_collapsed' => 'boolean',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->publicAssetUrl($this->logo_path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->publicAssetUrl($this->favicon_path);
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'footer_text' => 'Jl. Pendidikan No. 1, Kota Padang',
        ]);
    }

    private function publicAssetUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }
}
