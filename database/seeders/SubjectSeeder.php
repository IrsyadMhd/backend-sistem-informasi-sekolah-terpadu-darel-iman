<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $units = EducationUnit::query()->orderBy('code')->get();
        $kurikulums = MasterKurikulum::all();
        $gurus = Employee::where('status', 'Aktif')->orderBy('id')->get();
        if ($gurus->isEmpty()) {
            $gurus = Employee::all();
        }

        $defaultUnit = $units->first();
        $defaultKurikulum = $kurikulums->first();

        if (! $defaultUnit || ! $defaultKurikulum) {
            return;
        }

        $subjectsData = [
            // ================= SD / SDIT =================
            [
                'kode_mapel' => 'MAPEL-PAI-SD',
                'nama_mapel' => 'Pendidikan Agama Islam (PAI)',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#0E5C44',
                'ikon' => 'BookOpen',
                'deskripsi' => 'Mata pelajaran agama Islam terpadu dengan pembiasaan akhlakul karimah.',
            ],
            [
                'kode_mapel' => 'MAPEL-TAHFIZH-SD',
                'nama_mapel' => 'Tahfizh Al-Qur\'an',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jenjang' => 'SD',
                'jam_pelajaran' => 6,
                'kkm' => 80.00,
                'warna' => '#1E8E5A',
                'ikon' => 'Sparkles',
                'deskripsi' => 'Hafalan Al-Qur\'an Juz 30, 29, dan 28 serta makharijul huruf.',
            ],
            [
                'kode_mapel' => 'MAPEL-BARAB-SD',
                'nama_mapel' => 'Bahasa Arab',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 2,
                'kkm' => 75.00,
                'warna' => '#3FBF75',
                'ikon' => 'Globe',
                'deskripsi' => 'Pengenalan mufrodat dan percakapan bahasa Arab harian.',
            ],
            [
                'kode_mapel' => 'MAPEL-BINDO-SD',
                'nama_mapel' => 'Bahasa Indonesia',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 5,
                'kkm' => 75.00,
                'warna' => '#2563EB',
                'ikon' => 'FileText',
                'deskripsi' => 'Literasi membaca, menulis, dan tata bahasa Indonesia terstruktur.',
            ],
            [
                'kode_mapel' => 'MAPEL-MTK-SD',
                'nama_mapel' => 'Matematika',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 5,
                'kkm' => 70.00,
                'warna' => '#D97706',
                'ikon' => 'Calculator',
                'deskripsi' => 'Matematika bernalar dan penyelesaian masalah kontekstual.',
            ],
            [
                'kode_mapel' => 'MAPEL-IPAS-SD',
                'nama_mapel' => 'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#059669',
                'ikon' => 'Compass',
                'deskripsi' => 'Eksplorasi alam semesta dan sosial kemasyarakatan.',
            ],
            [
                'kode_mapel' => 'MAPEL-PPKN-SD',
                'nama_mapel' => 'Pendidikan Pancasila',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'warna' => '#DC2626',
                'ikon' => 'Shield',
                'deskripsi' => 'Penguatan nilai Pancasila, kewarganegaraan, dan karakter bangsa.',
            ],
            [
                'kode_mapel' => 'MAPEL-PJOK-SD',
                'nama_mapel' => 'PJOK',
                'kelompok_mapel' => 'Kelompok B',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'warna' => '#16A34A',
                'ikon' => 'Activity',
                'deskripsi' => 'Pendidikan jasmani, olahraga, dan pola hidup sehat.',
            ],
            [
                'kode_mapel' => 'MAPEL-SBDP-SD',
                'nama_mapel' => 'Seni Budaya dan Prakarya',
                'kelompok_mapel' => 'Kelompok B',
                'kategori' => 'Wajib',
                'jenjang' => 'SD',
                'jam_pelajaran' => 2,
                'kkm' => 75.00,
                'warna' => '#8B5CF6',
                'ikon' => 'Palette',
                'deskripsi' => 'Pengembangan minat bakat seni rupa, musik, dan prakarya.',
            ],
            [
                'kode_mapel' => 'MAPEL-BING-SD',
                'nama_mapel' => 'Bahasa Inggris',
                'kelompok_mapel' => 'Kelompok B',
                'kategori' => 'Pilihan',
                'jenjang' => 'SD',
                'jam_pelajaran' => 2,
                'kkm' => 75.00,
                'warna' => '#0284C7',
                'ikon' => 'Languages',
                'deskripsi' => 'Pengenalan kosakata dan percakapan dasar bahasa Inggris.',
            ],

            // ================= SMP / SMPIT =================
            [
                'kode_mapel' => 'MAPEL-PAI-SMP',
                'nama_mapel' => 'Pendidikan Agama Islam',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'warna' => '#0E5C44',
                'ikon' => 'BookOpen',
                'deskripsi' => 'Pendidikan agama Islam, aqidah akhlak, dan fiqih ibadah.',
            ],
            [
                'kode_mapel' => 'MAPEL-TAHFIZH-SMP',
                'nama_mapel' => 'Tahfizh & Tajwid Al-Qur\'an',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 6,
                'kkm' => 80.00,
                'warna' => '#1E8E5A',
                'ikon' => 'Sparkles',
                'deskripsi' => 'Target hafalan Al-Qur\'an dan pendalaman kaidah Tajwid.',
            ],
            [
                'kode_mapel' => 'MAPEL-BARAB-SMP',
                'nama_mapel' => 'Bahasa Arab & Nahwu',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'warna' => '#3FBF75',
                'ikon' => 'Globe',
                'deskripsi' => 'Pembelajaran kaidah bahasa Arab dan percakapan kontekstual.',
            ],
            [
                'kode_mapel' => 'MAPEL-BINDO-SMP',
                'nama_mapel' => 'Bahasa Indonesia',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#2563EB',
                'ikon' => 'FileText',
                'deskripsi' => 'Analisis teks, menulis karya ilmiah sederhana, dan apresiasi sastra.',
            ],
            [
                'kode_mapel' => 'MAPEL-MTK-SMP',
                'nama_mapel' => 'Matematika',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 4,
                'kkm' => 70.00,
                'warna' => '#D97706',
                'ikon' => 'Calculator',
                'deskripsi' => 'Aljabar, geometri, statistika, dan pemecahan masalah matematika.',
            ],
            [
                'kode_mapel' => 'MAPEL-IPA-SMP',
                'nama_mapel' => 'Ilmu Pengetahuan Alam (IPA)',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 4,
                'kkm' => 72.00,
                'warna' => '#059669',
                'ikon' => 'Cpu',
                'deskripsi' => 'Integrasi Fisika, Biologi, dan Kimia dasar.',
            ],
            [
                'kode_mapel' => 'MAPEL-IPS-SMP',
                'nama_mapel' => 'Ilmu Pengetahuan Sosial (IPS)',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#7C3AED',
                'ikon' => 'Compass',
                'deskripsi' => 'Sejarah, Geografi, Ekonomi, dan Sosiologi terpadu.',
            ],
            [
                'kode_mapel' => 'MAPEL-BING-SMP',
                'nama_mapel' => 'Bahasa Inggris',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#0284C7',
                'ikon' => 'Languages',
                'deskripsi' => 'Keterampilan menyimak, berbicara, membaca, dan menulis bahasa Inggris.',
            ],
            [
                'kode_mapel' => 'MAPEL-INFORMATIKA-SMP',
                'nama_mapel' => 'Informatika',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 2,
                'kkm' => 75.00,
                'warna' => '#4F46E5',
                'ikon' => 'Monitor',
                'deskripsi' => 'Berpikir komputasional, algoritma dasar, dan pemanfaatan TI.',
            ],

            // ================= SMA / SMAIT =================
            [
                'kode_mapel' => 'MAPEL-PAI-SMA',
                'nama_mapel' => 'Pendidikan Agama Islam & Budi Pekerti',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'warna' => '#0E5C44',
                'ikon' => 'BookOpen',
                'deskripsi' => 'Pendalaman nilai keislaman, fikih muamalah, dan pemikiran Islam.',
            ],
            [
                'kode_mapel' => 'MAPEL-TAHFIZH-SMA',
                'nama_mapel' => 'Tahfizh Al-Qur\'an Lanjutan',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 4,
                'kkm' => 80.00,
                'warna' => '#1E8E5A',
                'ikon' => 'Sparkles',
                'deskripsi' => 'Ziyadah dan Muraja\'ah hafalan Al-Qur\'an target tingkat SMA.',
            ],
            [
                'kode_mapel' => 'MAPEL-BINDO-SMA',
                'nama_mapel' => 'Bahasa Indonesia',
                'kelompok_mapel' => 'Kelompok Utama',
                'kategori' => 'Wajib',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#2563EB',
                'ikon' => 'FileText',
                'deskripsi' => 'Penulisan ilmiah, retorika, dan analisis kritis sastra Indonesia.',
            ],
            [
                'kode_mapel' => 'MAPEL-MTK-SMA',
                'nama_mapel' => 'Matematika Umum',
                'kelompok_mapel' => 'Kelompok Utama',
                'kategori' => 'Wajib',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 4,
                'kkm' => 70.00,
                'warna' => '#D97706',
                'ikon' => 'Calculator',
                'deskripsi' => 'Kalkulus, trigonometri, matrik, dan kalkulasi tingkat lanjut.',
            ],
            [
                'kode_mapel' => 'MAPEL-FIS-SMA',
                'nama_mapel' => 'Fisika',
                'kelompok_mapel' => 'Kelompok Pilihan',
                'kategori' => 'Pilihan',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 5,
                'kkm' => 70.00,
                'warna' => '#EA580C',
                'ikon' => 'Zap',
                'deskripsi' => 'Konsep fisika klasik dan modern serta aplikasi eksperimental.',
            ],
            [
                'kode_mapel' => 'MAPEL-KIM-SMA',
                'nama_mapel' => 'Kimia',
                'kelompok_mapel' => 'Kelompok Pilihan',
                'kategori' => 'Pilihan',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 5,
                'kkm' => 70.00,
                'warna' => '#06B6D4',
                'ikon' => 'FlaskConical',
                'deskripsi' => 'Struktur atom, ikatan kimia, reaksi, dan praktikum laboratorium.',
            ],

            // ================= TK / PAUD =================
            [
                'kode_mapel' => 'MAPEL-AGAMA-TK',
                'nama_mapel' => 'Nilai Agama & Moral',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'TK',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#0E5C44',
                'ikon' => 'Heart',
                'deskripsi' => 'Pengenalan nilai ketuhanan, doa harian, dan akhlak teruji.',
            ],
            [
                'kode_mapel' => 'MAPEL-MOTORIK-TK',
                'nama_mapel' => 'Fisik Motorik & Kesehatan',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'TK',
                'jam_pelajaran' => 4,
                'kkm' => 75.00,
                'warna' => '#16A34A',
                'ikon' => 'Activity',
                'deskripsi' => 'Pengembangan motorik kasar, motorik halus, dan koordinasi fisik.',
            ],
            [
                'kode_mapel' => 'MAPEL-TAHFIZH-TK',
                'nama_mapel' => 'Tahfizh Surah Pendek & Doa',
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jenjang' => 'TK',
                'jam_pelajaran' => 4,
                'kkm' => 80.00,
                'warna' => '#1E8E5A',
                'ikon' => 'Sparkles',
                'deskripsi' => 'Hafalan surah-surah pendek juz Amma dan adab harian.',
            ],
        ];

        $guruCount = $gurus->count();
        $urutanIndex = 1;

        foreach ($subjectsData as $data) {
            $jenjang = $data['jenjang'];

            // Cari unit pendidikan sesuai jenjang, fallback ke unit pertama
            $matchedUnit = $units->first(fn ($u) => str_contains(strtoupper($u->level ?? $u->name), strtoupper($jenjang))) ?? $defaultUnit;

            // Cari kurikulum sesuai jenjang, fallback ke kurikulum pertama
            $matchedKurikulum = $kurikulums->first(fn ($k) => str_contains(strtoupper($k->jenjang ?? $k->kode_kurikulum), strtoupper($jenjang))) ?? $defaultKurikulum;

            // Pilih guru pengampu secara bergantian jika ada
            $assignedGuru = $guruCount > 0 ? $gurus->get(($urutanIndex - 1) % $guruCount) : null;

            Subject::updateOrCreate(
                [
                    'kode_mapel' => $data['kode_mapel'],
                ],
                array_merge($data, [
                    'unit_pendidikan_id' => $matchedUnit->id,
                    'kurikulum_id' => $matchedKurikulum->id,
                    'nama_singkat' => strtoupper(substr($data['nama_mapel'], 0, 8)),
                    'code' => $data['kode_mapel'],
                    'name' => $data['nama_mapel'],
                    'guru_pengampu_id' => $assignedGuru?->id,
                    'tingkat_kelas' => 'All',
                    'bobot_pengetahuan' => 40,
                    'bobot_keterampilan' => 40,
                    'bobot_sikap' => 20,
                    'bobot_nilai' => [
                        'pengetahuan' => 40,
                        'keterampilan' => 40,
                        'sikap' => 20,
                    ],
                    'urutan_tampil' => $urutanIndex++,
                    'status' => true,
                ])
            );
        }
    }
}
