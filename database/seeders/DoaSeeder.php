<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doas = [
            [
                'id' => 1,
                'nama' => 'Doa Sebelum Belajar',
                'grup' => 'Pendidikan & Ilmu',
                'ar' => 'رَضِيْتُ بِاللهِ رَبًّا وَبِالإِسْلاَمِ دِيْنًا وَبِمُحَمَّدٍ نَبِيًّا وَرَسُوْلاً رَبِّ زِدْنِيْ عِلْمًـا وَارْزُقْنِيْ فَهْمًـا',
                'tr' => 'Rodhitubillahi robba, wabil islami dina, wabi muhammadin nabiyya wa rasula. Robbi zidnii \'ilman warzuqnii fahman.',
                'idn' => 'Aku ridha Allah sebagai Tuhanku, Islam sebagai agamaku, dan Nabi Muhammad sebagai Nabi dan Rasulku. Ya Allah, tambahkanlah aku ilmu dan berikanlah aku rezeki pemahaman.',
                'tentang' => 'HR. Abu Dawud dan Tirmidzi.',
                'tag' => json_encode(['belajar', 'ilmu', 'pagi']),
            ],
            [
                'id' => 2,
                'nama' => 'Doa Sebelum Tidur',
                'grup' => 'Harian & Tidur',
                'ar' => 'بِاسْمِكَ اللَّهُمَّ أَحْيَا وَأَمُوْتُ',
                'tr' => 'Bismikallahumma ahyaa wa amuut.',
                'idn' => 'Dengan menyebut nama-Mu ya Allah, aku hidup dan aku mati.',
                'tentang' => 'HR. Bukhari dan Muslim.',
                'tag' => json_encode(['tidur', 'malam']),
            ],
            [
                'id' => 3,
                'nama' => 'Doa Bangun Tidur',
                'grup' => 'Harian & Tidur',
                'ar' => 'اَلْحَمْدُ لِلَّهِ الَّذِي أَحْيَانَا بَعْدَ مَا أَمَاتَنَا وَإِلَيْهِ النُّشُورُ',
                'tr' => 'Alhamdulillahilladzi ahyana ba\'da ma amatana wa ilaihin nusyur.',
                'idn' => 'Segala puji bagi Allah yang telah menghidupkan kami setelah mematikan kami dan hanya kepada-Nya kami dikembalikan.',
                'tentang' => 'HR. Bukhari dan Muslim.',
                'tag' => json_encode(['tidur', 'pagi']),
            ],
            [
                'id' => 4,
                'nama' => 'Doa Untuk Kedua Orang Tua',
                'grup' => 'Keluarga & Adab',
                'ar' => 'رَبِّ اغْفِرْ لِي وَلِوَالِدَيَّ وَارْحَمْهُمَا كَمَا رَبَّيَانِي صَغِيرًا',
                'tr' => 'Rabbighfir lii wa liwaalidayya warhamhumaa kamaa rabbayaanii shaghiiraa.',
                'idn' => 'Ya Rabbku, ampunilah aku dan kedua orang tuaku, dan kasihanilah keduanya sebagaimana mereka merawatku di waktu kecil.',
                'tentang' => 'QS. Al-Isra\': 24.',
                'tag' => json_encode(['orangtua', 'keluarga', 'harian']),
            ],
            [
                'id' => 5,
                'nama' => 'Doa Kebaikan Dunia dan Akhirat (Sapu Jagat)',
                'grup' => 'Umum & Perlindungan',
                'ar' => 'رَبَّنَا آتِنَا فِي الدُّنْيَا حَسَنَةً وَفِي الآخِرَةِ حَسَنَةً وَقِنَا عَذَابَ النَّارِ',
                'tr' => 'Rabbanaa aatinaa fid dunyaa hasanah wa fil aakhirati hasanah wa qinaa \'adzaaban naar.',
                'idn' => 'Ya Tuhan kami, berilah kami kebaikan di dunia dan kebaikan di akhirat dan lindungilah kami dari azab neraka.',
                'tentang' => 'QS. Al-Baqarah: 201.',
                'tag' => json_encode(['sapujagat', 'harian', 'doa']),
            ]
        ];

        foreach ($doas as $doa) {
            DB::table('doas')->updateOrInsert(
                ['id' => $doa['id']],
                array_merge($doa, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
