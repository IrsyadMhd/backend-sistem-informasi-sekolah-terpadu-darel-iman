<?php

namespace Database\Seeders;

use App\Models\RekapPrestasiSiswa;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RekapPrestasiSiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Schema::hasTable('rekap_prestasi_siswas') || ! Schema::hasTable('students')) {
            return;
        }

        $penginput = User::where('email', 'superadmin@simsit.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        if (! $penginput) {
            return;
        }

        $sditUnit = \App\Models\EducationUnit::where('code', 'SDIT-01')->first()
            ?? \App\Models\EducationUnit::where('name', 'LIKE', '%SDIT%')->first()
            ?? \App\Models\EducationUnit::first();

        $students = Student::with(['educationUnit', 'kelas'])->get();

        if ($students->isEmpty()) {
            return;
        }

        $achievementsPool = [
            // Kategori Tahfizh
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 1 Musabaqah Hifzhil Qur’an (MHQ) 5 Juz',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 98.50,
                'keterangan' => 'Berhasil menyelesaikan hafalan 5 Juz Mutqin dengan tajwid dan makhorijul huruf sempurna.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur’an', 'penyelenggara' => 'LPTQ Provinsi'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Wisuda Tahfizh Al-Qur’an Kategori 10 Juz',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 95.00,
                'keterangan' => 'Lulus ujian tasmi’ sekali duduk 10 Juz Al-Qur’an predikat Mumtaz.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur’an', 'penyelenggara' => 'Divisi Tahfizh Pesantren'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 2 Lomba Hifzhil Qur’an Juz 30 & 29',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 92.00,
                'keterangan' => 'Meraih peringkat kedua pada Pekan Olahraga dan Seni Antar Diniyah/Sekolah.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur’an', 'penyelenggara' => 'Dinas Pendidikan & Kemenag'],
            ],

            // Kategori Santri Pesantren
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Santri Teladan & Mutabaah Adab Terbaik Asrama',
                'tingkat_prestasi' => 'Internal Pesantren',
                'nilai_prestasi' => 99.00,
                'keterangan' => 'Teladan dalam kedisiplinan ibadah yaumiyah, shalat jamaah di masjid, dan kebersihan asrama.',
                'data_tambahan' => ['kategori_kanonik' => 'Pondok Pesantren', 'predikat' => 'Santri Teladan Utama'],
            ],
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Kelulusan Ujian Hafalan Kitab Imrithy & Hadits Arbain',
                'tingkat_prestasi' => 'Internal Pesantren',
                'nilai_prestasi' => 96.00,
                'keterangan' => 'Lulus munaqosyah hafalan Kitab Matan Imrithy dan 40 Hadits Arbain Nawawiyah.',
                'data_tambahan' => ['kategori_kanonik' => 'Pondok Pesantren', 'penguji' => 'Kyai & Musyrif Asrama'],
            ],
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Juara 1 Lomba Pidato Bahasa Arab & Khutbah Jumat Santri',
                'tingkat_prestasi' => 'Kabupaten',
                'nilai_prestasi' => 94.50,
                'keterangan' => 'Penampilan pidato Bahasa Arab tanpa teks terbaik dalam ajang Safari Muharram Pesantren.',
                'data_tambahan' => ['kategori_kanonik' => 'Pondok Pesantren', 'penyelenggara' => 'FKPP Pesantren'],
            ],

            // Kategori Ekstrakurikuler & Sepakbola
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 1 Turnamen Sepakbola Antar Pesantren & Sekolah',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 100.00,
                'keterangan' => 'Tim Sepakbola Sekolah memenangkan babak final dengan skor 3-1 dan meraih Top Scorer.',
                'data_tambahan' => ['kategori_kanonik' => 'Ekstrakurikuler & Sepakbola', 'posisi' => 'Kapten Tim / Striker'],
            ],
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 2 Kejuaraan Futsal Pelajar Antar Unit Pendidikan',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 90.00,
                'keterangan' => 'Meraih gelar runner-up dan Best Goalkeeper turnamen futsal tahunan.',
                'data_tambahan' => ['kategori_kanonik' => 'Ekstrakurikuler & Sepakbola', 'posisi' => 'Kiper'],
            ],
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 1 Kejuaraan Panahan Tradisional Santri (Archery Cup)',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 97.00,
                'keterangan' => 'Memenangkan medali emas kategori panahan berkuda dan bantalan jarak 20 meter.',
                'data_tambahan' => ['kategori_kanonik' => 'Ekstrakurikuler & Sepakbola', 'penyelenggara' => 'Federasi Panahan Indonesia'],
            ],

            // Kategori Lomba Pendukung Pembelajaran
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 1 Olimpiade Matematika Terpadu & Sains Sekolah',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 98.00,
                'keterangan' => 'Meraih Medali Emas Olimpiade Sains Nasional bidang Matematika Terpadu.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'Pusat Prestasi Nasional'],
            ],
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 2 Lomba Karya Tulis Ilmiah & Science Technology Competition',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 93.00,
                'keterangan' => 'Inovasi alat penyaring air ramah lingkungan berbasis energi surya.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'BRIN & Universitas'],
            ],
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 1 Debate Competition Bahasa Inggris & Arab',
                'tingkat_prestasi' => 'Regional',
                'nilai_prestasi' => 96.50,
                'keterangan' => 'Best Speaker dalam ajang Inter-School English & Arabic Debate Championship.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'Language Learning Center'],
            ],

            // Kategori Akademik Umum
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Peringkat 1 Lulusan Terbaik & Rapor Akademik Paralel',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 99.50,
                'keterangan' => 'Nilai rata-rata rapor tertinggi paralel seluruh mata pelajaran umum dan agamis.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik', 'predikat' => 'Siswa Berprestasi Utama'],
            ],
        ];

        foreach ($students as $index => $student) {
            $poolIndex = $index % count($achievementsPool);
            $achievement = $achievementsPool[$poolIndex];
            $daysAgo = ($index * 4) + rand(1, 5);

            RekapPrestasiSiswa::updateOrCreate(
                [
                    'id_siswa' => $student->id,
                    'nama_prestasi' => $achievement['nama_prestasi'],
                ],
                [
                    'jenis_prestasi' => $achievement['jenis_prestasi'],
                    'tingkat_prestasi' => $achievement['tingkat_prestasi'],
                    'tanggal_prestasi' => now()->subDays($daysAgo)->format('Y-m-d'),
                    'nilai_prestasi' => $achievement['nilai_prestasi'],
                    'keterangan' => $achievement['keterangan'],
                    'id_penginput' => $penginput->id,
                    'data_tambahan' => array_merge($achievement['data_tambahan'], [
                        'unit_name' => $student->educationUnit?->name ?? 'Unit Sekolah',
                        'kelas_name' => $student->kelas?->nama_kelas ?? 'Kelas Utama',
                    ]),
                ]
            );
        }
    }
}
