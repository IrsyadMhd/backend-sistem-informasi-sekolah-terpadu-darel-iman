<?php

namespace Database\Seeders;

use App\Models\IndikatorKinerjaUtama;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class IndikatorKinerjaUtamaSeeder extends Seeder
{
    /**
     * Seed database indikator kinerja utama secara dinamis dari schema database yang ada.
     */
    public function run(): void
    {
        if (! Schema::hasTable('indikator_kinerja_utamas')) {
            $this->command?->warn('Tabel indikator_kinerja_utamas belum ada. Jalankan migration terlebih dahulu.');

            return;
        }

        // 1. Dapatkan user penginput secara dinamis (Superadmin / First User) untuk Foreign Key id_penginput
        $penginput = User::query()->where('is_superadmin', true)->first()
            ?? User::query()->where('email', 'superadmin@simsit.sch.id')->first()
            ?? User::query()->first();

        if (! $penginput) {
            $this->command?->warn('IndikatorKinerjaUtamaSeeder: Tidak ditemukan user untuk id_penginput.');

            return;
        }

        $bulanSekarang = (int) date('n');
        $tahunSekarang = (int) date('Y');

        // 2. Daftar Indikator Kinerja Utama (IKU) Prioritas Operasional Sekolah Terpadu
        $dataIndikator = [
            [
                'kode_indikator' => 'IKU-GURU-HADIR-01',
                'nama_indikator' => 'Tingkat Kehadiran Guru Tepat Waktu Minimal 95%',
                'kategori_indikator' => 'Kedisiplinan & Guru',
                'nilai' => 88.50,
                'target_nilai' => 95.00,
                'satuan' => '%',
                'warna_hex' => '#F59E0B',
                'urutan_tampil' => 1,
                'data_tambahan' => [
                    'deskripsi' => 'Presensi harian guru di awal jam tatap muka pertama',
                    'rekomendasi' => 'Pemberian apresiasi kehadiran & pembinaan wali kelas pagi',
                    'prioritas' => 'tinggi',
                ],
            ],
            [
                'kode_indikator' => 'IKU-TAHFIZH-TARGET-01',
                'nama_indikator' => 'Rata-rata Setoran Hafalan Al-Qur\'an Harian per Santri',
                'kategori_indikator' => 'Tahfizh & Al-Qur\'an',
                'nilai' => 7.50,
                'target_nilai' => 10.00,
                'satuan' => 'Baris/Hari',
                'warna_hex' => '#EF4444',
                'urutan_tampil' => 2,
                'data_tambahan' => [
                    'deskripsi' => 'Target pencapaian setoran tilawah dan tasmi\' santri',
                    'rekomendasi' => 'Optimasi halaqah sore dan mutabaah yaumiyyah santri',
                    'prioritas' => 'tinggi',
                ],
            ],
            [
                'kode_indikator' => 'IKU-SISWA-DISIPLIN-01',
                'nama_indikator' => 'Ambang Batas Keterlambatan Masuk Kelas Santri di Bawah 5%',
                'kategori_indikator' => 'Kesiswaan',
                'nilai' => 8.20,
                'target_nilai' => 5.00,
                'satuan' => '%',
                'warna_hex' => '#F59E0B',
                'urutan_tampil' => 3,
                'data_tambahan' => [
                    'deskripsi' => 'Monitoring gerbang kedatangan santri dan ketepatan bel masuk',
                    'rekomendasi' => 'Pengetatan scan barcode gerbang & briefing wali santri',
                    'prioritas' => 'sedang',
                ],
            ],
            [
                'kode_indikator' => 'IKU-MANAJEMEN-DIVISI-01',
                'nama_indikator' => 'Kepatuhan Penyerahan Laporan Bulanan Seluruh Divisi Yayasan',
                'kategori_indikator' => 'Manajemen & Divisi',
                'nilai' => 75.00,
                'target_nilai' => 100.00,
                'satuan' => '%',
                'warna_hex' => '#6366F1',
                'urutan_tampil' => 4,
                'data_tambahan' => [
                    'deskripsi' => 'Realisasi penyerahan laporan evaluasi program kerja bulanan',
                    'rekomendasi' => 'Reminder sistem otomatis sebelum tanggal 5 tiap bulan',
                    'prioritas' => 'sedang',
                ],
            ],
            [
                'kode_indikator' => 'IKU-LMS-KURIKULUM-01',
                'nama_indikator' => 'Kelengkapan Unggah Modul Ajar & Silabus Semester Aktif',
                'kategori_indikator' => 'Kurikulum & LMS',
                'nilai' => 82.00,
                'target_nilai' => 100.00,
                'satuan' => '%',
                'warna_hex' => '#3B82F6',
                'urutan_tampil' => 5,
                'data_tambahan' => [
                    'deskripsi' => 'Verifikasi ketersediaan modul ajar digital pada portal guru',
                    'rekomendasi' => 'Supervisi akademik oleh tim kurikulum unit',
                    'prioritas' => 'sedang',
                ],
            ],
        ];

        // 3. Masukkan ke database secara idempotent menggunakan updateOrCreate
        foreach ($dataIndikator as $item) {
            IndikatorKinerjaUtama::query()->updateOrCreate(
                ['kode_indikator' => $item['kode_indikator']],
                [
                    'id' => Str::uuid()->toString(),
                    'nama_indikator' => $item['nama_indikator'],
                    'kategori_indikator' => $item['kategori_indikator'],
                    'nilai' => $item['nilai'],
                    'target_nilai' => $item['target_nilai'],
                    'satuan' => $item['satuan'],
                    'bulan_periode' => $bulanSekarang,
                    'tahun_periode' => $tahunSekarang,
                    'warna_hex' => $item['warna_hex'],
                    'urutan_tampil' => $item['urutan_tampil'],
                    'id_penginput' => $penginput->id,
                    'data_tambahan' => $item['data_tambahan'],
                ]
            );
        }

        $this->command?->info('IndikatorKinerjaUtamaSeeder: Berhasil menyemai data indikator kinerja utama ke database.');
    }
}
