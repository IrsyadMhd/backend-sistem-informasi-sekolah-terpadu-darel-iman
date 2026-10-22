<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\TahfizhKpiTarget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TahfizhKpiTargetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sdit1 = EducationUnit::where('code', 'SDIT-01')->first();
        $sdit2 = EducationUnit::where('code', 'SDIT-02')->first();
        $tkit1 = EducationUnit::where('code', 'TKIT-01')->first();
        $smpit1 = EducationUnit::where('code', 'SMPIT-01')->first();
        $smait1 = EducationUnit::where('code', 'SMAIT-01')->first();
        $ponpesPa = EducationUnit::where('code', 'PONPES-PA')->first();
        $ponpesPi = EducationUnit::where('code', 'PONPES-PI')->first();

        $defaultTargets = [
            // 1. TK / PAUD / TAUD
            [
                'education_unit_id' => $tkit1?->id,
                'unit_level' => 'TKIT',
                'grade_level' => 'ALL',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh TKIT & TAUD (Surah Pendek Ad-Duha s.d An-Naas)',
                'target_juz' => 'Juz 30 (Sebagian)',
                'target_surahs' => 22,
                'target_ayats' => 147,
                'minimum_kkm_ayats' => 70,
                'description' => 'Target hafalan santri usia dini mencakup 22 surah-surah pendek Juz 30 (mulai dari Surah An-Naas sampai Ad-Duha).',
                'is_active' => true,
            ],
            // 2. SDIT Kelas 1
            [
                'education_unit_id' => $sdit1?->id,
                'unit_level' => 'SDIT',
                'grade_level' => '1',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SDIT Kelas 1 (Juz 30 Penuh / Juz \'Amma)',
                'target_juz' => 'Juz 30',
                'target_surahs' => 37,
                'target_ayats' => 564,
                'minimum_kkm_ayats' => 350,
                'description' => 'Target penuntasan Juz 30 penuh (37 surah, 564 ayat) untuk santri Kelas 1 SDIT Fullday.',
                'is_active' => true,
            ],
            // 3. SDIT Kelas 2
            [
                'education_unit_id' => $sdit1?->id,
                'unit_level' => 'SDIT',
                'grade_level' => '2',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SDIT Kelas 2 (Pemantapan Juz 30 & Ziyadah Awal Juz 29)',
                'target_juz' => 'Juz 30 & Awal Juz 29',
                'target_surahs' => 40,
                'target_ayats' => 700,
                'minimum_kkm_ayats' => 564,
                'description' => 'Pemantapan mutqin Juz 30 dan ziyadah hafalan baru pada awal Juz 29 (Surah Al-Mulk s.d Al-Qalam).',
                'is_active' => true,
            ],
            // 4. SDIT Kelas 3 - 4
            [
                'education_unit_id' => null,
                'unit_level' => 'SDIT',
                'grade_level' => '3',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SDIT Kelas 3-4 (Juz 29 Penuh / Juz Tabarak)',
                'target_juz' => 'Juz 29 - 30 (2 Juz)',
                'target_surahs' => 48,
                'target_ayats' => 995,
                'minimum_kkm_ayats' => 800,
                'description' => 'Penuntasan 2 Juz (Juz 29 dan Juz 30) untuk santri kelas menengah SDIT.',
                'is_active' => true,
            ],
            // 5. SDIT Kelas 5 - 6
            [
                'education_unit_id' => null,
                'unit_level' => 'SDIT',
                'grade_level' => '5',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SDIT Kelas 5-6 (Juz 28 Penuh / Juz Qad Sami\'a)',
                'target_juz' => 'Juz 28 - 30 (3 Juz)',
                'target_surahs' => 57,
                'target_ayats' => 1370,
                'minimum_kkm_ayats' => 1100,
                'description' => 'Target kelulusan SDIT: Minimal 3 Juz mutqin (Juz 28, 29, dan 30).',
                'is_active' => true,
            ],
            // 6. SMPIT Fullday
            [
                'education_unit_id' => $smpit1?->id,
                'unit_level' => 'SMPIT',
                'grade_level' => 'ALL',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SMPIT Fullday (Target 3 Juz Lanjutan)',
                'target_juz' => '3 Juz (Juz 1-3 / Juz 28-30)',
                'target_surahs' => 57,
                'target_ayats' => 1500,
                'minimum_kkm_ayats' => 1000,
                'description' => 'Target kurikulum tahfizh reguler SMPIT Dar el-Iman sebanyak 3 juz hafalan Al-Qur\'an.',
                'is_active' => true,
            ],
            // 7. SMAIT Fullday
            [
                'education_unit_id' => $smait1?->id,
                'unit_level' => 'SMAIT',
                'grade_level' => 'ALL',
                'program_type' => 'fullday',
                'target_name' => 'Target Tahfizh SMAIT Fullday (Target 5 Juz Al-Qur\'an)',
                'target_juz' => '5 Juz',
                'target_surahs' => 65,
                'target_ayats' => 2500,
                'minimum_kkm_ayats' => 1800,
                'description' => 'Target kurikulum tahfizh SMAIT Dar el-Iman untuk persiapan studi lanjut dan beasiswa hafiz.',
                'is_active' => true,
            ],
            // 8. Ponpes Putra / Putri (Boarding Takhasus)
            [
                'education_unit_id' => $ponpesPa?->id,
                'unit_level' => 'PONPES',
                'grade_level' => 'ALL',
                'program_type' => 'boarding',
                'target_name' => 'Target Tahfizh Ponpes Boarding (Khatam 30 Juz Al-Qur\'an)',
                'target_juz' => '30 Juz Lengkap',
                'target_surahs' => 114,
                'target_ayats' => 6236,
                'minimum_kkm_ayats' => 4000,
                'description' => 'Target program Takhasus Asrama Pesantren Dar el-Iman untuk tuntas menghafal 30 Juz Al-Qur\'an.',
                'is_active' => true,
            ],
        ];

        foreach ($defaultTargets as $targetData) {
            TahfizhKpiTarget::updateOrCreate(
                [
                    'target_name' => $targetData['target_name'],
                ],
                array_merge($targetData, [
                    'id' => (string) Str::uuid(),
                ])
            );
        }
    }
}
