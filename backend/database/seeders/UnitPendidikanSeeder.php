<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UnitPendidikanSeeder extends Seeder
{
    /**
     * Run the database seeds for Unit Pendidikan module.
     */
    public function run(): void
    {
        $this->call([
            MasterJenisUnitPendidikanSeeder::class,
            DataDummyUnitPendidikanSeeder::class,
        ]);
    }
}
