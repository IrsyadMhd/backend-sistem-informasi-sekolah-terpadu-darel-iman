<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['id' => 1],
            [
                'application_name' => 'Sistem Manajemen Sekolah Terpadu',
                'school_name' => 'YAYASAN DAR EL - IMAN',
                'logo_text' => 'YDE',
                'logo_path' => '/assets/images/logo.png',
                'favicon_path' => '/favicon.ico',
                'footer_text' => '© ' . date('Y') . ' Yayasan Dar El-Iman Padang. All rights reserved.',
                'header_style' => 'light',
                'header_sticky' => true,
                'sidebar_style' => 'gradient',
                'sidebar_position' => 'left',
                'sidebar_collapsed' => false,
                'template' => 'modern',
                'sidebar_color' => '#0E5C44',
                'sidebar_accent_color' => '#3FBF75',
                'body_color' => '#F7F9FC',
                'header_color' => '#FFFFFF',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
