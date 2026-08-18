<?php

namespace Database\Factories;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory Master Mata Pelajaran (Subject)
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $unit = EducationUnit::inRandomOrder()->first() ?? EducationUnit::factory()->create();
        $kurikulum = MasterKurikulum::where('unit_pendidikan_id', $unit->id)->first() ?? MasterKurikulum::factory()->create();
        $guru = Employee::inRandomOrder()->first();

        $kelompokList = ['Kelompok A', 'Kelompok B', 'Kekhasan SIT', 'Muatan Lokal'];
        $kategoriList = ['Wajib', 'Pilihan', 'Tahfizh/Diniyah', 'Ekstrakurikuler'];
        $jenjangList = ['TK', 'SD', 'SMP', 'SMA'];

        $kodeMapel = 'MP-'.fake()->unique()->numberBetween(100, 999);
        $namaMapel = fake()->word().' '.fake()->word();

        return [
            'unit_pendidikan_id' => $unit->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_mapel' => $kodeMapel,
            'nama_mapel' => $namaMapel,
            'nama_singkat' => strtoupper(substr($namaMapel, 0, 5)),
            'code' => $kodeMapel,
            'name' => $namaMapel,
            'kelompok_mapel' => fake()->randomElement($kelompokList),
            'kategori' => fake()->randomElement($kategoriList),
            'jenjang' => $unit->level ?? fake()->randomElement($jenjangList),
            'tingkat_kelas' => 'All',
            'jam_pelajaran' => fake()->numberBetween(2, 6),
            'guru_pengampu_id' => $guru?->id,
            'kkm' => 75.00,
            'bobot_pengetahuan' => 40,
            'bobot_keterampilan' => 40,
            'bobot_sikap' => 20,
            'bobot_nilai' => [
                'pengetahuan' => 40,
                'keterampilan' => 40,
                'sikap' => 20,
            ],
            'warna' => '#0E5C44',
            'ikon' => 'BookOpen',
            'urutan_tampil' => fake()->numberBetween(1, 20),
            'status' => true,
            'deskripsi' => fake()->sentence(8),
        ];
    }
}
