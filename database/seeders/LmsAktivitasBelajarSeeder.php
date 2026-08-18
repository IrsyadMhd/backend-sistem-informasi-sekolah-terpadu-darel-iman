<?php

namespace Database\Seeders;

use App\Models\LmsAktivitasBelajar;
use App\Models\LmsModulAjar;
use Illuminate\Database\Seeder;

class LmsAktivitasBelajarSeeder extends Seeder
{
    public function run(): void
    {
        $modul = LmsModulAjar::first();

        if (! $modul) {
            return;
        }

        $sampleAktivitas = [
            [
                'modul_ajar_id' => $modul->id,
                'nama_aktivitas' => 'Pembukaan & Orientasi Pembelajaran',
                'jenis_aktivitas' => 'Pendahuluan',
                'instruksi' => 'Guru mengucapkan salam, berdoa bersama, mengecek kehadiran siswa, serta menyampaikan tujuan pembelajaran dan apersepsi.',
                'waktu' => 15,
                'urutan' => 1,
                'status' => 'aktif',
            ],
            [
                'modul_ajar_id' => $modul->id,
                'nama_aktivitas' => 'Eksplorasi Konsep & Studi Kasus',
                'jenis_aktivitas' => 'Inti',
                'instruksi' => 'Siswa mengamati tayangan materi dan mendiskusikan contoh kasus nyata yang disajikan oleh guru secara berkelompok.',
                'waktu' => 35,
                'urutan' => 2,
                'status' => 'aktif',
            ],
            [
                'modul_ajar_id' => $modul->id,
                'nama_aktivitas' => 'Diskusi Kelompok & Lembar Kerja (LKPD)',
                'jenis_aktivitas' => 'Diskusi',
                'instruksi' => 'Peserta didik bekerja dalam kelompok kecil untuk menyelesaikan persoalan pada LKPD dan merumuskan kesimpulan.',
                'waktu' => 30,
                'urutan' => 3,
                'status' => 'aktif',
            ],
            [
                'modul_ajar_id' => $modul->id,
                'nama_aktivitas' => 'Presentasi Hasil Kerja Kelompok',
                'jenis_aktivitas' => 'Presentasi',
                'instruksi' => 'Perwakilan kelompok mempresentasikan hasil diskusi di depan kelas, kelompok lain memberikan tanggapan.',
                'waktu' => 25,
                'urutan' => 4,
                'status' => 'aktif',
            ],
            [
                'modul_ajar_id' => $modul->id,
                'nama_aktivitas' => 'Refleksi & Evaluasi Pembelajaran',
                'jenis_aktivitas' => 'Refleksi',
                'instruksi' => 'Guru bersama siswa menyimpulkan poin utama pembelajaran, memberikan apresiasi, dan memberikan tugas refleksi mandiri.',
                'waktu' => 15,
                'urutan' => 5,
                'status' => 'aktif',
            ],
        ];

        foreach ($sampleAktivitas as $item) {
            LmsAktivitasBelajar::firstOrCreate(
                [
                    'modul_ajar_id' => $item['modul_ajar_id'],
                    'urutan' => $item['urutan'],
                ],
                $item
            );
        }
    }
}
