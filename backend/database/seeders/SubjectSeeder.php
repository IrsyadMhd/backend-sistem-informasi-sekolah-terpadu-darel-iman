<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['code' => 'MAPEL-001', 'name' => 'Pendidikan Agama Islam (PAI)', 'description' => 'Mata pelajaran agama Islam terpadu'],
            ['code' => 'MAPEL-002', 'name' => 'Tahfizh Al-Qur\'an', 'description' => 'Hafalan dan muraja\'ah Al-Qur\'an'],
            ['code' => 'MAPEL-003', 'name' => 'Bahasa Arab', 'description' => 'Tata bahasa dan muhadatsah'],
            ['code' => 'MAPEL-004', 'name' => 'Bahasa Indonesia', 'description' => 'Literasi dan tatabahasa Indonesia'],
            ['code' => 'MAPEL-005', 'name' => 'Matematika', 'description' => 'Matematika dasar dan terapan'],
            ['code' => 'MAPEL-006', 'name' => 'Ilmu Pengetahuan Alam (IPA)', 'description' => 'Fisika, Biologi, Kimia dasar'],
            ['code' => 'MAPEL-007', 'name' => 'Ilmu Pengetahuan Sosial (IPS)', 'description' => 'Sejarah, Geografi, Ekonomi dasar'],
            ['code' => 'MAPEL-008', 'name' => 'Aqidah Akhlak', 'description' => 'Pembentukan karakter dan akidah Islam'],
            ['code' => 'MAPEL-009', 'name' => 'Fiqih', 'description' => 'Hukum dan ibadah praktis'],
            ['code' => 'MAPEL-010', 'name' => 'Hadits', 'description' => 'Pemahaman hadits shahih dan hafalan'],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->updateOrInsert(
                ['code' => $subject['code']],
                [
                    'id' => DB::raw('gen_random_uuid()'),
                    'code' => $subject['code'],
                    'name' => $subject['name'],
                    'description' => $subject['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
