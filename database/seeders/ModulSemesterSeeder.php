<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ModulSemester;
use App\Models\ModulSemesterDetail;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModulSemesterSeeder extends Seeder
{
    public function run(): void
    {
        $ta = AcademicYear::query()->where('is_active', true)->orderByDesc('start_date')->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $sem = $ta
            ? Semester::query()
                ->where('academic_year_id', $ta->id)
                ->orderByDesc('is_active')
                ->orderBy('sequence')
                ->first()
            : null;

        if (! $ta || ! $sem) {
            $this->command?->warn('Tahun Ajaran atau Semester aktif tidak ditemukan.');
            return;
        }

        $units = EducationUnit::all();
        if ($units->isEmpty()) {
            $this->command?->warn('Unit Pendidikan tidak ditemukan.');
            return;
        }

        // Definisi spesifikasi simulasi kurikulum per unit
        $unitConfigs = [
            'TKIT-01' => [
                'kode_sub' => 'TK1-MORAL',
                'mapel_keywords' => ['Nilai Agama', 'Moral', 'Tahfizh', 'Agama'],
                'nama_modul' => 'Program Semester 1 Pengembangan Karakter Islami & Doa Harian Anak Usia Dini',
                'jenjang' => 'TK',
                'kurikulum' => 'Kurikulum Merdeka PAUD Terpadu',
                'alokasi_jam' => 32,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 2,
                'atp' => 'Membiasakan pengenalan rukun iman, rukun islam, adab sehari-hari, dan doa-doa masnunah sejak dini.',
                'cp' => 'Anak mampu mengenali ciptaan Allah, mempraktikkan adab makan/minum, dan melafalkan doa harian dengan bimbingan.',
                'tujuan' => 'Menumbuhkan fitrah keimanan dan pembiasaan adab mulia pada anak usia dini.',
                'metode' => 'Bercerita, Bermain Peran (Role Play), Praktik Langsung, Lagu Edukatif Islami',
                'model' => 'Sentra Pembelajaran & Eksplorasi Terbimbing',
                'media' => 'Kartu Bergambar (Flashcard), Big Book Kisah Teladan Nabi, Audio Murottal Anak',
                'sumber' => 'Buku Panduan Guru PAUD Terpadu JSIT & Modul Adab Anak Muslim',
                'target_nilai' => 80.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Doa Mau Makan, Doa Bangun Tidur, Doa Kedua Orang Tua, Surah Al-Fatihah, Al-Ikhlas',
                'target_proyek' => 'Buku Tempel (Kliping) Pohon Kebaikan & Adab Harian di Rumah',
                'bobot' => ['tugas' => 30.00, 'quiz' => 20.00, 'projek' => 30.00, 'uts' => 10.00, 'uas' => 10.00],
                'topik_mingguan' => [
                    1 => 'Orientasi Kelas: Pengenalan Kalimat Thayyibah (Basmalah & Hamdalah)',
                    2 => 'Mengenal Allah Maha Pencipta Melalui Ciptaan di Sekitar',
                    3 => 'Adab Makan dan Minum Sesuai Sunnah Rasulullah SAW',
                    4 => 'Praktik Doa Sebelum dan Sesudah Makan secara Berulang',
                    5 => 'Adab Masuk dan Keluar Kamar Mandi (Kaki & Doa)',
                    6 => 'Mengenal Anggota Wudhu Sederhana dengan Nyanyian Edukasi',
                    7 => 'Praktik Bersuci (Wudhu) Bersama Guru Pendamping',
                    8 => 'Evaluasi Perkembangan Tengah Semester (Unjuk Kerja Adab & Doa)',
                    9 => 'Adab Berbakti kepada Ayah dan Ibu di Rumah',
                    10 => 'Menghafal Doa untuk Kedua Orang Tua beserta Makna Singkatnya',
                    11 => 'Adab Berbicara Santun dan Menyayangi Teman Sebaya',
                    12 => 'Kisah Keteladanan Kejujuran Nabi Muhammad SAW Waktu Kecil',
                    13 => 'Praktik Gerakan Shalat Berjamaah Sederhana (Rukuk & Sujud)',
                    14 => 'Mengenal Huruf Hijaiyah Tunggal (Alif sampai Jim)',
                    15 => 'Review & Unjuk Hafalan Doa Harian di Depan Teman',
                    16 => 'Gelar Karya Akhir Semester: Pameran Pohon Kebaikan & Portofolio Anak',
                ],
            ],
            'TKIT-02' => [
                'kode_sub' => 'TK2-TAHFIZH',
                'mapel_keywords' => ['Tahfizh', 'Al-Qur\'an', 'Agama'],
                'nama_modul' => 'Modul Semester 1 Tahfizh Juz 30 Surah Pendek & Makharijul Huruf',
                'jenjang' => 'TK',
                'kurikulum' => 'Kurikulum Merdeka PAUD Terpadu',
                'alokasi_jam' => 32,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 2,
                'atp' => 'Menghafal surah An-Nas sampai Al-Fil dengan pelafalan makhraj huruf yang benar dan berkesinambungan.',
                'cp' => 'Anak mampu menghafal minimal 5 surah pendek dengan tartil dan tajwid dasar anak.',
                'tujuan' => 'Menumbuhkan kecintaan terhadap Al-Qur\'an dan melatih daya ingat hafalan ayat suci.',
                'metode' => 'Metode Talqin, Gerakan Kinestetik Huruf, Pengulangan (Tikrar) Bersama',
                'model' => 'Halaqah Tahfizh Santri Cilik',
                'media' => 'Mushaf Rasm Utsmani Juz \'Amma, Audio Syaikh Misyari Rasyid Alafasy Anak',
                'sumber' => 'Buku Prestasi Tahfizh Balita & Juz \'Amma Terjemah',
                'target_nilai' => 80.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Surah An-Nas, Al-Falaq, Al-Ikhlas, Al-Lahab, An-Nashr, Al-Kafirun',
                'target_proyek' => 'Rekaman Video Santri Melantunkan Surah Pendek Bersama Ayah/Bunda',
                'bobot' => ['tugas' => 25.00, 'quiz' => 25.00, 'projek' => 25.00, 'uts' => 12.50, 'uas' => 12.50],
                'topik_mingguan' => [
                    1 => 'Ta\'aruf Halaqah & Pengenalan Ta\'awwudz dan Basmalah Tartil',
                    2 => 'Makhraj Huruf Halqiyah (Tenggorokan) & Pelafalan Surah An-Nas Ayat 1-3',
                    3 => 'Pelafalan Surah An-Nas Ayat 4-6 & Pengulangan Tikrar Bersama',
                    4 => 'Tasmi\' Mandiri Surah An-Nas Lengkap',
                    5 => 'Makhraj Huruf Lisan & Pelafalan Surah Al-Falaq Ayat 1-3',
                    6 => 'Pelafalan Surah Al-Falaq Ayat 4-5 & Penekanan Huruf Qalqalah',
                    7 => 'Muroja\'ah Surah An-Nas dan Al-Falaq Bergantian',
                    8 => 'Imtihan / Ujian Tahfizh Tengah Semester (Surah An-Nas & Al-Falaq)',
                    9 => 'Menghafal Surah Al-Ikhlas Ayat 1-4 & Makna Keesaan Allah',
                    10 => 'Muroja\'ah Surah Al-Ikhlas dengan Gerakan Tangan Interaktif',
                    11 => 'Menghafal Surah Al-Lahab Ayat 1-3 dengan Bimbingan Talqin',
                    12 => 'Menyelesaikan Surah Al-Lahab Ayat 4-5 & Koreksi Makhraj Kaf dan Qaf',
                    13 => 'Menghafal Surah An-Nashr Ayat 1-3',
                    14 => 'Muroja\'ah Gabungan Surah-Surah Pendek yang Telah Dipelajari',
                    15 => 'Simulasi Tasmi\' di Depan Kelas Menghadapi Ujian Akhir',
                    16 => 'Tasmi\' Akhir Semester 1 Juz \'Amma Anak di Hadapan Ustadzah & Ortu',
                ],
            ],
            'TKIT-03' => [
                'kode_sub' => 'TK3-ADAB',
                'mapel_keywords' => ['Adab', 'Agama', 'Tahfizh'],
                'nama_modul' => 'Program Semester 1 Adab Islami, Shalat Berjamaah & Kemandirian Anak',
                'jenjang' => 'TK',
                'kurikulum' => 'Kurikulum Merdeka PAUD Terpadu',
                'alokasi_jam' => 32,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 2,
                'atp' => 'Mengenalkan tata cara wudhu, gerakan shalat, adab berteman, dan melatih kemandirian merapikan barang sendiri.',
                'cp' => 'Anak mampu mempraktikkan gerakan shalat dengan tertib dan menunjukkan sikap mandiri serta empati.',
                'tujuan' => 'Membentuk santri cilik yang beradab santun, cinta shalat, dan memiliki kemandirian pribadi.',
                'metode' => 'Demonstrasi, Praktik Shalat Harian di Mushalla, Pembiasaan Mandiri',
                'model' => 'Contextual Islamic Learning',
                'media' => 'Sajadah Anak, Papan Ceklis Ibadah Harian, Poster Rukun Shalat',
                'sumber' => 'Buku Adab dan Ibadah Praktis Anak Sholeh',
                'target_nilai' => 80.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Bacaan Ruku, I\'tidal, Sujud, dan Doa Duduk di Antara Dua Sujud',
                'target_proyek' => 'Lembar Catatan Mandiri Shalat 5 Waktu Bersama Orang Tua',
                'bobot' => ['tugas' => 30.00, 'quiz' => 20.00, 'projek' => 25.00, 'uts' => 12.50, 'uas' => 12.50],
                'topik_mingguan' => [
                    1 => 'Orientasi Kelas: Membiasakan Salam dan Senyum Saat Masuk Kelas',
                    2 => 'Adab Membawa dan Merapikan Sepatu serta Tas Sekolah di Tempatnya',
                    3 => 'Mengenal Waktu-Waktu Shalat Fardhu 5 Waktu Sehari Semalam',
                    4 => 'Mendengarkan Adzan dan Menjawab Panggilan Adzan dengan Tenang',
                    5 => 'Mempersiapkan Perlengkapan Shalat: Memakai Peci / Mukena Mandiri',
                    6 => 'Praktik Berdiri Tegap (Qiyam) dan Menghadap Kiblat',
                    7 => 'Praktik Takbiratul Ihram dan Meletakkan Tangan di Atas Dada (Sedekap)',
                    8 => 'Ujian Praktik Adab & Gerakan Awal Shalat Tengah Semester',
                    9 => 'Praktik Gerakan Rukuk yang Tumaninah dan Membaca Dzikirnya',
                    10 => 'Praktik Bangkit dari Rukuk (I\'tidal) dan Merasakan Kedamaian Ibadah',
                    11 => 'Praktik Gerakan Sujud Sempurna (Tujuh Anggota Sujud)',
                    12 => 'Praktik Duduk di Antara Dua Sujud dan Duduk Tasyahud Awal',
                    13 => 'Praktik Duduk Tasyahud Akhir dan Mengucapkan Salam ke Kanan dan Kiri',
                    14 => 'Simulasi Shalat Dzuhur Berjamaah Penuh 4 Rakaat di Mushalla Unit',
                    15 => 'Dzikir dan Doa Singkat Sesudah Shalat Bersama Imam',
                    16 => 'Uji Petik Shalat Mandiri & Penganugerahan Bintang Santri Beradab',
                ],
            ],
            'TAUD-01' => [
                'kode_sub' => 'TAUD-SAQU',
                'mapel_keywords' => ['Tahfizh', 'Al-Qur\'an', 'Agama'],
                'nama_modul' => 'Program Semester 1 Tahfizh Balita SaQu: Metode Talqin & Gerak Juz 30',
                'jenjang' => 'TAUD',
                'kurikulum' => 'Kurikulum Tahfizh Anak Usia Dini Sahabat Qur\'an (SaQu)',
                'alokasi_jam' => 32,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 2,
                'atp' => 'Mengenalkan pelafalan ayat suci Al-Qur\'an Juz 30 secara berulang dengan irama tartil yang menyenangkan.',
                'cp' => 'Balita mampu menirukan lantunan surah pendek secara fasih dan menyukai suasana majelis Al-Qur\'an.',
                'tujuan' => 'Menanamkan kecintaan pada kalamullah sejak usia emas balita dengan metode visual dan kinestetik.',
                'metode' => 'Talqin Fardhi, Klasikal Berulang, Gerakan Tangan Asosiatif Makna',
                'model' => 'Halaqah Balita Ceria Al-Qur\'an',
                'media' => 'Poster Al-Qur\'an SaQu Warna-Warni, Boneka Peraga Suara, Audio Qari Cilik',
                'sumber' => 'Buku Panduan Metode SaQu (Sahabat Qur\'an) Anak Usia Dini',
                'target_nilai' => 85.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Surah Al-Fatihah, An-Nas, Al-Falaq, Al-Ikhlas, Al-Masad, An-Nashr',
                'target_proyek' => 'Dokumentasi Suara Hafalan Murni Balita di Rumah',
                'bobot' => ['tugas' => 35.00, 'quiz' => 20.00, 'projek' => 25.00, 'uts' => 10.00, 'uas' => 10.00],
                'topik_mingguan' => [
                    1 => 'Ta\'aruf SaQu: Senandung Huruf Hijaiyah dengan Nada Gembira',
                    2 => 'Talqin Surah Al-Fatihah Ayat 1-2 Disertai Gerakan Tangan Halus',
                    3 => 'Talqin Surah Al-Fatihah Ayat 3-4 dan Pengulangan Bersama Ustadzah',
                    4 => 'Talqin Surah Al-Fatihah Ayat 5-7 dan Tasmi\' Kelompok Ceria',
                    5 => 'Pengenalan Surah An-Nas Ayat 1-3 Metode Menirukan Suara Lembut',
                    6 => 'Pengenalan Surah An-Nas Ayat 4-6 dan Tepuk Fokus Qur\'ani',
                    7 => 'Tasmi\' Cilik Surah An-Nas Mandiri di Depan Halaqah',
                    8 => 'Pekan Evaluasi Ceria Tengah Semester: Parade Hafalan Surah Al-Fatihah & An-Nas',
                    9 => 'Talqin Surah Al-Falaq Ayat 1-2 dengan Artikulasi Jelas',
                    10 => 'Talqin Surah Al-Falaq Ayat 3-5 dan Mengulang Kata Kunci Berulang',
                    11 => 'Muroja\'ah Surah Al-Falaq Melalui Media Audio Interaktif',
                    12 => 'Talqin Surah Al-Ikhlas Ayat 1-2: Mengenal Makna Allah Esa',
                    13 => 'Talqin Surah Al-Ikhlas Ayat 3-4 dan Menyatukan Surah Secara Lengkap',
                    14 => 'Muroja\'ah 3 Qul (An-Nas, Al-Falaq, Al-Ikhlas) Sebelum Istirahat',
                    15 => 'Simulasi Tampil Mandiri Bersama Teman Sebaya',
                    16 => 'Wisuda Cilik SaQu Semester 1: Tasmi\' Terbuka di Hadapan Orang Tua',
                ],
            ],
            'SDIT-01' => [
                'kode_sub' => 'SD1-IPAS',
                'mapel_keywords' => ['Ilmu Pengetahuan Alam dan Sosial', 'IPAS', 'Sains'],
                'nama_modul' => 'Modul Semester 1 IPAS: Eksplorasi Harmoni Alam Semesta & Tubuh Manusia',
                'jenjang' => 'SDIT',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Peserta didik menganalisis hubungan panca indera, rantai makanan, fotosintesis, dan pelestarian alam terpadu.',
                'cp' => 'Peserta didik memahami sistem organ tubuh manusia dan saling ketergantungan makhluk hidup dalam ekosistem.',
                'tujuan' => 'Menghubungkan fakta sains modern dengan keagungan ciptaan Allah Subhanahu wa Ta\'ala.',
                'metode' => 'Inkuiri Terbimbing, Eksperimen Laboratorium Sederhana, Observasi Lingkungan',
                'model' => 'Problem Based Learning (PBL) & Saintifik Islam Terpadu',
                'media' => 'Model Torso Anatomi, Mikroskop Sederhana, Kit Eksperimen Tumbuhan, PPT Sains',
                'sumber' => 'Buku IPAS SD Kelas Terpadu JSIT & Buku Sains Kurikulum Merdeka',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Nama-Nama Organ Tubuh & Istilah Siklus Ekosistem Ilmiah',
                'target_proyek' => 'Pembuatan Terarium Ekosistem Mini dan Herbarium Tanaman Obat',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Kontrak Belajar & Pengenalan Konsep Harmoni Alam Ciptaan Sang Pencipta',
                    2 => 'Bagian Tubuh Tumbuhan: Akar, Batang, dan Daun Serta Fungsinya',
                    3 => 'Fotosintesis: Proses Dapur Tumbuhan Menghasilkan Oksigen dan Glukosa',
                    4 => 'Perkembangbiakan Tumbuhan Secara Generatif dan Vegetatif',
                    5 => 'Eksperimen Praktikum: Pengaruh Sinar Matahari terhadap Pertumbuhan Kecambah',
                    6 => 'Wujud Zat dan Perubahannya: Padat, Cair, Gas dalam Keseharian',
                    7 => 'Praktik Perubahan Wujud Benda: Mencair, Membeku, Menguap, dan Mengembun',
                    8 => 'Asesmen Sumatif Tengah Semester (PTS IPAS Terpadu)',
                    9 => 'Gaya di Sekitar Kita: Gaya Otot, Gaya Gesek, dan Gaya Gravitasi Bumi',
                    10 => 'Pemanfaatan Gaya Magnet dalam Kompas dan Teknologi Navigasi',
                    11 => 'Energi yang Bergerak: Perubahan Bentuk Energi Kinetik dan Potensial',
                    12 => 'Energi Terbarukan: Tenaga Surya, Air, dan Angin sebagai Solusi Ramah Lingkungan',
                    13 => 'Ekosistem dan Rantai Makanan: Hubungan Produsen, Konsumen, dan Pengurai',
                    14 => 'Jaring-Jaring Makanan dan Keseimbangan Alam Menurut Perspektif Ekologi',
                    15 => 'Presentasi Tugas Proyek Terarium Ekosistem Mini Kelompok Siswa',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS IPAS Semester 1)',
                ],
            ],
            'SDIT-02' => [
                'kode_sub' => 'SD2-ARAB',
                'mapel_keywords' => ['Bahasa Arab', 'Arab'],
                'nama_modul' => 'Modul Semester 1 Bahasa Arab Terpadu: Ta\'aruf, Adawatul Madrosiyyah & A\'dhaul Jism',
                'jenjang' => 'SDIT',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Menguasai kosakata dasar ta\'aruf, perlengkapan sekolah, anggota tubuh, dan menyusun kalimat isim isyarah sederhana.',
                'cp' => 'Mampu berkomunikasi aktif menggunakan frasa sapaan Arab harian dan memahami instruksi guru di kelas.',
                'tujuan' => 'Membiasakan bahasa Al-Qur\'an sebagai bahasa percakapan harian dan pengantar ilmu syar\'i.',
                'metode' => 'Metode Langsung (Thariqah Mubasyarah), Hiwar (Percakapan), Game Tebak Kosakata',
                'model' => 'Communicative Language Teaching (CLT)',
                'media' => 'Flashcard Mufradat Bergambar, Audio Percakapan Native, Papan Kata Magnetik',
                'sumber' => 'Buku Al-Lughah Al-Arabiyyah JSIT & Kamus Bergambar Santri',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => '50 Mufradat Baru Seputar Sekolah dan Percakapan Ta\'aruf Lengkap',
                'target_proyek' => 'Rekaman Video Percakapan Hiwar Berpasangan di Lingkungan Sekolah',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Ta\'aruf dan Ungkapan Sapaan: Kaifa Haluk, Ahlan wa Sahlan, Sobahul Khair',
                    2 => 'Penggunaan Isim Dhomir Ana dan Anta/Anti dalam Memperkenalkan Diri',
                    3 => 'Isim Isyarah Dasar: Haadza dan Haadzihi untuk Kata Muzakkar & Muannats',
                    4 => 'Mufradat Adawatul Madrosiyyah (Peralatan Sekolah): Kitab, Qolam, Mistarah',
                    5 => 'Mufradat Ruang Kelas: Sabbuuroh, Bab, Naafidzah, Maktab, Kursiy',
                    6 => 'Praktik Percakapan Singkat Menanyakan Benda: Ma Hadza? Ma Hadzihi?',
                    7 => 'Latihan Menulis Huruf Hijaiyah Sambung untuk Kata Benda Sekolah',
                    8 => 'Ujian Tengah Semester (PTS): Uji Tulis Mufradat & Praktik Hiwar Ta\'aruf',
                    9 => 'Mengenal Anggota Tubuh (A\'dhaul Jism): Ro\'sun, \'Ainun, Anfun, Famun',
                    10 => 'Lanjutan Anggota Tubuh: Yadun, Rijlun, Udzunun dan Kaidah Muannats Hakiki',
                    11 => 'Struktur Kalimat Kepemilikan (Dhomir Muttashil): Kitabi, Qolamuka, Kitabuha',
                    12 => 'Angka Arab 1 sampai 20 (Al-Arqaam) dan Penggunaannya Menghitung Benda',
                    13 => 'Warna-Warna dalam Bahasa Arab (Al-Alwan): Abyadh, Aswad, Ahmar, Akhdhar',
                    14 => 'Latihan Membaca Paragraf Pendek Deskriptif tentang Sekolahku',
                    15 => 'Pekan Simulasi Hiwar Percakapan Berpasangan di Depan Kelas',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS Bahasa Arab Terpadu)',
                ],
            ],
            'SDIT-03' => [
                'kode_sub' => 'SD3-MTK',
                'mapel_keywords' => ['Matematika', 'Hitung'],
                'nama_modul' => 'Modul Semester 1 Matematika Terpadu: Operasi Bilangan Cacah, Pecahan & Geometri',
                'jenjang' => 'SDIT',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Memahami nilai tempat bilangan cacah hingga 10.000, operasi penjumlahan-perkalian susun, pecahan, dan luas bangun datar.',
                'cp' => 'Peserta didik mampu menyelesaikan masalah matematis kontekstual dalam transaksi jual beli dan perhitungan zakat fitrah sederhana.',
                'tujuan' => 'Membangun logika berpikir kritis, jujur, teliti, dan menghubungkan matematika dengan syariat Islam.',
                'metode' => 'Problem Based Learning, Latihan Bertingkat, Pembelajaran Berbasis Manipulatif',
                'model' => 'Problem Based Learning (PBL)',
                'media' => 'Blok Dienes Nilai Tempat, Geoboard Bangun Datar, Lembar Kerja Berbasis Soal Cerita',
                'sumber' => 'Buku Matematika Kurikulum Merdeka Terpadu & Buku Soal Olimpiade Dasar',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Tabel Perkalian 1 sampai 10 di Luar Kepala',
                'target_proyek' => 'Simulasi Market Day: Pencatatan Laporan Modal, Penjualan, dan Laba Bersih',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Membaca, Menulis, dan Menentukan Nilai Tempat Bilangan Cacah s/d 10.000',
                    2 => 'Operasi Penjumlahan Bilangan Cacah Bersusun dengan Teknik Menyimpan',
                    3 => 'Operasi Pengurangan Bilangan Cacah Bersusun dengan Teknik Meminjam',
                    4 => 'Perkalian Bilangan Cacah sebagai Penjumlahan Berulang & Perkalian Susun Pendek',
                    5 => 'Pembagian Bilangan Cacah sebagai Pengurangan Berulang & Porogapit Sederhana',
                    6 => 'Penyelesaian Soal Cerita Kontekstual Operasi Hitung Campuran Keseharian',
                    7 => 'Konsep FPB dan KPK Menggunakan Pohon Faktor dan Tabel Faktorisasi Prima',
                    8 => 'Asesmen Sumatif Tengah Semester (PTS Matematika Terpadu)',
                    9 => 'Pengenalan Konsep Pecahan Senilai Melalui Ilustrasi Kue dan Gambar Arsir',
                    10 => 'Operasi Penjumlahan dan Pengurangan Pecahan Biasa dengan Penyebut Sama & Berbeda',
                    11 => 'Pecahan Desimal dan Persen: Konversi Nilai dan Penggunaannya dalam Zakat',
                    12 => 'Karakteristik Bangun Datar: Persegi, Persegi Panjang, dan Segitiga',
                    13 => 'Menghitung Keliling dan Luas Bangun Datar Menggunakan Rumus Standar',
                    14 => 'Pengukuran Sudut Menggunakan Busur Derajat dan Jenis-Jenis Sudut',
                    15 => 'Proyek Market Day Cilik: Perhitungan Arus Kas dan Keuntungan Penjualan',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS Matematika Terpadu)',
                ],
            ],
            'SDIT-04' => [
                'kode_sub' => 'SD4-TAHFIZH',
                'mapel_keywords' => ['Tahfizh', 'Al-Qur\'an', 'Agama'],
                'nama_modul' => 'Modul Semester 1 Tahfizh Al-Qur\'an: Tahsin Tartil, Kaidah Tajwid & Ziyadah Juz 30',
                'jenjang' => 'SDIT',
                'kurikulum' => 'Kurikulum Merdeka Plus Tahfizh',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Menghafal 1 Juz (Juz 30) mutqin disertai penguasaan hukum nun mati/tanwin, mim mati, dan makharijul huruf.',
                'cp' => 'Peserta didik mampu melantunkan bacaan Al-Qur\'an dengan kaidah tajwid riwayat Hafsh \'an \'Ashim secara tartil.',
                'tujuan' => 'Mencetak generasi penghafal Al-Qur\'an yang berakhlak mulia dan berdisiplin muroja\'ah.',
                'metode' => 'Metode Sabak Sabqi Manzil, Tikrar Jama\'i, Talqin Ustadz, Tasmi\' Berpasangan',
                'model' => 'Sistem Halaqah Tahfizh Terpadu',
                'media' => 'Mushaf Al-Qur\'an Pojok Kudus / Madinah, Mutaba\'ah Digital Card',
                'sumber' => 'Buku Pedoman Tajwid Praktis & Buku Catatan Mutaba\'ah Harian Santri',
                'target_nilai' => 80.00,
                'target_kehadiran' => 95.00,
                'target_hafalan' => 'Juz 30 Lengkap (Surah An-Naba\' s/d An-Nas) beserta Hukum Tajwidnya',
                'target_proyek' => 'Ujian Tasmi\' Sekali Duduk 1/2 Juz di Hadapan Penguji dan Orang Tua',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Pembukaan Halaqah, Penguatan Niat Menghafal Al-Qur\'an & Matriks Ziyadah Target',
                    2 => 'Tahsin Surah An-Naba\' Ayat 1-20 & Kaidah Nun Sukun/Tanwin (Idzhar Halqi)',
                    3 => 'Ziyadah Surah An-Naba\' Ayat 21-40 & Praktik Idghom Bighunnah dan Bilaghunnah',
                    4 => 'Muroja\'ah Surah An-Naba\' Sempurna & Kaidah Iqlab Beserta Contoh Ayat',
                    5 => 'Tahsin dan Ziyadah Surah An-Nazi\'at Ayat 1-25 & Hukum Ikhfa Haqiqi (15 Huruf)',
                    6 => 'Ziyadah Surah An-Nazi\'at Ayat 26-46 & Hukum Mim Sukun (Idzhar Syafawi)',
                    7 => 'Tasmi\' Halaqah Gabungan Surah An-Naba\' dan An-Nazi\'at',
                    8 => 'Ujian Tahfizh Tengah Semester (Imtihan Nisfi Juz 30 Bagian Awal)',
                    9 => 'Tahsin & Ziyadah Surah \'Abasa Ayat 1-25 & Hukum Idghom Mimi / Mitslain',
                    10 => 'Ziyadah Surah \'Abasa Ayat 26-42 & Hukum Ikhfa Syafawi',
                    11 => 'Tahsin & Ziyadah Surah At-Takwir Ayat 1-29 & Pengenalan Huruf Ghunnah Musyaddadah',
                    12 => 'Ziyadah Surah Al-Infithar & Al-Muthaffifin Ayat 1-20 & Qalqalah Sugro/Kubro',
                    13 => 'Muroja\'ah Intensif Surah Al-Muthaffifin, Al-Insyiqaq, dan Al-Buruj',
                    14 => 'Kajian Adab Pembawa Al-Qur\'an dari Kitab At-Tibyan Imam An-Nawawi',
                    15 => 'Simulasi Tasmi\' Mandiri Menghadapi Ujian Akhir Semester',
                    16 => 'Imtihan Akhir Semester / Tasmi\' Akbar 1/2 Juz Sekali Duduk',
                ],
            ],
            'MIT-01' => [
                'kode_sub' => 'MIT-SAQU',
                'mapel_keywords' => ['Tahfizh', 'Al-Qur\'an', 'Agama'],
                'nama_modul' => 'Modul Semester 1 Tahfizh & Mutaba\'ah Tilawah SaQu Juz 29-30',
                'jenjang' => 'MIT',
                'kurikulum' => 'Kurikulum Madrasah Ibtidaiyah Tahfizh Sahabat Qur\'an',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Mencapai ketuntasan hafalan Juz 29 mutqin serta tilawah harian 1 juz per hari dengan fasih.',
                'cp' => 'Peserta didik menguasai kaidah tajwid lanjutan, waqaf wal ibtida\', dan fashahah bacaan.',
                'tujuan' => 'Menjadikan santri madrasah sahabat setia Al-Qur\'an dalam bacaan, hafalan, dan pengamalan.',
                'metode' => 'Metode Talaqqi SaQu, Setoran Ziyadah Pagi, Muroja\'ah Sore Berpasangan',
                'model' => 'Halaqah Tahfizh Madrasah Terpadu',
                'media' => 'Mushaf SaQu Tajwid Berwarna, Buku Agenda Mutaba\'ah Harian Santri',
                'sumber' => 'Panduan Mutaba\'ah Tahfizh SaQu & Tuhfatul Athfal Tajwid',
                'target_nilai' => 80.00,
                'target_kehadiran' => 95.00,
                'target_hafalan' => 'Juz 29 (Surah Al-Mulk sampai Al-Mursalat) Mutqin',
                'target_proyek' => 'Tasmi\' Sekali Duduk 1 Juz Penuh di Depan Wali Santri',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Penyusunan Rencana Target Ziyadah Juz 29 & Kaidah Adab Majelis Al-Qur\'an',
                    2 => 'Tahsin & Ziyadah Surah Al-Mulk Ayat 1-15 & Kaidah Ghunnah dan Mad Asli',
                    3 => 'Ziyadah Surah Al-Mulk Ayat 16-30 & Kaidah Waqaf Wal Ibtida\'',
                    4 => 'Muroja\'ah Mutqin Surah Al-Mulk & Tahsin Surah Al-Qalam Ayat 1-25',
                    5 => 'Ziyadah Surah Al-Qalam Ayat 26-52 & Hukum Mad Wajib Muttashil',
                    6 => 'Tahsin & Ziyadah Surah Al-Haqqah Ayat 1-30 & Hukum Mad Jaiz Munfashil',
                    7 => 'Ziyadah Surah Al-Haqqah Ayat 31-52 & Tasmi\' 3 Surah Pertama Juz 29',
                    8 => 'Asesmen Sumatif Tengah Semester (Tasmi\' 15 Halaman Pertama Juz 29)',
                    9 => 'Tahsin & Ziyadah Surah Al-Ma\'arij Ayat 1-44 & Hukum Mad Lazim Kalimi',
                    10 => 'Tahsin & Ziyadah Surah Nuh Ayat 1-28 & Pengenalan Sifat Huruf Hams dan Jahr',
                    11 => 'Tahsin & Ziyadah Surah Al-Jinn Ayat 1-28 & Sifat Huruf Isti\'la dan Istifal',
                    12 => 'Tahsin & Ziyadah Surah Al-Muzzammil & Al-Muddatsir & Sifat Qalqalah dan Shofir',
                    13 => 'Tahsin & Ziyadah Surah Al-Qiyamah & Al-Insan',
                    14 => 'Tahsin & Ziyadah Surah Al-Mursalat Ayat 1-50 (Penyelesaian Juz 29)',
                    15 => 'Pekan Penguncian Hafalan (Muroja\'ah Kubro Juz 29 Full)',
                    16 => 'Asesmen Sumatif Akhir Semester (Tasmi\' Terbuka 1 Juz Sekali Duduk)',
                ],
            ],
            'SMPIT-01' => [
                'kode_sub' => 'SMP1-IPA',
                'mapel_keywords' => ['Ilmu Pengetahuan Alam', 'IPA', 'Sains'],
                'nama_modul' => 'Modul Semester 1 IPA Terpadu: Sel, Sistem Organ, Listrik Dinamis & Ekologi',
                'jenjang' => 'SMP',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Menganalisis tingkatan organisasi kehidupan, sistem peredaran darah, pewarisan sifat, dan konsep listrik dinamis.',
                'cp' => 'Peserta didik dapat merancang penyelidikan ilmiah dan mengomunikasikan kesimpulan berbasis data faktual.',
                'tujuan' => 'Menghubungkan sains dengan integrasi nilai tauhid rububiyyah atas keteraturan ciptaan alam.',
                'metode' => 'Praktikum Laboratorium, Problem Based Learning, Analisis Data Empiris',
                'model' => 'Inquiry-Based Learning & STEM Terintegrasi Islam',
                'media' => 'Mikroskop Binokuler, Preparat Sel, Alat Kit Praktikum Listrik Ohm, Virtual Lab PhET',
                'sumber' => 'Buku IPA SMP Kelas VII-IX Kurikulum Merdeka & Jurnal Sains Terapan JSIT',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Rumus Hukum Ohm, Daya Listrik, dan Struktur Organel Sel Beserta Fungsinya',
                'target_proyek' => 'Rancang Bangun Rangkaian Listrik Rumah Sederhana Ramah Energi',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Pengenalan Metode Ilmiah, Keselamatan Kerja di Laboratorium IPA & Etika Peneliti',
                    2 => 'Sel sebagai Unit Terkecil Kehidupan: Perbedaan Sel Hewan dan Sel Tumbuhan',
                    3 => 'Praktikum: Pengamatan Sayatan Sel Bawang Merah dan Epitel Pipi di Bawah Mikroskop',
                    4 => 'Jaringan, Organ, dan Sistem Organ pada Manusia dan Hewan',
                    5 => 'Sistem Pencernaan Manusia: Saluran Cerna, Enzim Pencernaan, dan Pola Makan Sehat',
                    6 => 'Sistem Peredaran Darah: Jantung, Pembuluh Darah, dan Komposisi Sel Darah',
                    7 => 'Kelainan dan Penyakit pada Sistem Peredaran Darah serta Upaya Pencegahannya',
                    8 => 'Asesmen Sumatif Tengah Semester (PTS IPA Terpadu)',
                    9 => 'Muatan Listrik, Hukum Coulomb, dan Medan Listrik Statis dalam Kehidupan',
                    10 => 'Arus Listrik, Beda Potensial, dan Verifikasi Eksperimen Hukum Ohm',
                    11 => 'Rangkaian Listrik Seri dan Paralel: Karakteristik Kuat Arus dan Hambatan Pengganti',
                    12 => 'Hukum Kirchoff 1 dan Energi serta Daya Listrik Rumahan (kWh Meter)',
                    13 => 'Interaksi Makhluk Hidup dengan Lingkungan: Komponen Biotik dan Abiotik',
                    14 => 'Dampak Pencemaran Air, Udara, dan Tanah terhadap Kesehatan Manusia',
                    15 => 'Uji Coba & Pameran Produk Proyek STEM: Rangkaian Listrik Rumah Hemat Energi',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS IPA Terpadu)',
                ],
            ],
            'SMPIT-02' => [
                'kode_sub' => 'SMP2-ARAB',
                'mapel_keywords' => ['Bahasa Arab', 'Arab', 'Nahwu'],
                'nama_modul' => 'Modul Semester 1 Bahasa Arab Terpadu: Qawa\'id Nahwu Dasar, Hiwar & Qira\'ah',
                'jenjang' => 'SMP',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Memahami klasifikasi kalam (isim, fi\'il, huruf), tanda i\'rab rafa\'nashab, dan menerapkan hiwar bertema keseharian.',
                'cp' => 'Peserta didik mampu membaca teks berbahasa Arab berharakat dengan benar dan mengonstruksi jumlah mufidah.',
                'tujuan' => 'Membentuk kemampuan literasi bahasa Arab aktif dan pasif sebagai bekal memahami dalil Al-Qur\'an dan Hadits.',
                'metode' => 'Thariqah Qawa\'id wa Tarjamah, Praktik Muhadatsah Berkelompok, Bedah Teks Bacaan',
                'model' => 'Problem Based Learning & Drill Komunikatif',
                'media' => 'Papan I\'rab Interaktif, Lembar Kerja Mufradat Tematik, Audio Dialog Arabiyah Baina Yadaik',
                'sumber' => 'Buku Bahasa Arab SMPIT Terpadu & Durusul Lughah Al-Arabiyyah Jilid 1-2',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Matan Kaidah Pembagian Isim dan Fi\'il beserta 100 Kosakata Tematik',
                'target_proyek' => 'Produksi Video Sandiwara / Drama Pendek Bahasa Arab Bertema Muamalah di Pasar',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Pengantar Ilmu Bahasa Arab: Urgensi Bahasa Arab bagi Penuntut Ilmu Syar\'i',
                    2 => 'Kaidah Pembagian Al-Kalam: Al-Ismu, Al-Fi\'lu, dan Al-Harfu beserta Tandanya',
                    3 => 'Tanda-Tanda Isim: Al-Jarru, At-Tanwin, Masuknya Alif Lam, dan Huruf Jar',
                    4 => 'Tanda-Tanda Fi\'il: Qad, Sin, Saufa, dan Ta\' Ta\'nits Sakinah',
                    5 => 'Huruf-Huruf Jar dan Pengaruhnya terhadap I\'rab Isim Sesudahnya',
                    6 => 'Al-Mudzakar wal Muannats: Kaidah Pemilahan Gender Kata Benda',
                    7 => 'Isim Isyarah dan Isim Maushul dalam Kalimat Sederhana',
                    8 => 'Asesmen Sumatif Tengah Semester (PTS Bahasa Arab & Qawa\'id)',
                    9 => 'Al-Mufrad, Al-Mutsanna, dan Al-Jam\' (Mudzakar Salim, Muannats Salim, Taksir)',
                    10 => 'Konsep Al-I\'rab wal Bina\': Perubahan Akhir Kata Akibat Amil yang Masuk',
                    11 => 'Jumlah Ismiyyah: Struktur Mubtada\' dan Khabar beserta Kaidah Kesesuaiannya',
                    12 => 'Jumlah Fi\'liyyah: Struktur Fi\'il Madhi, Fa\'il, dan Maf\'ul Bih',
                    13 => 'Teks Bacaan Qira\'ah Tematik: Al-Hayatu fil Madrasah (Kehidupan di Sekolah)',
                    14 => 'Latihan Menulis Insya\' (Karangan Bebas Pendek) Menggunakan Kosa Kata Baru',
                    15 => 'Pekan Penilaian Unjuk Kerja Proyek: Pementasan Drama Bahasa Arab Santri',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS Bahasa Arab Terpadu)',
                ],
            ],
            'SMAIT-01' => [
                'kode_sub' => 'SMA1-FISIKA',
                'mapel_keywords' => ['Fisika', 'IPA', 'Sains'],
                'nama_modul' => 'Modul Semester 1 Fisika Kinematika, Dinamika Gerak Newton & Gravitasi Semesta',
                'jenjang' => 'SMA',
                'kurikulum' => 'Kurikulum Merdeka',
                'alokasi_jam' => 64,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 4,
                'atp' => 'Menganalisis besaran fisis gerak lurus (GLB & GLBB), gerak parabola, gerak melingkar, dan hukum dinamika Newton.',
                'cp' => 'Mampu memodelkan fenomena gerak secara matematis dan melakukan verifikasi eksperimen laboratorium fisika.',
                'tujuan' => 'Menjadikan telaah sains fisika sebagai wasilah mengagumi presisi sunnatullah di alam semesta.',
                'metode' => 'Eksperimen Digital Ticker Timer, Pemodelan Vektor Matematis, Analisis Grafik Gerak',
                'model' => 'Problem Based Learning (PBL) & Saintifik SMA Terpadu',
                'media' => 'Rel Presisi Gerak (Linear Air Track), Ticker Timer, Sensor Gerak Vernier, Software GeoGebra',
                'sumber' => 'Buku Fisika SMA Kelas X-XI Kurikulum Merdeka & Serway Physics for Scientists',
                'target_nilai' => 75.00,
                'target_kehadiran' => 90.00,
                'target_hafalan' => 'Persamaan Kinematika GLBB, Hukum Newton I-III, dan Hukum Gravitasi Universal',
                'target_proyek' => 'Rancang Bangun Roket Air Tekanan Udara dengan Analisis Sudut Parabola Optimum',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Hakikat Fisika, Metode Ilmiah, Besaran Pokok, Besaran Turunan, dan Analisis Dimensi',
                    2 => 'Pengukuran Presisi Menggunakan Jangka Sorong, Mikrometer Sekrup, dan Angka Penting',
                    3 => 'Vektor: Penjumlahan Vektor secara Grafis dan Analitis (Penguraian Komponen Sumbu X-Y)',
                    4 => 'Kinematika Gerak Lurus: Posisi, Jarak, Perpindahan, Kelajuan, dan Kecepatan Sesaat',
                    5 => 'Gerak Lurus Beraturan (GLB) dan Analisis Grafik Posisi terhadap Waktu (s-t dan v-t)',
                    6 => 'Gerak Lurus Berubah Beraturan (GLBB): Percepatan, Gerak Jatuh Bebas, dan Gerak Vertikal',
                    7 => 'Praktikum Laboratorium: Penentuan Percepatan Gravitasi Bumi Menggunakan Ticker Timer',
                    8 => 'Asesmen Sumatif Tengah Semester (PTS Fisika Terpadu)',
                    9 => 'Gerak Parabola: Penguraian Komponen Gerak Horizontal GLB dan Vertikal GLBB',
                    10 => 'Gerak Melingkar Beraturan (GMB): Kecepatan Sudut, Periode, Frekuensi, Percepatan Sentripetal',
                    11 => 'Dinamika Gerak: Hukum I Newton (Kelembaman) dan Hukum II Newton (F = m.a)',
                    12 => 'Hukum III Newton (Aksi-Reaksi) dan Gaya Gesek Statis serta Kinetis pada Bidang Miring',
                    13 => 'Penerapan Hukum Newton pada Sistem Katrol dan Lift Bergerak',
                    14 => 'Hukum Gravitasi Universal Newton, Medan Gravitasi, dan Hukum Keppler Gerak Planet',
                    15 => 'Uji Coba & Kompetisi Peluncuran Roket Air Hasil Proyek Riset Kelompok Siswa',
                    16 => 'Asesmen Sumatif Akhir Semester (PAS Fisika Terpadu)',
                ],
            ],
            'PONPES-PA' => [
                'kode_sub' => 'PONPES-PA-TAHFIZH',
                'mapel_keywords' => ['Tahfizh', 'Al-Qur\'an', 'Agama'],
                'nama_modul' => 'Program Semester 1 Tahfizh Intensif Ponpes Putra: Target 3 Juz Mutqin & Sanad Tajwid',
                'jenjang' => 'PONPES',
                'kurikulum' => 'Kurikulum Terpadu Kepesantrenan & Lembaga Tahfizh Darel Iman',
                'alokasi_jam' => 96,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 6,
                'atp' => 'Menyelesaikan setoran hafalan baru 3 juz mutqin, muroja\'ah harian 1 juz, dan mengkaji matan Al-Jazariyyah.',
                'cp' => 'Santri menguasai tajwid teoritis dan praktis riwayat Hafsh serta mampu tasmi\' beruntun tanpa melihat mushaf.',
                'tujuan' => 'Mencetak kader ulama penghafal Al-Qur\'an yang berakhlak Qur\'ani dan berdisiplin tinggi di pesantren.',
                'metode' => 'Setoran Ba\'da Subuh, Muroja\'ah Qobla Ashar, Muroja\'ah Berpasangan Ba\'da Maghrib',
                'model' => 'Halaqah Asrama Berkelanjutan (Karantina Tahfizh Intensif)',
                'media' => 'Mushaf Al-Qur\'an Huffazh Madinah Rasm Utsmani, Kartu Setoran Santri Pesantren',
                'sumber' => 'Matan Al-Muqaddimah Al-Jazariyyah & Kitab Nihayatul Qaulil Mufid',
                'target_nilai' => 85.00,
                'target_kehadiran' => 95.00,
                'target_hafalan' => '3 Juz Baru (Juz 1, 2, 3 atau Juz 28, 29, 30) beserta Matan Jazariyyah 30 Bait',
                'target_proyek' => 'Tasmi\' Sekali Duduk 2 Juz Beruntun Disaksikan Mudir Pesantren',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Iftitah Halaqah: Niat Ikhlas, Disiplin Waktu Halaqah, dan Pemetaan Target Ziyadah',
                    2 => 'Ziyadah Pekan 1: 5 Halaman Baru & Bedah Matan Jazariyyah: Bab Makharijul Huruf Bagian 1',
                    3 => 'Ziyadah Pekan 2: 5 Halaman Baru & Bedah Matan Jazariyyah: Bab Makharijul Huruf Bagian 2',
                    4 => 'Muroja\'ah Sabqi: Mengulang 10 Halaman yang Baru Dihafal Sekali Duduk',
                    5 => 'Ziyadah Pekan 3: 5 Halaman Baru & Bedah Matan Jazariyyah: Bab Sifat Huruf Berlawanan',
                    6 => 'Ziyadah Pekan 4: 5 Halaman Baru & Bedah Matan Jazariyyah: Bab Sifat Huruf Tanpa Lawan',
                    7 => 'Tasmi\' Juz Pertama yang Dihafal di Hadapan Pengampu Halaqah Utama',
                    8 => 'Imtihan Nisfi / Ujian Tengah Semester: Uji Kelayakan Hafalan 1 Juz Penuh Sekali Duduk',
                    9 => 'Ziyadah Pekan 5: 5 Halaman Baru Juz Berikutnya & Kajian Hukum Tajwid Terapan (Tafkhim & Tarqiq)',
                    10 => 'Ziyadah Pekan 6: 5 Halaman Baru & Kajian Hukum Idghom Mutamatsilain, Mutaqaribain, Mutajanisain',
                    11 => 'Muroja\'ah Sabqi Lanjutan: Menggabungkan Juz Pertama dan Halaman Baru yang Disetor',
                    12 => 'Ziyadah Pekan 7: 5 Halaman Baru & Kajian Bab Ahkamul Madd wal Qashr',
                    13 => 'Ziyadah Pekan 8: 5 Halaman Baru & Kajian Bab Ma\'rifatul Wuquf wal Ibtida\'',
                    14 => 'Muroja\'ah Akbar Seluruh Halaman Baru (Penguncian 3 Juz Ziyadah)',
                    15 => 'Karantina Tasmi\' Mandiri Menjelang Ujian Akhir di Masjid Jami\' Pesantren',
                    16 => 'Imtihan Akhir Semester / Tasmi\' Akbar Terbuka Disaksikan Dewan Asatidzah & Orang Tua',
                ],
            ],
            'PONPES-PI' => [
                'kode_sub' => 'PONPES-PI-SYARIAH',
                'mapel_keywords' => ['Adab', 'Agama', 'Tahfizh'],
                'nama_modul' => 'Program Semester 1 Dirasah Islamiyyah, Fiqih Ibadah & Adab Muslimah Pesantren',
                'jenjang' => 'PONPES',
                'kurikulum' => 'Kurikulum Terpadu Kepesantrenan Putri Darel Iman',
                'alokasi_jam' => 96,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 6,
                'atp' => 'Memahami fiqih thaharah khusus wanita (haidh, nifas, istihadhah), fiqih shalat, dan adab pergaulan islami.',
                'cp' => 'Santriwati mampu mempraktikkan bersuci dan ibadah sesuai dalil shahih serta membiasakan akhlaqul karimah.',
                'tujuan' => 'Membina santriwati menjadi muslimah shalihah, berilmu syar\'i, dan siap berdakwah di lingkungan keluarga.',
                'metode' => 'Kajian Kitab Kuning Berharakat, Tanya Jawab Tematik Fiqih Wanita, Praktik Ibadah',
                'model' => 'Halaqah Dirasah Syar\'iyyah Khusus Santriwati',
                'media' => 'Kitab Panduan Matan Al-Ghayah wat Taqrib, Papan Tulis Diagram Fiqih Darah Wanita',
                'sumber' => 'Kitab Safinatun Najah, Matan Abu Syuja\', dan Risalah Fiqih Darah Wanita Shalihah',
                'target_nilai' => 80.00,
                'target_kehadiran' => 95.00,
                'target_hafalan' => 'Matan Matrik Hukum Haidh, Doa-Doa Seputar Thaharah, dan 20 Hadits Arbain Nawawi',
                'target_proyek' => 'Penyusunan Modul Catatan Praktis Fiqih Bersuci Wanita Muslimah',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Muqaddimah: Hakikat Menuntut Ilmu Syar\'i dan Kemuliaan Menjaga Kesucian Diri',
                    2 => 'Macam-Macam Air untuk Bersuci: Air Thahur, Air Thahir Ghairu Muthahhir, dan Air Najis',
                    3 => 'Najis dan Cara Menghilangkannya: Najis Mukhaffafah, Mutawassithah, dan Mughalladhah',
                    4 => 'Tata Cara Wudhu Sesuai Sunnah: Rukun, Sunnah, dan Pembatal-Pembatal Wudhu',
                    5 => 'Mandi Wajib (Ghusl): Rukun Mandi, Niat, dan Tata Cara Menyiram Air Sempurna',
                    6 => 'Fiqih Khusus Wanita: Pembagian Darah Haidh, Tanda Suci (Qashshatul Baidha\'), dan Batas Waktu',
                    7 => 'Darah Nifas dan Hukum-Hukum Ibadah yang Dilarang Semasa Mengalaminya',
                    8 => 'Imtihan Nisfi / Ujian Tengah Semester: Uji Tulis Pemahaman Fiqih Thaharah',
                    9 => 'Darah Istihadhah: Karakteristik Perbedaan dengan Haidh dan Cara Bersuci untuk Shalat',
                    10 => 'Fiqih Shalat Muslimah: Syarat Sah Shalat, Menutup Aurat Wanita dalam Shalat, dan Waktu',
                    11 => 'Rukun-Rukun Shalat dan Perbedaan Rincian Gerakan Shalat Wanita Sesuai Sunnah',
                    12 => 'Sujud Sahwi, Sujud Tilawah, dan Sujud Syukur Beserta Sebab-Sebabnya',
                    13 => 'Adab Berpakaian Syar\'i: Kaidah Hijab Sesuai Al-Qur\'an dan Sunnah Tanpa Tabarruj',
                    14 => 'Adab Muamalah dan Birrul Walidain: Menjaga Komunikasi Santun dengan Orang Tua',
                    15 => 'Pekan Evaluasi Mandiri: Simulasi Sidang Terbuka Fiqih Permasalahan Muslimah Kontemporer',
                    16 => 'Imtihan Akhir Semester: Ujian Teori & Praktik Fiqih Ibadah Terpadu Santriwati',
                ],
            ],
            'MAHAD-01' => [
                'kode_sub' => 'MAHAD-DIRASAH',
                'mapel_keywords' => ['Bahasa Arab', 'Arab', 'Pendidikan Agama Islam (PAI)', 'Agama'],
                'nama_modul' => 'Modul Semester 1 Dirasah Lughawiyyah & Syar\'iyyah Mahad: Nahwu Wadlih & Ushul Tsalatsah',
                'jenjang' => 'MAHAD',
                'kurikulum' => 'Kurikulum Salafiyah Terpadu Mahad Aly Darel Iman',
                'alokasi_jam' => 96,
                'jumlah_pertemuan' => 16,
                'jp_per_minggu' => 6,
                'atp' => 'Menguasai struktur sintaksis bahasa Arab (Nahwu Wadlih Jilid 1-2) dan membedah matan Aqidah Al-Ushul Ats-Tsalatsah.',
                'cp' => 'Mahasantri mampu mengi\'rab naskah berbahasa Arab gundul dan memahami prinsip akidah ahlussunnah wal jama\'ah.',
                'tujuan' => 'Mencetak kader da\'i yang kokoh dalam pondasi bahasa Arab dan kemurnian pemahaman tauhid.',
                'metode' => 'Sorogan, Bandongan, I\'rab Naskah Klasik, Diskusi Ilmiah Muamalah',
                'model' => 'Dirasah Ilmiyyah Mahad Aly Klasik-Modern',
                'media' => 'Kitab Turats Matan Al-Ajurrumiyyah, Papan Silsilah I\'rab, Rekaman Syaikh Kibar',
                'sumber' => 'Kitab An-Nahwu Al-Wadlih & Syarah Tsalatsatul Ushul Syaikh Ibnu Utsaimin',
                'target_nilai' => 80.00,
                'target_kehadiran' => 95.00,
                'target_hafalan' => 'Matan Al-Ajurrumiyyah Bab Kalam s/d I\'rab & Matan Ushul Tsalatsah Lengkap',
                'target_proyek' => 'Penyusunan Risalah Ringkas Kaidah I\'rab Kalimat Arabiyah dari Surat Al-Kahfi',
                'bobot' => ['tugas' => 20.00, 'quiz' => 15.00, 'projek' => 25.00, 'uts' => 20.00, 'uas' => 20.00],
                'topik_mingguan' => [
                    1 => 'Muqaddimah Dirasah Mahad: Kedudukan Bahasa Arab sebagai Kunci Membuka Khazanah Ilmu Syar\'i',
                    2 => 'Nahwu: Pembagian Jumlah Mufidah dan Komponen Penyusun Kata (Isim, Fi\'il, Harf)',
                    3 => 'Nahwu: Pembagian Fi\'il Berdasarkan Waktu (Madhi, Mudhari\', Amr) dan Contoh Penggunaannya',
                    4 => 'Nahwu: Al-Fa\'il: Pengertian, Kedudukan I\'rab Rafa\', dan Macam-Macam Fa\'il Isim Dzhahir',
                    5 => 'Nahwu: Al-Maf\'ul Bih: Pengertian, Kedudukan I\'rab Nashab, dan Karakteristik Kalimat',
                    6 => 'Aqidah: Tsalatsatul Ushul: Empat Masalah Wajib (Ilmu, Amal, Da\'wah, Sabar)',
                    7 => 'Aqidah: Tiga Landasan Utama: Mengenal Allah, Mengenal Agama Islam, Mengenal Nabi Muhammad',
                    8 => 'Imtihan Nisfi Mahad: Ujian Lisan I\'rab Naskah & Ujian Tulis Pemahaman Ushul Tsalatsah',
                    9 => 'Nahwu: Al-Mubtada\' wal Khabar: Kedudukan I\'rab dan Macam-Macam Khabar (Mufrod & Ghairu Mufrod)',
                    10 => 'Nahwu: Masuknya Kaana wa Akhawatuha pada Jumlah Ismiyyah serta Dampak Perubahan I\'rabnya',
                    11 => 'Nahwu: Masuknya Inna wa Akhawatuha pada Jumlah Ismiyyah dan Penjelasan Makna Masing-Masing',
                    12 => 'Nahwu: Isim Majrur bi Harfil Jar dan Isim Majrur bil Idhafah (Mudhaf wa Mudhaf Ilaih)',
                    13 => 'Nahwu: Na\'at wal Man\'ut: Kaidah Kesesuaian Sifat dalam I\'rab, Ifrad, Ta\'nits, dan Ta\'rif',
                    14 => 'Aqidah: Landasan Pertama: Dalil Rububiyyah, Uluhiyyah, dan Asma\' wa Shifat Allah',
                    15 => 'Sidang Halaqah: Praktik Membaca dan Mengi\'rab Satu Halaman Kitab Tanpa Harakat',
                    16 => 'Imtihan Akhir Semester Mahad: Ujian Komprehensif Lisan dan Tulis Mahasantri',
                ],
            ],
        ];

        $totalModulCreated = 0;
        $totalDetailCreated = 0;

        foreach ($units as $unit) {
            $config = $unitConfigs[$unit->code] ?? null;

            // Jika tidak ada konfigurasi spesifik, buatkan fallback cerdas berdasarkan nama/jenjang unit
            if (! $config) {
                $codeUpper = strtoupper($unit->code);
                $nameUpper = strtoupper($unit->name);

                if (str_contains($codeUpper, 'TK') || str_contains($nameUpper, 'TK')) {
                    $config = $unitConfigs['TKIT-01'];
                    $config['kode_sub'] = $unit->code . '-ADAB';
                    $config['nama_modul'] = 'Program Semester 1 Adab & Karakter Islami - ' . $unit->name;
                    $config['jenjang'] = 'TK';
                } elseif (str_contains($codeUpper, 'TAUD') || str_contains($nameUpper, 'TAUD')) {
                    $config = $unitConfigs['TAUD-01'];
                    $config['kode_sub'] = $unit->code . '-TAHFIZH';
                    $config['nama_modul'] = 'Program Semester 1 Tahfizh SaQu Anak Usia Dini - ' . $unit->name;
                    $config['jenjang'] = 'TAUD';
                } elseif (str_contains($codeUpper, 'SD') || str_contains($nameUpper, 'SD')) {
                    $config = $unitConfigs['SDIT-01'];
                    $config['kode_sub'] = $unit->code . '-IPAS';
                    $config['nama_modul'] = 'Modul Semester 1 Pembelajaran Tematik Terpadu - ' . $unit->name;
                    $config['jenjang'] = 'SDIT';
                } elseif (str_contains($codeUpper, 'MIT') || str_contains($nameUpper, 'MIT')) {
                    $config = $unitConfigs['MIT-01'];
                    $config['kode_sub'] = $unit->code . '-TAHFIZH';
                    $config['nama_modul'] = 'Modul Semester 1 Tahfizh & Syariah - ' . $unit->name;
                    $config['jenjang'] = 'MIT';
                } elseif (str_contains($codeUpper, 'SMP') || str_contains($nameUpper, 'SMP')) {
                    $config = $unitConfigs['SMPIT-01'];
                    $config['kode_sub'] = $unit->code . '-IPA';
                    $config['nama_modul'] = 'Modul Semester 1 Sains & Karakter Terpadu - ' . $unit->name;
                    $config['jenjang'] = 'SMP';
                } elseif (str_contains($codeUpper, 'SMA') || str_contains($nameUpper, 'SMA')) {
                    $config = $unitConfigs['SMAIT-01'];
                    $config['kode_sub'] = $unit->code . '-FISIKA';
                    $config['nama_modul'] = 'Modul Semester 1 Sains Lanjutan & Karakter - ' . $unit->name;
                    $config['jenjang'] = 'SMA';
                } else {
                    $config = $unitConfigs['PONPES-PA'];
                    $config['kode_sub'] = $unit->code . '-TAHFIZH';
                    $config['nama_modul'] = 'Program Semester 1 Dirasah Islamiyyah & Tahfizh - ' . $unit->name;
                    $config['jenjang'] = 'PONPES';
                }
            }

            // Temukan kelas di unit ini
            $kelas = Kelas::where('unit_pendidikan_id', $unit->id)->orderBy('nama_kelas')->first();
            if (! $kelas) {
                // Buat kelas jika belum ada sama sekali
                $kelas = Kelas::firstOrCreate(
                    [
                        'unit_pendidikan_id' => $unit->id,
                        'nama_kelas' => 'Kelas 1 Reguler',
                    ],
                    [
                        'kode_kelas' => 'KLS-' . $unit->code . '-01',
                        'tingkat' => 1,
                        'kapasitas' => 25,
                        'status' => 'Aktif',
                    ]
                );
            }

            // Temukan mapel yang cocok berdasarkan kata kunci config
            $subject = null;
            if (! empty($config['mapel_keywords'])) {
                $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                foreach ($config['mapel_keywords'] as $kw) {
                    $subject = Subject::where('unit_pendidikan_id', $unit->id)
                        ->where(function ($q) use ($kw, $likeOp) {
                            $q->where('name', $likeOp, '%' . $kw . '%')
                              ->orWhere('nama_mapel', $likeOp, '%' . $kw . '%');
                        })
                        ->first();
                    if ($subject) break;
                }
            }

            // Fallback subject jika kata kunci belum ketemu
            if (! $subject) {
                $subject = Subject::where('unit_pendidikan_id', $unit->id)->first() ?? Subject::first();
            }

            // Temukan guru di unit ini
            $guru = Employee::where('unit_id', $unit->id)->first() ?? Employee::first();

            $kodeModul = 'MDS-20261-' . $config['kode_sub'];

            $dataModul = [
                'tahun_ajaran_id' => $ta->id,
                'semester_id' => $sem->id,
                'unit_pendidikan_id' => $unit->id,
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $subject->id,
                'guru_id' => $guru->id,
                'kode_modul' => $kodeModul,
                'nama_modul' => $config['nama_modul'],
                'jenjang' => $config['jenjang'],
                'kurikulum' => $config['kurikulum'],
                'status' => 'Aktif',
                'atp' => $config['atp'],
                'cp' => $config['cp'],
                'tujuan_pembelajaran' => $config['tujuan'],
                'alokasi_jam' => $config['alokasi_jam'],
                'jumlah_pertemuan' => $config['jumlah_pertemuan'],
                'metode_pembelajaran' => $config['metode'],
                'model_pembelajaran' => $config['model'],
                'media_pembelajaran' => $config['media'],
                'sumber_belajar' => $config['sumber'],
                'target_nilai_minimum' => $config['target_nilai'],
                'target_kehadiran' => $config['target_kehadiran'],
                'target_hafalan' => $config['target_hafalan'],
                'target_proyek' => $config['target_proyek'],
                'berlaku_mulai' => '2026-07-15',
                'berlaku_sampai' => '2026-12-20',
                'ditampilkan_di_portal_ortu' => true,
                'ditampilkan_di_aplikasi_siswa' => true,
                'arsip_otomatis' => false,
                'bobot_tugas' => $config['bobot']['tugas'],
                'bobot_quiz' => $config['bobot']['quiz'],
                'bobot_projek' => $config['bobot']['projek'],
                'bobot_uts' => $config['bobot']['uts'],
                'bobot_uas' => $config['bobot']['uas'],
            ];

            $modul = ModulSemester::withTrashed()->where('kode_modul', $kodeModul)->first();
            if ($modul) {
                if ($modul->trashed()) {
                    $modul->restore();
                }
                $modul->update($dataModul);
            } else {
                $modul = ModulSemester::create($dataModul);
            }

            $totalModulCreated++;

            // Rincian 16 Pekan Materi Belajar
            $topikMingguan = $config['topik_mingguan'] ?? [];
            for ($pekan = 1; $pekan <= 16; $pekan++) {
                $topik = $topikMingguan[$pekan] ?? ("Materi Pekan ke-{$pekan}: Pendalaman dan Pengayaan Kurikulum");
                $ket = $pekan === 8 ? 'Asesmen Sumatif Tengah Semester (PTS)' :
                      ($pekan === 16 ? 'Asesmen Sumatif Akhir Semester (PAS)' :
                      ($pekan === 1 ? 'Kontrak Belajar & Asesmen Diagnostik Awal' : 'Pertemuan Pembelajaran Efektif'));

                ModulSemesterDetail::updateOrCreate(
                    [
                        'modul_semester_id' => $modul->id,
                        'minggu' => $pekan,
                    ],
                    [
                        'materi' => $topik,
                        'atp' => $modul->atp,
                        'cp' => $modul->cp,
                        'jp' => $config['jp_per_minggu'] ?? 4,
                        'keterangan' => $ket,
                    ]
                );
                $totalDetailCreated++;
            }
        }

        $this->command?->info("Berhasil membuat simulasi {$totalModulCreated} Modul Semester dan {$totalDetailCreated} Rincian Pekan Materi untuk seluruh 15 Unit Pendidikan!");
    }
}
