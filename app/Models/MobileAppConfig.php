<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileAppConfig extends Model
{
    protected $fillable = [
        'platform', 'version', 'config', 'is_published', 'published_at', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public static function defaults(): array
    {
        return [
            'theme' => [
                'primary_color' => '#0E5C44',
                'secondary_color' => '#10B981',
                'background_color' => '#F7F9FC',
                'background_gradient_enabled' => true,
                'background_gradient_start' => '#F7FCFA',
                'background_gradient_end' => '#EAF8F2',
                'background_gradient_direction' => 'diagonal',
                'surface_color' => '#FFFFFF',
                'text_color' => '#0F172A',
                'muted_text_color' => '#64748B',
                'font_family' => 'system',
                'font_scale' => 'normal',
                'button_radius' => 14,
                'card_radius' => 18,
            ],
            'branding' => [
                'app_name' => 'Sistem Manajemen Sekolah Terpadu',
                'school_name' => 'Yayasan Dar el-Iman',
                'logo_url' => null,
                'splash_background_color' => '#004B3A',
            ],
            'navigation' => [
                'style' => 'bottom_tabs',
                'show_labels' => true,
                'items' => [
                    ['key' => 'home', 'label' => 'Beranda', 'icon' => 'view-dashboard-outline', 'enabled' => true, 'order' => 1],
                    ['key' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'bell-outline', 'enabled' => true, 'order' => 2],
                    ['key' => 'qr', 'label' => 'QR Code', 'icon' => 'qrcode-scan', 'enabled' => true, 'order' => 3],
                    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'account-circle-outline', 'enabled' => true, 'order' => 4],
                    ['key' => 'more', 'label' => 'Lainnya', 'icon' => 'menu', 'enabled' => true, 'order' => 5],
                ],
            ],
            'home_layout' => [
                'template' => 'dashboard_default',
                'sections' => [
                    ['type' => 'announcements', 'enabled' => true, 'order' => 1],
                    ['type' => 'quick_menu', 'enabled' => true, 'order' => 2],
                    ['type' => 'metrics', 'enabled' => true, 'order' => 3],
                    ['type' => 'schedule', 'enabled' => true, 'order' => 4],
                ],
            ],
            'role_home_layouts' => [
                'super_admin' => static::homeLayout(['announcements', 'quick_menu', 'metrics', 'schedule']),
                'foundation' => static::homeLayout(['metrics', 'announcements', 'schedule', 'quick_menu']),
                'principal' => static::homeLayout(['metrics', 'schedule', 'announcements', 'quick_menu']),
                'teacher' => static::homeLayout(['schedule', 'quick_menu', 'metrics', 'announcements']),
                'parent' => static::homeLayout(['announcements', 'quick_menu', 'schedule', 'metrics']),
                'student' => static::homeLayout(['schedule', 'quick_menu', 'announcements', 'metrics']),
                'staff' => static::homeLayout(['quick_menu', 'metrics', 'announcements', 'schedule']),
            ],
        ];
    }

    private static function homeLayout(array $sections): array
    {
        return [
            'template' => 'dashboard_default',
            'sections' => array_map(
                fn (string $type, int $index) => ['type' => $type, 'enabled' => true, 'order' => $index + 1],
                $sections,
                array_keys($sections),
            ),
        ];
    }

    public static function android(): self
    {
        return static::query()->firstOrCreate(
            ['platform' => 'android'],
            [
                'version' => 1,
                'config' => static::defaults(),
                'is_published' => true,
                'published_at' => now(),
            ],
        );
    }
}
