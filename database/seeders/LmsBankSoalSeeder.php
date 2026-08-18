<?php

namespace Database\Seeders;

use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use Illuminate\Database\Seeder;

class LmsBankSoalSeeder extends Seeder
{
    public function run(): void
    {
        $kisi = LmsKisiKisi::first();
        if (! $kisi) {
            return;
        }

        $kisiId = $kisi->id;
        $mapelId = $kisi->mata_pelajaran_id;

        $samples = [
            // 1. Pilihan Ganda (PG)
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'PG-001',
                'pertanyaan' => 'Apa landasan utama Pancasila sebagai dasar negara Indonesia?',
                'tipe_soal' => 'pg',
                'opsi_a' => 'UUD 1945 Pembukaan',
                'opsi_b' => 'Tap MPR No. III/2000',
                'opsi_c' => 'Proklamasi Kemerdekaan',
                'opsi_d' => 'Bhinneka Tunggal Ika',
                'opsi_e' => 'Keputusan Presiden',
                'kunci_jawaban' => 'A',
                'pembahasan' => 'Pancasila disahkan pada tanggal 18 Agustus 1945 dan tercantum pada Pembukaan UUD 1945.',
                'poin' => 2.50,
                'tingkat_kesulitan' => 'mudah',
                'indikator' => 'Peserta didik mampu mengidentifikasi landasan Pancasila',
                'status' => true,
            ],
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'PG-002',
                'pertanyaan' => 'Di bawah ini yang merupakan contoh penerapan sila ke-1 dalam kehidupan sekolah adalah...',
                'tipe_soal' => 'pg',
                'opsi_a' => 'Menghormati teman yang sedang beribadah',
                'opsi_b' => 'Menjaga kebersihan ruang kelas bersama',
                'opsi_c' => 'Mengikuti musyawarah pemilihan ketua OSIS',
                'opsi_d' => 'Menghormati pendapat teman lain',
                'opsi_e' => 'Gotong royong membersihkan taman',
                'kunci_jawaban' => 'A',
                'pembahasan' => 'Menghormati ibadah sesama merupakan cerminan Ketuhanan Yang Maha Esa (Sila ke-1).',
                'poin' => 2.50,
                'tingkat_kesulitan' => 'sedang',
                'indikator' => 'Peserta didik mampu memberikan contoh pengalaman sila pertama',
                'status' => true,
            ],

            // 2. Essay / Esai
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'ESAI-001',
                'pertanyaan' => 'Jelaskan perbedaan antara Hak Asasi Manusia (HAM) dan Kewajiban Asasi Manusia (KAM) serta berikan contohnya dalam kehidupan sehari-hari!',
                'tipe_soal' => 'esai',
                'opsi_a' => null,
                'opsi_b' => null,
                'opsi_c' => null,
                'opsi_d' => null,
                'opsi_e' => null,
                'kunci_jawaban' => 'Kunci Jawaban & Rubrik: HAM adalah hak dasar yang dimiliki sejak lahir (contoh: hak hidup, mendapat pendidikan). KAM adalah beban/tanggung jawab yang harus dilaksanakan (contoh: mematuhi hukum, membayar pajak).',
                'pembahasan' => 'Penilaian didasarkan pada ketepatan definisi (50%) dan kejelasan contoh (50%).',
                'poin' => 10.00,
                'tingkat_kesulitan' => 'sulit',
                'indikator' => 'Peserta didik mampu menganalisis hubungan HAM dan KAM',
                'status' => true,
            ],

            // 3. Benar / Salah
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'BS-001',
                'pertanyaan' => 'UUD 1945 disahkan oleh Badan Penyelidik Usaha-usaha Persiapan Kemerdekaan Indonesia (BPUPKI) pada tanggal 17 Agustus 1945.',
                'tipe_soal' => 'benar_salah',
                'opsi_a' => null,
                'opsi_b' => null,
                'opsi_c' => null,
                'opsi_d' => null,
                'opsi_e' => null,
                'kunci_jawaban' => 'Salah',
                'pembahasan' => 'UUD 1945 disahkan oleh PPKI (bukan BPUPKI) pada tanggal 18 Agustus 1945.',
                'poin' => 2.00,
                'tingkat_kesulitan' => 'sedang',
                'indikator' => 'Peserta didik mampu memverifikasi fakta sejarah perumusan UUD 1945',
                'status' => true,
            ],
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'BS-002',
                'pertanyaan' => 'Sistem pemerintahan Indonesia menganut sistem Presidensial di mana Presiden bertindak sebagai kepala negara sekaligus kepala pemerintahan.',
                'tipe_soal' => 'benar_salah',
                'opsi_a' => null,
                'opsi_b' => null,
                'opsi_c' => null,
                'opsi_d' => null,
                'opsi_e' => null,
                'kunci_jawaban' => 'Benar',
                'pembahasan' => 'Sesuai Pasal 4 ayat (1) UUD 1945, Presiden Republik Indonesia memegang kekuasaan pemerintahan menurut UUD.',
                'poin' => 2.00,
                'tingkat_kesulitan' => 'mudah',
                'indikator' => 'Peserta didik dapat memahami bentuk pemerintahan Indonesia',
                'status' => true,
            ],

            // 4. Menjodohkan
            [
                'kisi_kisi_id' => $kisiId,
                'mata_pelajaran_id' => $mapelId,
                'kode_soal' => 'JO-001',
                'pertanyaan' => 'Jodohkan lambang Sila Pancasila di kolom kiri dengan makna silanya di kolom kanan dengan tepat!',
                'tipe_soal' => 'menjodohkan',
                'opsi_a' => null,
                'opsi_b' => null,
                'opsi_c' => null,
                'opsi_d' => null,
                'opsi_e' => null,
                'kunci_jawaban' => json_encode([
                    ['kiri' => 'Bintang Emas', 'kanan' => 'Ketuhanan Yang Maha Esa'],
                    ['kiri' => 'Rantai Emas', 'kanan' => 'Kemanusiaan yang Adil dan Beradab'],
                    ['kiri' => 'Pohon Beringin', 'kanan' => 'Persatuan Indonesia'],
                    ['kiri' => 'Kepala Banteng', 'kanan' => 'Kerakyatan yang Dipimpin oleh Hikmat Kebijaksanaan'],
                    ['kiri' => 'Padi dan Kapas', 'kanan' => 'Keadilan Sosial bagi Seluruh Rakyat Indonesia'],
                ]),
                'pembahasan' => 'Pasangan lambang dan makna Sila Pancasila sesuai Garuda Pancasila.',
                'poin' => 5.00,
                'tingkat_kesulitan' => 'sedang',
                'indikator' => 'Peserta didik mampu menjodohkan simbol dan sila Pancasila',
                'status' => true,
            ],
        ];

        foreach ($samples as $sample) {
            LmsBankSoal::updateOrCreate(
                ['kode_soal' => $sample['kode_soal']],
                $sample
            );
        }
    }
}
