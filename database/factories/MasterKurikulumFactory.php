<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MasterKurikulum>
 */
class MasterKurikulumFactory extends Factory
{
    protected $model = MasterKurikulum::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unit = EducationUnit::inRandomOrder()->first() ?? EducationUnit::factory()->create();
        $tahun = AcademicYear::inRandomOrder()->first() ?? AcademicYear::factory()->create();
        $semester = Semester::where('academic_year_id', $tahun->id)->first();

        $jenisOptions = ['Nasional', 'Merdeka', 'SIT', 'Lokal', 'Pesantren', 'Lainnya'];
        $jenjangOptions = ['TK', 'SD', 'SMP', 'SMA', 'MA'];

        $jenis = fake()->randomElement($jenisOptions);
        $jenjang = $unit->level ?? fake()->randomElement($jenjangOptions);

        return [
            'kode_kurikulum' => 'KUR-'.strtoupper($jenjang).'-'.fake()->unique()->numberBetween(100, 999),
            'nama_kurikulum' => 'Kurikulum '.$jenis.' '.$jenjang.' '.fake()->word(),
            'jenis_kurikulum' => $jenis,
            'unit_pendidikan_id' => $unit->id,
            'jenjang' => $jenjang,
            'tahun_ajaran_id' => $tahun->id,
            'semester_id' => $semester?->id,
            'tanggal_mulai' => now()->startOfYear()->toDateString(),
            'tanggal_selesai' => now()->endOfYear()->toDateString(),
            'status' => true,
            'deskripsi' => fake()->sentence(10),
        ];
    }
}
