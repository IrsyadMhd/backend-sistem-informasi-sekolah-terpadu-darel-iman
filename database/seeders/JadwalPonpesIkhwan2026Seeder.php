<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class JadwalPonpesIkhwan2026Seeder extends Seeder
{
    /**
     * Backward-compatible seeder entry point.
     * Mengarahkan ke seeder resmi terpadu JadwalBoardingTerpaduSeeder
     * yang mencakup seluruh 15 slot harian (Asrama & KBM 10A, 11A, 12A).
     */
    public function run(): void
    {
        $this->call(JadwalBoardingTerpaduSeeder::class);
    }
}
