<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use Illuminate\Database\Seeder;

class DataDummyUnitPendidikanSeeder extends Seeder
{
    /**
     * Seed the application's database with dummy education units.
     */
    public function run(): void
    {
        $units = [
            [
                'code' => 'UP-PAUD',
                'name' => 'PAUD IT Al-Falah',
                'level' => 'PAUD',
                'description' => 'Unit Pendidikan Anak Usia Dini.',
                'is_active' => true,
                'metadata' => [
                    'kepala_unit' => 'Siti Aminah, S.Pd',
                    'telepon' => '021-5550101',
                ],
            ],
            [
                'code' => 'UP-SD',
                'name' => 'SD IT Al-Falah',
                'level' => 'SD',
                'description' => 'Unit Sekolah Dasar Islam Terpadu.',
                'is_active' => true,
                'metadata' => [
                    'kepala_unit' => 'Ahmad Ridwan, M.Pd',
                    'telepon' => '021-5550102',
                ],
            ],
            [
                'code' => 'UP-SMP',
                'name' => 'SMP IT Al-Falah',
                'level' => 'SMP',
                'description' => 'Unit Sekolah Menengah Pertama Islam Terpadu.',
                'is_active' => true,
                'metadata' => [
                    'kepala_unit' => 'Nur Hidayah, S.Pd',
                    'telepon' => '021-5550103',
                ],
            ],
            [
                'code' => 'UP-SMA',
                'name' => 'SMA IT Al-Falah',
                'level' => 'SMA',
                'description' => 'Unit Sekolah Menengah Atas Islam Terpadu.',
                'is_active' => false,
                'metadata' => [
                    'kepala_unit' => 'Rahmat Fauzan, M.Pd',
                    'telepon' => '021-5550104',
                ],
            ],
        ];

        foreach ($units as $unit) {
            EducationUnit::query()->updateOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }
}
