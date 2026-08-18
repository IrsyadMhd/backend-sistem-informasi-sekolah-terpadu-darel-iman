<?php

namespace Database\Factories;

use App\Models\JenisUnitPendidikan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JenisUnitPendidikanFactory extends Factory
{
    protected $model = JenisUnitPendidikan::class;

    public function definition(): array
    {
        $kode = strtoupper($this->faker->unique()->lexify('????'));

        return [
            'uuid' => (string) Str::uuid(),
            'kode_jenis' => $kode,
            'nama_jenis' => "Sekolah {$kode}",
            'singkatan' => $kode,
            'jenjang' => $this->faker->randomElement(['PAUD', 'TK', 'SD', 'MI', 'SMP', 'MTs', 'SMA', 'MA', 'Pondok Pesantren', 'Mahad']),
            'warna_badge' => $this->faker->randomElement(['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6']),
            'icon' => $this->faker->randomElement(['School', 'Building', 'Book', 'Mosque', 'Graduation', 'University', 'Children', 'Home']),
            'urutan' => $this->faker->numberBetween(1, 20),
            'keterangan' => $this->faker->sentence(),
            'status' => true,
        ];
    }
}
