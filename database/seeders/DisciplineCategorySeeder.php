<?php

namespace Database\Seeders;

use App\Models\DisciplineCategory;
use Illuminate\Database\Seeder;

class DisciplineCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // --- PELANGGARAN KEDISIPLINAN (VIOLATIONS) ---
            [
                'code' => 'VIO-01',
                'name' => 'Terlambat Masuk Jam Pelajaran',
                'type' => 'violation',
                'level' => 'ringan',
                'point_weight' => -5,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-02',
                'name' => 'Tidak Membawa Perlengkapan Belajar / Buku Paket',
                'type' => 'violation',
                'level' => 'ringan',
                'point_weight' => -5,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-03',
                'name' => 'Seragam / Atribut Tidak Rapi & Tidak Sesuai Syar\'i',
                'type' => 'violation',
                'level' => 'ringan',
                'point_weight' => -5,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-04',
                'name' => 'Mengganggu Ketertiban KBM / Gaduh di Kelas',
                'type' => 'violation',
                'level' => 'ringan',
                'point_weight' => -10,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-05',
                'name' => 'Tidak Mengerjakan Tugas / PR Tepat Waktu',
                'type' => 'violation',
                'level' => 'sedang',
                'point_weight' => -10,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-06',
                'name' => 'Tidur di Jam Pelajaran Tanpa Keterangan Uzur/Sakit',
                'type' => 'violation',
                'level' => 'ringan',
                'point_weight' => -5,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-07',
                'name' => 'Menggunakan Perangkat Elektronik / HP Tanpa Izin Guru',
                'type' => 'violation',
                'level' => 'sedang',
                'point_weight' => -15,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-08',
                'name' => 'Keluar Kelas / Membolos Tanpa Izin Guru Pengampu',
                'type' => 'violation',
                'level' => 'sedang',
                'point_weight' => -20,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-09',
                'name' => 'Berkata Kasar / Tidak Sopan kepada Guru atau Teman',
                'type' => 'violation',
                'level' => 'sedang',
                'point_weight' => -25,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-10',
                'name' => 'Ketidakjujuran Akademik / Menyontek Saat KBM & Ujian',
                'type' => 'violation',
                'level' => 'berat',
                'point_weight' => -35,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-11',
                'name' => 'Bertengkar / Berkelahi di Area Sekolah',
                'type' => 'violation',
                'level' => 'berat',
                'point_weight' => -50,
                'is_active' => true,
            ],
            [
                'code' => 'VIO-12',
                'name' => 'Merusak Fasilitas Kelas / Sekolah',
                'type' => 'violation',
                'level' => 'berat',
                'point_weight' => -50,
                'is_active' => true,
            ],

            // --- APRESIASI & PRESTASI KEDISIPLINAN (ACHIEVEMENTS) ---
            [
                'code' => 'ACH-01',
                'name' => 'Sangat Aktif & Beradab Baik dalam Pembelajaran',
                'type' => 'achievement',
                'level' => 'ringan',
                'point_weight' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'ACH-02',
                'name' => 'Membantu Kebersihan & Kerapian Kelas Tanpa Diminta',
                'type' => 'achievement',
                'level' => 'ringan',
                'point_weight' => 5,
                'is_active' => true,
            ],
            [
                'code' => 'ACH-03',
                'name' => 'Nilai Sempurna dalam Tugas / Proyek Pembelajaran',
                'type' => 'achievement',
                'level' => 'sedang',
                'point_weight' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'ACH-04',
                'name' => 'Juara Kelas / Lomba / Keteladanan Akhlak Islami',
                'type' => 'achievement',
                'level' => 'berat',
                'point_weight' => 25,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $cat) {
            DisciplineCategory::updateOrCreate(
                ['code' => $cat['code']],
                $cat
            );
        }
    }
}
