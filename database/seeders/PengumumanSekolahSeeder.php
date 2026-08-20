<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\PengumumanSekolah;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PengumumanSekolahSeeder extends Seeder
{
    /**
     * Seed data berita dan pengumuman sekolah untuk seluruh unit pendidikan di database.
     */
    public function run(): void
    {
        if (! Schema::hasTable('pengumuman_sekolahs')) {
            $this->command?->warn('Tabel pengumuman_sekolahs belum ada. Jalankan migration terlebih dahulu.');
            return;
        }

        // 1. Dapatkan user penerbit (Superadmin / Admin / User pertama)
        $penerbit = User::where('email', 'superadmin@simsit.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        if (! $penerbit) {
            $this->command?->warn('PengumumanSekolahSeeder: Tidak ditemukan user penerbit di database.');
            return;
        }

        // 2. Tarik seluruh unit pendidikan yang ada di database
        $units = EducationUnit::where('is_active', true)->get();

        if ($units->isEmpty()) {
            $units = EducationUnit::all();
        }

        // Templat berita & pengumuman komprehensif untuk disebar ke seluruh unit
        $newsTemplates = [
            // Global Yayasan
            [
                'judul' => 'Penerimaan Peserta Didik Baru (PPDB) T.A. 2026/2027 Resmi Dibuka',
                'kategori' => 'PPDB',
                'target_unit' => 'all',
                'target_unit_name' => 'Seluruh Unit (Yayasan)',
                'ringkasan' => 'Pendaftaran online siswa baru untuk seluruh jenjang TKIT, SDIT 01, SDIT 02, SMPIT, SMAIT, dan Pesantren Dar El-Iman.',
                'isi' => "Assalamu’alaikum Warahmatullahi Wabarakatuh.\n\nPendaftaran peserta didik baru Yayasan Dar El-Iman Padang Tahun Ajaran 2026/2027 telah resmi dibuka. Orang tua calon siswa dapat mengakses pendaftaran online melalui portal utama sekolah terpadu atau mendatangi sekretariat PPDB di unit masing-masing.\n\nPersyaratan administrasi:\n1. Mengisi formulir pendaftaran online\n2. Melampirkan Akta Kelahiran dan Kartu Keluarga\n3. Pasfoto terbaru calon siswa\n\nInformasi lebih lanjut dapat menghubungi Layanan Informasi Yayasan.",
                'gambar_url' => 'https://images.unsplash.com/photo-1577896851231-70ef18881754?w=800&auto=format&fit=crop&q=60',
            ],
            [
                'judul' => 'Sosialisasi Program Tahfizh Al-Qur’an & Mutaba’ah Yaumiyah Digital Yayasan',
                'kategori' => 'Kegiatan',
                'target_unit' => 'all',
                'target_unit_name' => 'Seluruh Unit (Yayasan)',
                'ringkasan' => 'Peluncuran sistem jurnal ibadah digital dan target hafalan Al-Qur’an terpadu seluruh sekolah.',
                'isi' => "Dalam rangka meningkatkan kualitas keislaman dan kedisiplinan ibadah siswa, Yayasan Dar El-Iman meluncurkan sistem pemantauan mutaba'ah digital terpadu.\n\nSistem ini terintegrasi langsung dengan portal orang tua untuk memberikan laporan hafalan dan kedisiplinan harian santri secara real-time.",
                'gambar_url' => 'https://images.unsplash.com/photo-1609599006353-e629aaabfeae?w=800&auto=format&fit=crop&q=60',
            ],
        ];

        // Seed berita spesifik untuk masing-masing unit pendidikan yang ada di database
        foreach ($units as $unit) {
            $unitId = String($unit->id);
            $unitName = $unit->name;
            $unitCode = $unit->code;

            $newsTemplates[] = [
                'judul' => "Jadwal Pelaksanaan Evaluasi Ujian & Kegiatan Pembelajaran {$unitName}",
                'kategori' => 'Akademik',
                'target_unit' => $unitId,
                'target_unit_name' => $unitName,
                'ringkasan' => "Informasi jadwal evaluasi akademik dan persiapan pembelajaran di {$unitName}.",
                'isi' => "Diberitahukan kepada seluruh siswa dan orang tua siswa {$unitName} bahwa pelaksanaan evaluasi belajar semester akan dilaksanakan sesuai dengan kalender akademik terpadu.\n\nHarap para siswa mempersiapkan diri dengan baik dan menjaga kesehatan.",
                'gambar_url' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=800&auto=format&fit=crop&q=60',
            ];

            $newsTemplates[] = [
                'judul' => "Capaian Prestasi Santri & Penghargaan Tahfizh Terbaik {$unitName}",
                'kategori' => 'Prestasi',
                'target_unit' => $unitId,
                'target_unit_name' => $unitName,
                'ringkasan' => "Selamat kepada para santri {$unitName} atas raihan medali emas olimpiade & wisuda tahfizh.",
                'isi' => "Alhamdulillah, santri {$unitName} berhasil mengukir prestasi gemilang dalam ajang perlombaan akademik serta wisuda tasmi' hafalan Al-Qur'an.\n\nSemoga capaian ini memotivasi seluruh peserta didik untuk terus berprestasi dan mengamalkan ilmu.",
                'gambar_url' => 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&auto=format&fit=crop&q=60',
            ];
        }

        // Insert ke database pengumuman_sekolahs
        DB::transaction(function () use ($newsTemplates, $penerbit) {
            foreach ($newsTemplates as $item) {
                // Gunakan firstOrCreate berdasarkan judul & target unit agar aman di-reseed
                $existing = PengumumanSekolah::where('judul_pengumuman', $item['judul'])->first();

                if (! $existing) {
                    PengumumanSekolah::create([
                        'id' => (string) Str::uuid(),
                        'judul_pengumuman' => $item['judul'],
                        'isi_pengumuman' => $item['isi'],
                        'target_peran' => ['Orang Tua', 'Siswa'],
                        'mulai_tampil' => now(),
                        'prioritas' => 1,
                        'status_aktif' => true,
                        'id_penerbit' => $penerbit->id,
                        'data_tambahan' => [
                            'unit_id' => $item['target_unit'],
                            'unit_name' => $item['target_unit_name'],
                            'kategori' => $item['kategori'],
                            'ringkasan' => $item['ringkasan'],
                            'gambar_url' => $item['gambar_url'],
                        ],
                    ]);
                }
            }
        });

        $count = PengumumanSekolah::count();
        $this->command?->info("Seeder PengumumanSekolahSeeder selesai! Total berita di DB: {$count}");
    }
}
