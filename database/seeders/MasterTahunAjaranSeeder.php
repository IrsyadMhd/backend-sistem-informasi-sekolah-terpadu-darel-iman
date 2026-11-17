<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MasterTahunAjaranSeeder extends Seeder
{
    /**
     * Seed Tahun Ajaran & Semester resmi Yayasan.
     */
    public function run(): void
    {
        // 1. Tahun Ajaran Sebelumnya (Arsip 2025/2026)
        $taPrev = AcademicYear::updateOrCreate(
            ['name' => '2025/2026'],
            [
                'start_date' => '2025-07-01',
                'end_date' => '2026-06-30',
                'is_active' => false,
                'metadata' => [
                    'source' => 'MasterTahunAjaranSeeder',
                    'keterangan' => 'Tahun Ajaran Lalu (Arsip)',
                ],
            ]
        );

        Semester::updateOrCreate(
            ['academic_year_id' => $taPrev->id, 'name' => 'Semester Ganjil'],
            [
                'sequence' => 1,
                'start_date' => '2025-07-01',
                'end_date' => '2025-12-31',
                'is_active' => false,
            ]
        );

        Semester::updateOrCreate(
            ['academic_year_id' => $taPrev->id, 'name' => 'Semester Genap'],
            [
                'sequence' => 2,
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'is_active' => false,
            ]
        );

        // 2. Tahun Ajaran Aktif (2026/2027)
        $taAktif = AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
                'metadata' => [
                    'source' => 'MasterTahunAjaranSeeder',
                    'keterangan' => 'Tahun Ajaran Aktif Berjalan',
                ],
            ]
        );

        Semester::updateOrCreate(
            ['academic_year_id' => $taAktif->id, 'name' => 'Semester Ganjil'],
            [
                'sequence' => 1,
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );

        Semester::updateOrCreate(
            ['academic_year_id' => $taAktif->id, 'name' => 'Semester Genap'],
            [
                'sequence' => 2,
                'start_date' => '2027-01-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
            ]
        );

        echo "Master Tahun Ajaran (2025/2026 & 2026/2027) dan Semester berhasil di-seed.\n";
    }
}

