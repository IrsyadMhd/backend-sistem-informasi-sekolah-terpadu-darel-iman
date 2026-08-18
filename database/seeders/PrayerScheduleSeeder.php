<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PrayerScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $today = Carbon::today();
        
        // Seed 7 days of prayer schedules for Padang, Sumatera Barat
        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->addDays($i);
            
            DB::table('jadwal_sholat_caches')->updateOrInsert(
                [
                    'provinsi' => 'SUMATERA BARAT',
                    'kabkota_name' => 'KOTA PADANG',
                    'tanggal' => $date->format('Y-m-d'),
                ],
                [
                    'kabkota_id' => '1371',
                    'tanggal_lengkap' => $date->isoFormat('D MMMM YYYY'),
                    'hari' => $date->isoFormat('dddd'),
                    'bulan' => (int) $date->format('m'),
                    'tahun' => (int) $date->format('Y'),
                    'imsak' => '04:52',
                    'subuh' => '05:02',
                    'terbit' => '06:17',
                    'dhuha' => '06:45',
                    'dzuhur' => '12:28',
                    'ashar' => '15:51',
                    'maghrib' => '18:32',
                    'isya' => '19:44',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
