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
                'accent_color' => '#3FBF75',
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
                'welcome_text' => 'Selamat datang di SIMSIT Dar el-Iman',
                'login_banner_url' => null,
                'news_gradient_start' => '#FFFFFF',
                'news_gradient_end' => '#E8F5E9',
            ],
            'branding' => [
                'app_name' => 'Sistem Manajemen Sekolah Terpadu',
                'school_name' => 'Yayasan Dar el-Iman',
                'logo_url' => null,
                'logo_header_url' => null,
                'logo_login_url' => null,
                'logo_footer_url' => null,
                'splash_background_color' => '#004B3A',
                'contact' => [
                    'phone' => null,
                    'email' => null,
                    'website' => null,
                    'service_hours' => "Senin – Jum'at: 07.30 – 16.00 WIB",
                ],
            ],
            'navigation' => [
                'style' => 'bottom_tabs',
                'show_labels' => true,
                'items' => [
                    ['key' => 'home', 'label' => 'Beranda', 'icon' => 'view-dashboard-outline', 'enabled' => true, 'order' => 1],
                    ['key' => 'notifications', 'label' => 'Chat Guru', 'icon' => 'chat-processing-outline', 'enabled' => true, 'order' => 2],
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
                'super_admin' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'foundation' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'principal' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'divisi' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'tu' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'teacher' => static::homeLayout(['metrics', 'schedule', 'quick_menu', 'announcements']),
                'musyrif' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'parent' => static::homeLayout(['quick_menu', 'metrics', 'schedule', 'announcements']),
                'student' => static::homeLayout(['quick_menu', 'metrics', 'schedule', 'announcements']),
                'staff' => static::homeLayout(['metrics', 'quick_menu', 'schedule', 'announcements']),
                'alumni' => static::homeLayout(['quick_menu', 'metrics', 'schedule', 'announcements']),
            ],
            'features' => [
                'qr_login' => true,
                'qr_attendance' => true,
                'chat' => true,
                'notifications' => true,
                'tahfizh' => true,
                'mutabaah' => true,
                'cbt' => true,
                'school_info' => true,
            ],
            'system' => [
                'min_app_version' => '1.0.0',
                'latest_app_version' => '1.0.0',
                'maintenance_mode' => false,
                'maintenance_message' => 'Sistem sedang dalam pemeliharaan rutin. Silakan coba beberapa saat lagi.',
                'force_update' => false,
                'update_url' => 'https://play.google.com/store/apps/details?id=id.sch.dareliman.sims.mobile',
            ],
            'onboarding' => [
                'enabled' => true,
                'slides' => [
                    [
                        'title' => 'Sistem Sekolah Terpadu',
                        'desc' => 'Akses informasi akademik, jadwal pembelajaran, dan pengumuman sekolah dalam satu genggaman.',
                        'image_url' => null,
                    ],
                    [
                        'title' => 'Mutaba\'ah & Tahfizh Mandiri',
                        'desc' => 'Pantau amalan yaumiyyah, hafalan Al-Qur\'an, dan capaian adab ananda secara transparan.',
                        'image_url' => null,
                    ],
                    [
                        'title' => 'Presensi & Layanan Digital',
                        'desc' => 'Notifikasi kehadiran kelas & gerbang otomatis terhubung langsung ke gawai orang tua.',
                        'image_url' => null,
                    ],
                ],
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
