<?php

namespace Database\Seeders;

use App\Models\TransportationMode;
use Illuminate\Database\Seeder;

class TransportationModeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modes = [
            ['name' => 'Jalan Kaki', 'code' => 'jalan_kaki', 'order' => 1],
            ['name' => 'Sepeda', 'code' => 'sepeda', 'order' => 2],
            ['name' => 'Sepeda Motor', 'code' => 'sepeda_motor', 'order' => 3],
            ['name' => 'Mobil', 'code' => 'mobil', 'order' => 4],
            ['name' => 'Transportasi Umum', 'code' => 'transportasi_umum', 'order' => 5],
            ['name' => 'Diantar Jemput', 'code' => 'diantar_jemput', 'order' => 6],
        ];

        foreach ($modes as $mode) {
            TransportationMode::updateOrCreate(
                ['code' => $mode['code']],
                [
                    'name' => $mode['name'],
                    'order' => $mode['order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
