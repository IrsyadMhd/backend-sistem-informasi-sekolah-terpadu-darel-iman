<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JadwalSditFullDay2026Seeder extends Seeder
{
    /**
     * Backward-compatible seeder entry point.
     * Mengarahkan ke seeder resmi terpadu JadwalSditFullDayTerpaduSeeder
     * yang mencakup 19 slot harian (bebas guru/pengampu, bebas duplikat).
     */
    public function run(): void
    {
        $this->call(JadwalSditFullDayTerpaduSeeder::class);
    }
}
