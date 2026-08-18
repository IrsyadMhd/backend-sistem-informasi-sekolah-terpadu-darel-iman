<?php

namespace Database\Seeders;

use App\Models\LmsModulAjar;
use App\Models\LmsReferensi;
use Illuminate\Database\Seeder;

class LmsReferensiSeeder extends Seeder
{
    public function run(): void
    {
        $modulAjars = LmsModulAjar::all();

        $sampleReferensi = [
            [
                'judul' => 'Buku Panduan Guru Kurikulum Merdeka Matematika SMP',
                'penulis' => 'Prof. Dr. Ahmad Dahlan & Tim Penyusun Kemendikbud',
                'penerbit' => 'Pusat Kurikulum dan Perbukuan Kemendikbudristek',
                'tahun' => 2022,
                'url' => 'https://buku.kemdikbud.go.id/katalog/buku-kurikulum-merdeka',
                'status' => 'aktif',
            ],
            [
                'judul' => 'Jurnal Kajian Metode Pembelajaran sains Terpadu',
                'penulis' => 'Dr. H. Mohammad Natsir, M.Ed.',
                'penerbit' => 'Pustaka Akademika Indonesia',
                'tahun' => 2023,
                'url' => 'https://journal.unesa.ac.id/index.php/jpps',
                'status' => 'aktif',
            ],
            [
                'judul' => 'Ensiklopedi Sains Terpadu & Peradaban Islam',
                'penulis' => 'Dr. Raghib As-Sirjani',
                'penerbit' => 'Pustaka Al-Kautsar',
                'tahun' => 2021,
                'url' => 'https://kautsar.co.id/ensiklopedi-sains',
                'status' => 'aktif',
            ],
            [
                'judul' => 'Modul Suplemen Pengayaan Literasi dan Numerasi',
                'penulis' => 'Tim Pengembang Kurikulum Sekolah',
                'penerbit' => 'Penerbit Erlangga',
                'tahun' => 2024,
                'url' => 'https://erlangga.co.id/katalog-buku-sekolah',
                'status' => 'aktif',
            ],
        ];

        // 1. Buat Data Referensi Umum (Tanpa Modul / modul_ajar_id = null)
        foreach ($sampleReferensi as $item) {
            if (! LmsReferensi::where('judul', $item['judul'])->whereNull('modul_ajar_id')->exists()) {
                LmsReferensi::create(array_merge($item, ['modul_ajar_id' => null]));
            }
        }

        // 2. Buat Referensi Spesifik Per Modul Ajar jika ada
        foreach ($modulAjars as $modul) {
            if (LmsReferensi::where('modul_ajar_id', $modul->id)->exists()) {
                continue;
            }

            foreach ($sampleReferensi as $item) {
                LmsReferensi::create([
                    'modul_ajar_id' => $modul->id,
                    'judul' => $item['judul'],
                    'penulis' => $item['penulis'],
                    'penerbit' => $item['penerbit'],
                    'tahun' => $item['tahun'],
                    'url' => $item['url'],
                    'status' => $item['status'],
                ]);
            }
        }
    }
}
