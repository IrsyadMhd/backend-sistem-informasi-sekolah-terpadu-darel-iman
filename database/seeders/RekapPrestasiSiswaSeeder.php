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

        // ============================================================
        // POOL PRESTASI DIPERKAYA — 5 KATEGORI × banyak variasi
        // Kategori: tahfizh, santri, akademik, olahraga, lomba
        // ============================================================

        $achievementsPool = [

            // ──────────────────────────────────────────────────────
            // KATEGORI 1: TAHFIZH & MTQ (Hafalan & Kaligrafi)
            // jenis_prestasi = 'tahfizh'
            // ──────────────────────────────────────────────────────
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 1 Musabaqah Hifzhil Qur\'an (MHQ) 5 Juz',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 98.50,
                'keterangan' => 'Berhasil menyelesaikan hafalan 5 Juz Mutqin dengan tajwid dan makhorijul huruf sempurna.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'LPTQ Provinsi Sumatera Barat'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Wisuda Tahfizh Al-Qur\'an Kategori 10 Juz',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 95.00,
                'keterangan' => 'Lulus ujian tasmi\' sekali duduk 10 Juz Al-Qur\'an predikat Mumtaz.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'Divisi Tahfizh Pesantren'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 2 Lomba Hifzhil Qur\'an Juz 30 & 29',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 92.00,
                'keterangan' => 'Meraih peringkat kedua pada Pekan Olahraga dan Seni Antar Diniyah/Sekolah.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'Dinas Pendidikan & Kemenag'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 1 Musabaqah Tilawatil Qur\'an (MTQ) Anak',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 97.80,
                'keterangan' => 'Meraih juara umum MTQ tingkat SD/MI kategori Tilawah Murattal dengan irama Nahawand sempurna.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'LPTQ Nasional RI'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 3 Lomba Kaligrafi Al-Qur\'an',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 88.50,
                'keterangan' => 'Menampilkan karya kaligrafi khat Naskhi & Tsuluts Surah Al-Fath ayat 1 dengan komposisi warna emas.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'Dinas Kebudayaan Provinsi'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Wisuda Tahfizh Al-Qur\'an Kategori 3 Juz',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 91.00,
                'keterangan' => 'Menyelesaikan target hafalan 3 Juz (Juz 30, 29, 28) dengan tajwid baik.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'SDIT 1 Dar el-Iman'],
            ],
            [
                'jenis_prestasi' => 'tahfizh',
                'nama_prestasi' => 'Juara 1 Lomba Adzan & Iqamah',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 93.50,
                'keterangan' => 'Suara adzan terbaik dengan makhroj dan intonasi yang sempurna.',
                'data_tambahan' => ['kategori_kanonik' => 'Tahfizh Al-Qur\'an', 'penyelenggara' => 'Kemenag Kab. 50 Kota'],
            ],

            // ──────────────────────────────────────────────────────
            // KATEGORI 2: KARAKTER & AKHLAK (Ibadah & Rapor Sikap)
            // jenis_prestasi = 'santri'
            // ──────────────────────────────────────────────────────
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
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Siswa dengan Rapor Sikap Terbaik Semester Genap',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 97.50,
                'keterangan' => 'Mendapatkan predikat "Sangat Baik" pada seluruh aspek sikap spiritual dan sosial selama 2 semester berturut-turut.',
                'data_tambahan' => ['kategori_kanonik' => 'Karakter & Akhlak', 'predikat' => 'Siswa Berakhlak Mulia'],
            ],
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Penghargaan Disiplin Ibadah Terbaik',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 96.80,
                'keterangan' => 'Tercatat shalat Dhuha & shalat 5 waktu berjamaah di sekolah tanpa absen selama 1 semester penuh.',
                'data_tambahan' => ['kategori_kanonik' => 'Karakter & Akhlak', 'penyelenggara' => 'Wali Kelas & Musyrif'],
            ],
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Siswa Teladan Peduli Lingkungan',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 93.00,
                'keterangan' => 'Konsisten menjaga kebersihan kelas, memimpin gerakan zero waste, dan rajin merawat taman sekolah.',
                'data_tambahan' => ['kategori_kanonik' => 'Karakter & Akhlak', 'predikat' => 'Green Student Award'],
            ],
            [
                'jenis_prestasi' => 'santri',
                'nama_prestasi' => 'Juara 1 Lomba Da\'i Cilik',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 95.20,
                'keterangan' => 'Tampil sebagai da\'i cilik terbaik dengan tema "Indahnya Silaturahmi" di ajang Festival Anak Sholeh.',
                'data_tambahan' => ['kategori_kanonik' => 'Karakter & Akhlak', 'penyelenggara' => 'Forum Anak Sholeh Sumbar'],
            ],

            // ──────────────────────────────────────────────────────
            // KATEGORI 3: AKADEMIK & SAINS (Olimpiade, LKTI, Cerdas Cermat)
            // jenis_prestasi = 'akademik'
            // ──────────────────────────────────────────────────────
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Peringkat 1 Lulusan Terbaik & Rapor Akademik Paralel',
                'tingkat_prestasi' => 'Internal Sekolah',
                'nilai_prestasi' => 99.50,
                'keterangan' => 'Nilai rata-rata rapor tertinggi paralel seluruh mata pelajaran umum dan agamis.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik', 'predikat' => 'Siswa Berprestasi Utama'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 1 Olimpiade Matematika Terpadu & Sains Sekolah',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 98.00,
                'keterangan' => 'Meraih Medali Emas Olimpiade Sains Nasional bidang Matematika Terpadu.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'Pusat Prestasi Nasional'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 2 Olimpiade IPA Tingkat SD/MI',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 94.00,
                'keterangan' => 'Medali Perak pada Kompetisi Sains Madrasah bidang Ilmu Pengetahuan Alam.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'Kemenag Provinsi Sumbar'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 1 Cerdas Cermat PAI Antar Sekolah',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 96.50,
                'keterangan' => 'Tim Cerdas Cermat PAI menjawab 18 dari 20 soal dengan benar di babak final.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'Dinas Pendidikan Kab. 50 Kota'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 3 Lomba Karya Tulis Ilmiah Siswa SD',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 90.00,
                'keterangan' => 'Menyusun karya tulis tentang pemanfaatan limbah organik sekolah menjadi pupuk kompos.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'LIPI Junior Sumbar'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 2 Olimpiade Bahasa Inggris SD/MI',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 95.50,
                'keterangan' => 'Meraih Silver Medal pada National English Olympiad for Primary School.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'NESO Foundation'],
            ],
            [
                'jenis_prestasi' => 'akademik',
                'nama_prestasi' => 'Juara 1 Lomba Matematika Kreatif',
                'tingkat_prestasi' => 'Regional',
                'nilai_prestasi' => 97.00,
                'keterangan' => 'Menyelesaikan soal problem solving level lanjut dengan metode heuristik yang kreatif.',
                'data_tambahan' => ['kategori_kanonik' => 'Akademik & Sains', 'penyelenggara' => 'Math Creative Indonesia'],
            ],

            // ──────────────────────────────────────────────────────
            // KATEGORI 4: PENTAS PAI & OLAHRAGA (Ekstrakurikuler)
            // jenis_prestasi = 'olahraga'
            // ──────────────────────────────────────────────────────
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
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 1 Lomba Pentas Seni PAI & Nasyid',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 96.00,
                'keterangan' => 'Grup nasyid sekolah tampil memukau dengan aransemen lagu islami kontemporer dan koreografi harmonis.',
                'data_tambahan' => ['kategori_kanonik' => 'Pentas PAI', 'penyelenggara' => 'Festival Seni Islami Sumbar'],
            ],
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 2 Lomba Puisi & Drama Islami Siswa SD',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 91.50,
                'keterangan' => 'Penampilan drama pendek bertema "Ukhuwah Islamiyah" dengan akting dan penyampaian pesan moral yang kuat.',
                'data_tambahan' => ['kategori_kanonik' => 'Pentas PAI', 'penyelenggara' => 'Dinas Pendidikan Kab. 50 Kota'],
            ],
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 1 Lomba Renang Gaya Bebas Pelajar',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 94.00,
                'keterangan' => 'Meraih medali emas 50m gaya bebas kategori putra SD/MI dengan catatan waktu terbaik.',
                'data_tambahan' => ['kategori_kanonik' => 'Ekstrakurikuler', 'penyelenggara' => 'KONI Kab. 50 Kota'],
            ],
            [
                'jenis_prestasi' => 'olahraga',
                'nama_prestasi' => 'Juara 3 Lomba Pencak Silat Minangkabau Cup',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 89.00,
                'keterangan' => 'Meraih medali perunggu kategori tanding kelas C putra.',
                'data_tambahan' => ['kategori_kanonik' => 'Ekstrakurikuler', 'penyelenggara' => 'IPSI Sumbar'],
            ],

            // ──────────────────────────────────────────────────────
            // KATEGORI 5: LOMBA PEMBELAJARAN
            // jenis_prestasi = 'lomba'
            // ──────────────────────────────────────────────────────
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
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 1 Lomba Story Telling Bahasa Inggris SD',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 94.50,
                'keterangan' => 'Penampilan storytelling "The Wise Fisherman" dengan pengucapan, ekspresi, dan intonasi yang sempurna.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'British Council Indonesia'],
            ],
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 2 Lomba Robotika & Coding Pelajar SD',
                'tingkat_prestasi' => 'Nasional',
                'nilai_prestasi' => 95.00,
                'keterangan' => 'Tim robotika berhasil menyelesaikan misi line-follower dan obstacle avoidance dengan algoritma efisien.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'Indonesian Robotic Competition'],
            ],
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 1 Lomba Menulis Cerita Pendek Islami',
                'tingkat_prestasi' => 'Kota/Kabupaten',
                'nilai_prestasi' => 92.50,
                'keterangan' => 'Cerpen berjudul "Cahaya di Balik Hujan" terpilih sebagai karya terbaik dari 150 peserta.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'Perpustakaan Daerah'],
            ],
            [
                'jenis_prestasi' => 'lomba',
                'nama_prestasi' => 'Juara 3 Lomba Poster Digital Edukasi Lingkungan',
                'tingkat_prestasi' => 'Provinsi',
                'nilai_prestasi' => 88.00,
                'keterangan' => 'Desain poster digital bertema "Sekolahku Hijau, Bumiku Lestari" dengan tools Canva & Procreate.',
                'data_tambahan' => ['kategori_kanonik' => 'Lomba Pembelajaran', 'penyelenggara' => 'Dinas LHK Sumbar'],
            ],
        ];

        // Distribute achievements across all students
        // Each student gets 2-4 achievements from different categories
        $poolCount = count($achievementsPool);

        foreach ($students as $index => $student) {
            // Each student gets 2-4 random achievements
            $numAchievements = rand(2, 4);
            $usedIndexes = [];

            for ($i = 0; $i < $numAchievements; $i++) {
                // Pick a random achievement, avoid duplicates per student
                $attempts = 0;
                do {
                    $poolIndex = rand(0, $poolCount - 1);
                    $attempts++;
                } while (in_array($poolIndex, $usedIndexes) && $attempts < 20);

                if (in_array($poolIndex, $usedIndexes)) {
                    continue;
                }

                $usedIndexes[] = $poolIndex;
                $achievement = $achievementsPool[$poolIndex];

                $daysAgo = rand(1, 365);

                RekapPrestasiSiswa::updateOrCreate(
                    [
                        'id_siswa' => $student->id,
                        'nama_prestasi' => $achievement['nama_prestasi'],
                    ],
                    [
                        'jenis_prestasi' => $achievement['jenis_prestasi'],
                        'tingkat_prestasi' => $achievement['tingkat_prestasi'],
                        'tanggal_prestasi' => now()->subDays($daysAgo)->format('Y-m-d'),
                        'nilai_prestasi' => $achievement['nilai_prestasi'] + (rand(-20, 10) / 10),
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
}
