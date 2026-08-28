<?php

namespace Database\Seeders;

use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Models\LmsModulAjar;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsDiskusiSeeder extends Seeder
{
    public function run(): void
    {
        $modulAjarList = LmsModulAjar::all();
        $users = User::query()->orderBy('id')->get();
        $adminUser = User::query()->orderBy('id')->first();

        if ($modulAjarList->isEmpty()) {
            return;
        }

        $diskusis = [
            [
                'judul' => 'Diskusi Penerapan Hukum Newton dalam Kehidupan Sehari-hari',
                'deskripsi' => 'Anak-anak sekalian, silakan bagikan contoh penerapan Hukum I, II, dan III Newton yang sering kalian temui dalam aktivitas sehari-hari di rumah atau di sekolah!',
                'kategori' => 'Tanya Jawab',
                'is_pinned' => true,
                'is_closed' => false,
                'status' => 'aktif',
                'komentar' => [
                    [
                        'peran_pengirim' => 'Guru',
                        'konten' => 'Ingat untuk menyertakan analisis gaya yang bekerja pada contoh yang kalian berikan ya!',
                        'balasan' => [
                            [
                                'peran_pengirim' => 'Siswa',
                                'konten' => 'Ustadz, apakah saat kita mendorong meja yang berat tetapi tidak bergerak itu termasuk Hukum I Newton?',
                            ],
                            [
                                'peran_pengirim' => 'Guru',
                                'konten' => 'Tepat sekali! Karena percepatannya nol (benda tetap diam), total gaya resultan yang bekerja adalah nol.',
                            ],
                        ],
                    ],
                    [
                        'peran_pengirim' => 'Siswa',
                        'konten' => 'Contoh Hukum III Newton: Saat mendayung perahu, kita mendorong air ke belakang, dan air mendorong perahu ke depan.',
                    ],
                ],
            ],
            [
                'judul' => 'Tanya Jawab Persamaan Kuadrat & Metode Pemfaktoran',
                'deskripsi' => 'Ruang diskusi bagi siswa yang masih mengalami kesulitan dalam menentukan akar-akar persamaan kuadrat menggunakan rumus ABC atau pemfaktoran.',
                'kategori' => 'Tugas',
                'is_pinned' => false,
                'is_closed' => false,
                'status' => 'aktif',
                'komentar' => [
                    [
                        'peran_pengirim' => 'Siswa',
                        'konten' => 'Untuk soal nomor 5 pada modul, kapan kita lebih efisien memakai rumus kuadratik ketimbang pemfaktoran?',
                    ],
                    [
                        'peran_pengirim' => 'Guru',
                        'konten' => 'Gunakan rumus kuadratik (ABC) jika koefisien bilangan cukup besar atau diskriminannya bukan kuadrat sempurna.',
                    ],
                ],
            ],
            [
                'judul' => 'Refleksi Pembelajaran Berpikir Kritis & Algoritma',
                'deskripsi' => 'Silakan tuliskan kesan dan pemahaman terbaik yang kalian dapatkan setelah mempelajari bab Algoritma Pemrograman minggu ini.',
                'kategori' => 'Refleksi',
                'is_pinned' => false,
                'is_closed' => false,
                'status' => 'aktif',
                'komentar' => [
                    [
                        'peran_pengirim' => 'Siswa',
                        'konten' => 'Alhamdulillah, pemahaman saya tentang variabel dan percabangan IF-ELSE semakin jelas setelah latihan kasus kemarin.',
                    ],
                ],
            ],
        ];

        foreach ($diskusis as $index => $item) {
            $modul = $modulAjarList[$index % $modulAjarList->count()];

            $diskusi = LmsDiskusi::firstOrCreate(
                ['judul' => $item['judul']],
                [
                    'id' => Str::uuid()->toString(),
                    'modul_ajar_id' => $modul->id,
                    'deskripsi' => $item['deskripsi'],
                    'kategori' => $item['kategori'],
                    'tanggal_mulai' => now()->subDays(3),
                    'tanggal_tutup' => now()->addDays(10),
                    'is_pinned' => $item['is_pinned'],
                    'is_closed' => $item['is_closed'],
                    'status' => $item['status'],
                    'created_by' => $adminUser ? $adminUser->id : null,
                ]
            );

            if (isset($item['komentar']) && is_array($item['komentar'])) {
                foreach ($item['komentar'] as $kom) {
                    $randomUser = $users->count() > 1 ? $users->random() : $adminUser;
                    $komentarUtama = LmsDiskusiKomentar::withTrashed()->firstOrCreate(
                        [
                            'diskusi_id' => $diskusi->id,
                            'parent_id' => null,
                            'konten' => $kom['konten'],
                        ],
                        [
                            'id' => Str::uuid()->toString(),
                            'user_id' => $randomUser ? $randomUser->id : null,
                            'peran_pengirim' => $kom['peran_pengirim'],
                            'is_solution' => false,
                            'created_by' => $randomUser ? $randomUser->id : null,
                        ]
                    );
                    if ($komentarUtama->trashed()) {
                        $komentarUtama->restore();
                    }

                    if (isset($kom['balasan']) && is_array($kom['balasan'])) {
                        foreach ($kom['balasan'] as $balasan) {
                            $balasanUser = $users->count() > 1 ? $users->random() : $adminUser;
                            $komentarBalasan = LmsDiskusiKomentar::withTrashed()->firstOrCreate(
                                [
                                    'diskusi_id' => $diskusi->id,
                                    'parent_id' => $komentarUtama->id,
                                    'konten' => $balasan['konten'],
                                ],
                                [
                                    'id' => Str::uuid()->toString(),
                                    'user_id' => $balasanUser ? $balasanUser->id : null,
                                    'peran_pengirim' => $balasan['peran_pengirim'],
                                    'is_solution' => false,
                                    'created_by' => $balasanUser ? $balasanUser->id : null,
                                ]
                            );
                            if ($komentarBalasan->trashed()) {
                                $komentarBalasan->restore();
                            }
                        }
                    }
                }
            }
        }
    }
}
