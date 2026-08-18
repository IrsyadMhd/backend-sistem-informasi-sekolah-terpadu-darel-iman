<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk Model Kelas / Rombongan Belajar.
 */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        $jenjang = $this->faker->randomElement(['SD', 'SMP', 'SMA']);
        $tingkatMap = [
            'SD' => ['1', '2', '3', '4', '5', '6'],
            'SMP' => ['7', '8', '9'],
            'SMA' => ['10', '11', '12'],
        ];

        $tingkat = $this->faker->randomElement($tingkatMap[$jenjang]);
        $paralel = $this->faker->randomElement(['A', 'B', 'C', 'IPA-1', 'IPS-1']);

        return [
            'unit_pendidikan_id' => EducationUnit::factory(),
            'tahun_ajaran_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
            'jenjang' => $jenjang,
            'tingkat' => $tingkat,
            'kode_kelas' => 'KLS-'.strtoupper($jenjang).'-'.$tingkat.$paralel.'-'.$this->faker->unique()->randomNumber(3),
            'nama_kelas' => 'Kelas '.$tingkat.' '.$paralel,
            'wali_kelas_id' => Employee::factory(),
            'kapasitas' => $this->faker->numberBetween(25, 36),
            'ruangan' => 'Ruang R-'.$this->faker->numberBetween(101, 305),
            'status' => 'Aktif',
        ];
    }
}
