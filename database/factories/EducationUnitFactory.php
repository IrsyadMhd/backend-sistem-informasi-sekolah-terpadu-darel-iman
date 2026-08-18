<?php

namespace Database\Factories;

use App\Models\EducationUnit;
use App\Models\JenisUnitPendidikan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory untuk Model EducationUnit (Unit Pendidikan).
 * Dibuat sebagai bagian dari SAFE REFACTOR untuk mendukung test yang membutuhkan EducationUnit.
 */
class EducationUnitFactory extends Factory
{
    protected $model = EducationUnit::class;

    public function definition(): array
    {
        $jenjang = $this->faker->randomElement(['TK', 'SD', 'SMP', 'SMA', 'MA', 'MI']);
        $kode = strtoupper($this->faker->unique()->lexify('??-???'));

        return [
            'jenis_unit_id' => null, // Akan di-generate otomatis via EducationUnit::booted()
            'code'          => $kode,
            'name'          => "{$jenjang} " . $this->faker->company(),
            'level'         => $jenjang,
            'description'   => $this->faker->sentence(),
            'is_active'     => true,
            'metadata'      => null,
        ];
    }

    /**
     * State: Unit yang tidak aktif.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * State: Unit dengan jenis yang sudah ada.
     */
    public function withJenisUnit(JenisUnitPendidikan $jenis): static
    {
        return $this->state(['jenis_unit_id' => $jenis->uuid]);
    }
}
