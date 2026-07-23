<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataDummySiswaSeeder extends Seeder
{
    public function run(): void
    {
        $tahunAjaranAktif = DB::table('academic_years')->first();
        if (! $tahunAjaranAktif) {
            $tahunAjaranId = DB::table('academic_years')->insertGetId([
                'name' => '2024/2025',
                'start_date' => '2024-07-01',
                'end_date' => '2025-06-30',
                'is_active' => true,
                'metadata' => json_encode(['sumber' => 'seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');
        } else {
            $tahunAjaranId = $tahunAjaranAktif->id;
        }

        $semesterAktif = DB::table('semesters')
            ->where('academic_year_id', $tahunAjaranId)
            ->orderBy('sequence')
            ->first();

        if (! $semesterAktif) {
            $semesterId = DB::table('semesters')->insertGetId([
                'academic_year_id' => $tahunAjaranId,
                'name' => 'Semester Ganjil',
                'sequence' => 1,
                'start_date' => '2024-07-01',
                'end_date' => '2024-12-31',
                'is_active' => true,
                'metadata' => json_encode(['sumber' => 'seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id');
        } else {
            $semesterId = $semesterAktif->id;
        }

        $daftarKelas = [
            [
                'name' => 'Kelas 6A',
                'level' => '6',
                'academic_year_id' => $tahunAjaranId,
                'semester_id' => $semesterId,
                'classroom_id' => null,
                'homeroom_teacher_id' => null,
                'metadata' => [
                    'wali_kelas' => 'Ust. Rahmat',
                    'tahun_ajaran' => '2024/2025',
                ],
            ],
            [
                'name' => 'Kelas 6B',
                'level' => '6',
                'academic_year_id' => $tahunAjaranId,
                'semester_id' => $semesterId,
                'classroom_id' => null,
                'homeroom_teacher_id' => null,
                'metadata' => [
                    'wali_kelas' => 'Ust. Hadi',
                    'tahun_ajaran' => '2024/2025',
                ],
            ],
            [
                'name' => 'Kelas 5A',
                'level' => '5',
                'academic_year_id' => $tahunAjaranId,
                'semester_id' => $semesterId,
                'classroom_id' => null,
                'homeroom_teacher_id' => null,
                'metadata' => [
                    'wali_kelas' => 'Ust. Nur',
                    'tahun_ajaran' => '2024/2025',
                ],
            ],
            [
                'name' => 'Kelas 5B',
                'level' => '5',
                'academic_year_id' => $tahunAjaranId,
                'semester_id' => $semesterId,
                'classroom_id' => null,
                'homeroom_teacher_id' => null,
                'metadata' => [
                    'wali_kelas' => 'Ust. Fikri',
                    'tahun_ajaran' => '2024/2025',
                ],
            ],
        ];

        $kelasMap = [];

        foreach ($daftarKelas as $kelas) {
            $modelKelas = SchoolClass::query()->updateOrCreate(
                ['name' => $kelas['name']],
                $kelas
            );

            $kelasMap[$kelas['name']] = $modelKelas->id;
        }

        $daftarSiswa = [
            [
                'nis' => '23001',
                'full_name' => 'Ahmad Zaky',
                'gender' => 'male',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2014, 5, 12)->toDateString(),
                'address' => 'Jl. Melati No. 12',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Ahmad Fauzi',
                        'nama_ibu' => 'Fatimah',
                        'no_hp' => '0812-0001-0001',
                    ],
                ],
            ],
            [
                'nis' => '23002',
                'full_name' => 'Aisyah Humaira',
                'gender' => 'female',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2014, 8, 7)->toDateString(),
                'address' => 'Jl. Kenanga No. 8',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Hendra',
                        'nama_ibu' => 'Nurlaila',
                        'no_hp' => '0812-0002-0002',
                    ],
                ],
            ],
            [
                'nis' => '23003',
                'full_name' => 'Muhammad Fadli',
                'gender' => 'male',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2014, 1, 20)->toDateString(),
                'address' => 'Jl. Mawar No. 3',
                'class_name' => 'Kelas 6B',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Rizal',
                        'nama_ibu' => 'Santi',
                        'no_hp' => '0812-0003-0003',
                    ],
                ],
            ],
            [
                'nis' => '23004',
                'full_name' => 'Nabila Putri',
                'gender' => 'female',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2015, 2, 14)->toDateString(),
                'address' => 'Jl. Anggrek No. 5',
                'class_name' => 'Kelas 5A',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2020/2021',
                    'orang_tua' => [
                        'nama_ayah' => 'Yusuf',
                        'nama_ibu' => 'Rahma',
                        'no_hp' => '0812-0004-0004',
                    ],
                ],
            ],
            [
                'nis' => '23005',
                'full_name' => 'Raihan Abiyyu',
                'gender' => 'male',
                'birth_place' => 'Padang',
                'birth_date' => Carbon::create(2015, 4, 11)->toDateString(),
                'address' => 'Jl. Flamboyan No. 10',
                'class_name' => 'Kelas 5B',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2020/2021',
                    'orang_tua' => [
                        'nama_ayah' => 'Fajar',
                        'nama_ibu' => 'Murni',
                        'no_hp' => '0812-0005-0005',
                    ],
                ],
            ],
        ];

        foreach ($daftarSiswa as $siswa) {
            Student::query()->updateOrCreate(
                ['nis' => $siswa['nis']],
                [
                    'user_id' => null,
                    'parent_id' => null,
                    'class_id' => $kelasMap[$siswa['class_name']] ?? null,
                    'full_name' => $siswa['full_name'],
                    'gender' => $siswa['gender'],
                    'birth_place' => $siswa['birth_place'],
                    'birth_date' => $siswa['birth_date'],
                    'address' => $siswa['address'],
                    'is_active' => $siswa['is_active'],
                    'metadata' => $siswa['metadata'],
                ]
            );
        }
    }
}
