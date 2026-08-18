<?php

namespace Database\Seeders;

use App\Models\LmsMateri;
use App\Models\LmsMedia;
use Illuminate\Database\Seeder;

class LmsMediaSeeder extends Seeder
{
    public function run(): void
    {
        $materis = LmsMateri::all();

        if ($materis->isEmpty()) {
            $this->command->info('Tidak ada data LmsMateri untuk di-attach LmsMedia.');

            return;
        }

        $sampleMedia = [
            [
                'nama_file' => 'Modul Panduan Pembelajaran Interaktif.pdf',
                'tipe_file' => 'pdf',
                'url_eksternal' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'ukuran_bytes' => 2450000,
                'deskripsi' => 'Dokumen PDF panduan materi lengkap beserta latihan soal.',
                'urutan' => 1,
            ],
            [
                'nama_file' => 'Video Penjelasan Konsep Utama (YouTube)',
                'tipe_file' => 'video',
                'url_eksternal' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'durasi_detik' => 620,
                'deskripsi' => 'Video tutorial penjelasan bab 1 oleh tim pengajar.',
                'urutan' => 2,
            ],
            [
                'nama_file' => 'Audio Podcasts Penjelasan Rangkuman.mp3',
                'tipe_file' => 'audio',
                'url_eksternal' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'durasi_detik' => 372,
                'ukuran_bytes' => 5242880,
                'deskripsi' => 'Audio podcast podcast kajian materi singkat 6 menit.',
                'urutan' => 3,
            ],
            [
                'nama_file' => 'Slide Presentasi Pengajaran (PPTX)',
                'tipe_file' => 'ppt',
                'url_eksternal' => 'https://scholar.harvard.edu/files/todd_rogers/files/slide_presentation_template.pptx',
                'ukuran_bytes' => 4194304,
                'deskripsi' => 'Slide tayangan PowerPoint untuk guru dan siswa.',
                'urutan' => 4,
            ],
            [
                'nama_file' => 'Lembar Kerja Siswa (LKS Word)',
                'tipe_file' => 'word',
                'url_eksternal' => 'https://file-examples.com/wp-content/uploads/2017/02/file-sample_100kB.doc',
                'ukuran_bytes' => 102400,
                'deskripsi' => 'Template tugas mandiri format Microsoft Word.',
                'urutan' => 5,
            ],
            [
                'nama_file' => 'Infografis Ringkasan Peta Konsep.png',
                'tipe_file' => 'image',
                'url_eksternal' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=800',
                'ukuran_bytes' => 850000,
                'deskripsi' => 'Gambar infografis alur peta konsep bab pembelajaran.',
                'urutan' => 6,
            ],
            [
                'nama_file' => 'Tautan Simulasi Lab Interaktif (PhET)',
                'tipe_file' => 'link',
                'url_eksternal' => 'https://phet.colorado.edu/',
                'deskripsi' => 'Situs web luar untuk praktek simulasi interaktif.',
                'urutan' => 7,
            ],
        ];

        foreach ($materis as $materi) {
            // Check if media already exists for this materi
            if (LmsMedia::where('materi_id', $materi->id)->exists()) {
                continue;
            }

            // Add 2-3 sample media per materi
            $count = min(3, count($sampleMedia));
            for ($i = 0; $i < $count; $i++) {
                $item = $sampleMedia[$i];
                LmsMedia::create([
                    'materi_id' => $materi->id,
                    'nama_file' => $item['nama_file'],
                    'tipe_file' => $item['tipe_file'],
                    'url_eksternal' => $item['url_eksternal'] ?? null,
                    'ukuran_bytes' => $item['ukuran_bytes'] ?? null,
                    'durasi_detik' => $item['durasi_detik'] ?? null,
                    'deskripsi' => $item['deskripsi'] ?? null,
                    'urutan' => $item['urutan'] ?? 1,
                ]);
            }
        }
    }
}
