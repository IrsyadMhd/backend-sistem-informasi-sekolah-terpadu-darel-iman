<?php

namespace Database\Seeders;

use App\Models\HelpFaq;
use Illuminate\Database\Seeder;

class HelpFaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'category' => 'Keamanan & Akun',
                'question' => 'Bagaimana cara mengganti kata sandi (password) akun SIMSIT saya?',
                'answer' => "Anda dapat mengubah kata sandi kapan saja demi keamanan akun:\n1. Buka menu Profil Akun di pojok kanan atas topbar.\n2. Pilih tab Keamanan & Kata Sandi.\n3. Masukkan kata sandi lama, lalu ketikkan kata sandi baru minimal 8 karakter.\n4. Klik Simpan Perubahan Kata Sandi.\n\n*Jika Anda lupa kata sandi lama, silakan hubungi Administrator TU Sekolah untuk mengajukan reset password.*",
                'tags' => ['password', 'ganti password', 'lupa password', 'profil', 'keamanan', 'akun'],
                'action_url' => '/dashboard/profil-akun',
                'action_label' => 'Buka Profil Akun',
                'sort_order' => 1,
            ],
            [
                'category' => 'Absensi Digital',
                'question' => 'Bagaimana prosedur penanganan siswa yang tidak melakukan tap kartu di gerbang?',
                'answer' => "Jika siswa lupa membawa kartu NFC/QR atau gerbang sedang dalam perawatan:\n1. Wali Kelas atau TU dapat masuk ke menu Absensi > Koreksi & Permohonan Izin.\n2. Pilih nama siswa yang bersangkutan pada tanggal berjalan.\n3. Ubah status kehadiran secara manual menjadi Hadir (Koreksi TU/Walas) atau masukkan keterangan Izin/Sakit.\n4. Simpan data agar sistem merekap kehadiran secara akurat pada laporan harian.",
                'tags' => ['absensi', 'gerbang', 'kartu nfc', 'qr code', 'koreksi', 'walas', 'tu'],
                'action_url' => '/absensi/koreksi',
                'action_label' => 'Halaman Koreksi Absensi',
                'sort_order' => 2,
            ],
            [
                'category' => 'Tahfizh & Mutabaah',
                'question' => 'Bagaimana cara input setoran Ziyadah & Murajaah harian santri/siswa?',
                'answer' => "Guru Tahfizh atau Musyrif dapat mencatat hafalan siswa dengan langkah berikut:\n1. Akses menu Tahfizh > Input Setoran.\n2. Pilih halaqah, kelas, dan nama santri.\n3. Pilih Jenis Setoran (Ziyadah hafalan baru atau Muraja'ah pengulangan).\n4. Tentukan Surah, Ayat Awal hingga Ayat Akhir, Nilai/Predikat Kelancaran, serta Catatan Tajwid.\n5. Klik Simpan Setoran. Wali murid akan otomatis menerima pemberitahuan di Portal Orang Tua.",
                'tags' => ['tahfizh', 'setoran', 'ziyadah', 'murajaah', 'quran', 'halaqah', 'musyrif'],
                'action_url' => '/dashboard/tahfizh',
                'action_label' => 'Kelola Tahfizh',
                'sort_order' => 3,
            ],
            [
                'category' => 'Akademik & LMS',
                'question' => 'Bagaimana cara mencetak Rapor Pembelajaran & LMS siswa?',
                'answer' => "Untuk mencetak Rapor Semester maupun Rapor Perkembangan:\n1. Wali Kelas atau Divisi Akademik masuk ke menu Akademik / LMS > Nilai & Rapor.\n2. Pilih Tahun Ajaran, Semester, dan Rombel Kelas.\n3. Klik tombol Pratinjau Rapor pada siswa yang diinginkan atau gunakan tombol Cetak Masal (PDF).\n4. Pastikan semua nilai formatif, sumatif, dan catatan wali kelas sudah terisi lengkap.",
                'tags' => ['rapor', 'lms', 'cetak pdf', 'nilai', 'akademik', 'semester', 'walas'],
                'action_url' => '/dashboard/lms/rapor',
                'action_label' => 'Menu Nilai & Rapor',
                'sort_order' => 4,
            ],
            [
                'category' => 'Kesiswaan',
                'question' => 'Bagaimana cara melakukan pengajuan Mutasi Siswa masuk/keluar?',
                'answer' => "Proses Administrasi Mutasi Siswa dikelola melalui portal Tata Usaha & Yayasan:\n1. Masuk ke Kesiswaan > Data Siswa atau Dashboard Yayasan > Mutasi Siswa.\n2. Klik tombol Tambah Data Mutasi.\n3. Lengkapi nomor surat rekomendasi, alasan mutasi, dan sekolah tujuan/asal.\n4. Setelah disetujui Kepala Sekolah, status siswa akan otomatis disinkronisasi ke arsip alumni/keluar.",
                'tags' => ['mutasi', 'pindah sekolah', 'siswa baru', 'kesiswaan', 'tu', 'surat keluar'],
                'action_url' => '/dashboard/kelola-alumni',
                'action_label' => 'Kelola Alumni & Mutasi',
                'sort_order' => 5,
            ],
            [
                'category' => 'SDM & Kepegawaian',
                'question' => 'Bagaimana cara mendaftarkan akun pegawai/guru baru ke sistem?',
                'answer' => "Pendaftaran akun pegawai dilakukan terpusat oleh Admin SIMSIT / TU Yayasan:\n1. Buka Master Data > Pegawai & Guru.\n2. Klik tombol Tambah Pegawai Baru atau Import Masal (Excel/CSV).\n3. Isi data identitas (NIP, Nama Lengkap, No. WhatsApp, Unit Penempatan, dan Jabatan).\n4. Akun login akan otomatis dibuatkan dan kredensial sementara dikirimkan via WhatsApp terintegrasi.",
                'tags' => ['pegawai', 'guru', 'sdm', 'akun baru', 'nip', 'tu', 'admin'],
                'action_url' => '/dashboard/master/pegawai',
                'action_label' => 'Master Data Pegawai',
                'sort_order' => 6,
            ],
            [
                'category' => 'Akademik & LMS',
                'question' => 'Bagaimana mengunggah soal ujian CBT dari template berkas CSV?',
                'answer' => "Guru pengampu dapat mengimpor bank soal langsung dari berkas CSV:\n1. Masuk ke LMS > Bank Soal.\n2. Klik tombol Import Bank Soal di toolbar atas.\n3. Unduh berkas format acuan 'template_bank_soal.csv'.\n4. Isi pertanyaan, kunci jawaban, dan pilihan ganda lalu unggah berkas kembali.\n5. Sistem akan memvalidasi format dan memasukkan soal ke bank soal mata pelajaran.",
                'tags' => ['cbt', 'ujian', 'bank soal', 'impor', 'csv', 'guru'],
                'action_url' => '/dashboard/lms/bank-soal',
                'action_label' => 'Buka Bank Soal',
                'sort_order' => 7,
            ],
            [
                'category' => 'Keamanan & Akun',
                'question' => 'Bagaimana cara mengaktifkan Autentikasi Dua Faktor (2FA)?',
                'answer' => "Pengguna dapat mengamankan akun dengan 2FA melalui menu Profil:\n1. Buka Profil Akun > Keamanan & Akun.\n2. Buka tab Autentikasi Dua Faktor.\n3. Pindai kode QR menggunakan Google Authenticator atau aplikasi TOTP lainnya.\n4. Masukkan kode verifikasi 6-digit untuk mengaktifkan perlindungan ekstra.",
                'tags' => ['2fa', 'authenticator', 'keamanan', 'otp', 'google authenticator'],
                'action_url' => '/dashboard/profil-akun',
                'action_label' => 'Setup 2FA',
                'sort_order' => 8,
            ],
        ];

        foreach ($faqs as $faq) {
            HelpFaq::updateOrCreate(
                ['question' => $faq['question']],
                $faq
            );
        }
    }
}
