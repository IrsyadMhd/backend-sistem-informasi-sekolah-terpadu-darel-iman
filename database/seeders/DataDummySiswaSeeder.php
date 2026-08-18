<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataDummySiswaSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure academic year exists (idempotent)
        $tahunAjaranAktif = DB::table('academic_years')->where('name', '2024/2025')->first();
        if (! $tahunAjaranAktif) {
            $tahunAjaranAktif = DB::table('academic_years')->first();
        }
        if (! $tahunAjaranAktif) {
            $tahunAjaranId = (string) Str::uuid();
            DB::table('academic_years')->updateOrInsert(
                ['name' => '2024/2025'],
                [
                    'id' => $tahunAjaranId,
                    'start_date' => '2024-07-01',
                    'end_date' => '2025-06-30',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } else {
            $tahunAjaranId = $tahunAjaranAktif->id;
        }

        // Ensure semester exists (idempotent)
        $semesterAktif = DB::table('semesters')
            ->where('academic_year_id', $tahunAjaranId)
            ->orderBy('sequence')
            ->first();

        if (! $semesterAktif) {
            $semesterId = (string) Str::uuid();
            DB::table('semesters')->updateOrInsert(
                ['academic_year_id' => $tahunAjaranId, 'sequence' => 1],
                [
                    'id' => $semesterId,
                    'name' => 'Semester Ganjil',
                    'start_date' => '2024-07-01',
                    'end_date' => '2024-12-31',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
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

        $unitPendidikanId = DB::table('education_units')->value('id');

        foreach ($daftarKelas as $kelas) {
            $modelKelas = Kelas::query()->updateOrCreate(
                ['kode_kelas' => 'K-'.$kelas['level'].'-'.str_replace(' ', '', $kelas['name'])],
                [
                    'unit_pendidikan_id' => $unitPendidikanId ?? (string) Str::uuid(),
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'semester_id' => $semesterId,
                    'jenjang' => 'SDIT',
                    'tingkat' => $kelas['level'],
                    'nama_kelas' => $kelas['name'],
                    'status' => 'Aktif',
                ]
            );

            DB::table('classes')->updateOrInsert(
                [
                    'academic_year_id' => $tahunAjaranId,
                    'semester_id' => $semesterId,
                    'name' => $kelas['name'],
                ],
                [
                    'id' => $modelKelas->id,
                    'level' => (string) $kelas['level'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
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
                'full_name' => 'Khadijah Nurul',
                'gender' => 'female',
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
            [
                'nis' => '23006',
                'full_name' => 'Raihan Ananda',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2015, 6, 15)->toDateString(),
                'address' => 'Jl. Harau No. 12',
                'class_name' => 'Kelas 5B',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2020/2021',
                    'orang_tua' => [
                        'nama_ayah' => 'Ananda Syahputra',
                        'nama_ibu' => 'Rina',
                        'no_hp' => '0852-1122-3344',
                    ],
                ],
            ],
            [
                'nis' => '23007',
                'full_name' => 'Zahra Annisa',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2014, 9, 21)->toDateString(),
                'address' => 'Jl. Sudirman No. 44',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Bambang',
                        'nama_ibu' => 'Annisa',
                        'no_hp' => '0812-7788-9911',
                    ],
                ],
            ],
            [
                'nis' => '23008',
                'full_name' => 'Umar Al-Faruq',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2014, 11, 5)->toDateString(),
                'address' => 'Jl. Tan Malaka No. 88',
                'class_name' => 'Kelas 6B',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Faruq',
                        'nama_ibu' => 'Siti',
                        'no_hp' => '0813-9988-7766',
                    ],
                ],
            ],
            [
                'nis' => '23009',
                'full_name' => 'Fatimah Az-Zahra',
                'gender' => 'female',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2015, 1, 30)->toDateString(),
                'address' => 'Jl. Tanjung Pati No. 3',
                'class_name' => 'Kelas 5A',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2020/2021',
                    'orang_tua' => [
                        'nama_ayah' => 'Zahra',
                        'nama_ibu' => 'Laila',
                        'no_hp' => '0811-3344-5566',
                    ],
                ],
            ],
            [
                'nis' => '23010',
                'full_name' => 'Hamzah Fansuri',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2014, 4, 18)->toDateString(),
                'address' => 'Jl. Tiakar No. 19',
                'class_name' => 'Kelas 6B',
                'is_active' => true,
                'metadata' => [
                    'status' => 'Aktif',
                    'tahun_masuk' => '2019/2020',
                    'orang_tua' => [
                        'nama_ayah' => 'Fansuri',
                        'nama_ibu' => 'Maimunah',
                        'no_hp' => '0812-4455-6677',
                    ],
                ],
            ],
            [
                'nis' => '23011',
                'full_name' => 'Bilal Ramadan',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2015, 3, 10)->toDateString(),
                'address' => 'Jl. Harau No. 25',
                'class_name' => 'Kelas 5A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2020/2021', 'orang_tua' => ['nama_ayah' => 'Ramadan', 'nama_ibu' => 'Syarifah', 'no_hp' => '0812-0011-0011']],
            ],
            [
                'nis' => '23012',
                'full_name' => 'Laila Fitriani',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2015, 7, 22)->toDateString(),
                'address' => 'Jl. Sudirman No. 10',
                'class_name' => 'Kelas 5B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2020/2021', 'orang_tua' => ['nama_ayah' => 'Fitriadi', 'nama_ibu' => 'Murni', 'no_hp' => '0812-0012-0012']],
            ],
            [
                'nis' => '23013',
                'full_name' => 'Zulkifli Mansur',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2014, 12, 1)->toDateString(),
                'address' => 'Jl. Tan Malaka No. 55',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2019/2020', 'orang_tua' => ['nama_ayah' => 'Mansur', 'nama_ibu' => 'Halimah', 'no_hp' => '0812-0013-0013']],
            ],
            [
                'nis' => '23014',
                'full_name' => 'Safiyah Nabila',
                'gender' => 'female',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2014, 5, 14)->toDateString(),
                'address' => 'Jl. Sarilamak No. 12',
                'class_name' => 'Kelas 6B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2019/2020', 'orang_tua' => ['nama_ayah' => 'Nabil', 'nama_ibu' => 'Zulfa', 'no_hp' => '0812-0014-0014']],
            ],
            [
                'nis' => '23015',
                'full_name' => 'Ali Imran Al-Hafizh',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2015, 9, 30)->toDateString(),
                'address' => 'Jl. Tanjung Pati No. 40',
                'class_name' => 'Kelas 5A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2020/2021', 'orang_tua' => ['nama_ayah' => 'Imran', 'nama_ibu' => 'Khadijah', 'no_hp' => '0812-0015-0015']],
            ],
            [
                'nis' => '23016',
                'full_name' => 'Maryam Salimah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2015, 11, 18)->toDateString(),
                'address' => 'Jl. A. Yani No. 90',
                'class_name' => 'Kelas 5B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2020/2021', 'orang_tua' => ['nama_ayah' => 'Salim', 'nama_ibu' => 'Raudhah', 'no_hp' => '0812-0016-0016']],
            ],
            [
                'nis' => '23017',
                'full_name' => 'Salman Al-Farisi',
                'gender' => 'male',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2014, 2, 8)->toDateString(),
                'address' => 'Jl. Pakan Sinayan No. 4',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2019/2020', 'orang_tua' => ['nama_ayah' => 'Farisi', 'nama_ibu' => 'Nurmala', 'no_hp' => '0812-0017-0017']],
            ],
            [
                'nis' => '23018',
                'full_name' => 'Hasan Al-Banna',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2014, 8, 19)->toDateString(),
                'address' => 'Jl. Koto Nan Ampek No. 77',
                'class_name' => 'Kelas 6B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2019/2020', 'orang_tua' => ['nama_ayah' => 'Banna', 'nama_ibu' => 'Suhaila', 'no_hp' => '0812-0018-0018']],
            ],
            [
                'nis' => '23019',
                'full_name' => 'Mutia Rahmah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2015, 1, 12)->toDateString(),
                'address' => 'Jl. Labuh Basilang No. 15',
                'class_name' => 'Kelas 5A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2020/2021', 'orang_tua' => ['nama_ayah' => 'Rahman', 'nama_ibu' => 'Husna', 'no_hp' => '0812-0019-0019']],
            ],
            [
                'nis' => '23020',
                'full_name' => 'Ilham Rabbani',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2014, 10, 25)->toDateString(),
                'address' => 'Jl. Dang Dung No. 88',
                'class_name' => 'Kelas 6A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2019/2020', 'orang_tua' => ['nama_ayah' => 'Rabbani', 'nama_ibu' => 'Kalsum', 'no_hp' => '0812-0020-0020']],
            ],
            [
                'nis' => '23021',
                'full_name' => 'Annisa Thahirah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2016, 4, 10)->toDateString(),
                'address' => 'Jl. Tiakar No. 33',
                'class_name' => 'Kelas 4A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2021/2022', 'orang_tua' => ['nama_ayah' => 'Thahir', 'nama_ibu' => 'Wardah', 'no_hp' => '0812-0021-0021']],
            ],
            [
                'nis' => '23022',
                'full_name' => 'Faruq Abdillah',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2016, 6, 17)->toDateString(),
                'address' => 'Jl. Harau No. 9',
                'class_name' => 'Kelas 4A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2021/2022', 'orang_tua' => ['nama_ayah' => 'Abdillah', 'nama_ibu' => 'Syamsiah', 'no_hp' => '0812-0022-0022']],
            ],
            [
                'nis' => '23023',
                'full_name' => 'Siti Nurhaliza',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2016, 8, 29)->toDateString(),
                'address' => 'Jl. Ibuh No. 22',
                'class_name' => 'Kelas 4B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2021/2022', 'orang_tua' => ['nama_ayah' => 'Halim', 'nama_ibu' => 'Zahra', 'no_hp' => '0812-0023-0023']],
            ],
            [
                'nis' => '23024',
                'full_name' => 'Usman Al-Qarni',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2016, 12, 5)->toDateString(),
                'address' => 'Jl. Tanjung Pati No. 11',
                'class_name' => 'Kelas 4B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2021/2022', 'orang_tua' => ['nama_ayah' => 'Qarni', 'nama_ibu' => 'Amina', 'no_hp' => '0812-0024-0024']],
            ],
            [
                'nis' => '23025',
                'full_name' => 'Ruqayyah Mufidah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2017, 2, 14)->toDateString(),
                'address' => 'Jl. Sicincin No. 18',
                'class_name' => 'Kelas 3A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2022/2023', 'orang_tua' => ['nama_ayah' => 'Mufid', 'nama_ibu' => 'Hasanah', 'no_hp' => '0812-0025-0025']],
            ],
            [
                'nis' => '23026',
                'full_name' => 'Zubair Bin Awwam',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2017, 5, 20)->toDateString(),
                'address' => 'Jl. Sarilamak No. 80',
                'class_name' => 'Kelas 3A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2022/2023', 'orang_tua' => ['nama_ayah' => 'Awwam', 'nama_ibu' => 'Fatimah', 'no_hp' => '0812-0026-0026']],
            ],
            [
                'nis' => '23027',
                'full_name' => 'Juwayriyah Azizah',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2017, 7, 11)->toDateString(),
                'address' => 'Jl. Ngalau No. 5',
                'class_name' => 'Kelas 3B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2022/2023', 'orang_tua' => ['nama_ayah' => 'Aziz', 'nama_ibu' => 'Ummi', 'no_hp' => '0812-0027-0027']],
            ],
            [
                'nis' => '23028',
                'full_name' => 'Talhah Ubaidillah',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2017, 9, 28)->toDateString(),
                'address' => 'Jl. Akabiluru No. 14',
                'class_name' => 'Kelas 3B',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2022/2023', 'orang_tua' => ['nama_ayah' => 'Ubaidillah', 'nama_ibu' => 'Hawa', 'no_hp' => '0812-0028-0028']],
            ],
            [
                'nis' => '23029',
                'full_name' => 'Ummu Sulaim',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => Carbon::create(2018, 1, 15)->toDateString(),
                'address' => 'Jl. Tan Malaka No. 9',
                'class_name' => 'Kelas 2A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2023/2024', 'orang_tua' => ['nama_ayah' => 'Anas', 'nama_ibu' => 'Sulaimah', 'no_hp' => '0812-0029-0029']],
            ],
            [
                'nis' => '23030',
                'full_name' => 'Sa\'ad Bin Abi Waqqas',
                'gender' => 'male',
                'birth_place' => '50 Kota',
                'birth_date' => Carbon::create(2018, 3, 22)->toDateString(),
                'address' => 'Jl. Harau No. 100',
                'class_name' => 'Kelas 2A',
                'is_active' => true,
                'metadata' => ['status' => 'Aktif', 'tahun_masuk' => '2023/2024', 'orang_tua' => ['nama_ayah' => 'Waqqas', 'nama_ibu' => 'Zubaidah', 'no_hp' => '0812-0030-0030']],
            ],
        ];

        // Hanya 3 parent fixture dari ParentSeeder (deterministic, terlepas
        // dari akun parent lain yang dibuat seeder lain).
        $parents = ParentModel::query()
            ->where('email', 'like', '%@parent.local')
            ->orderBy('email')
            ->get();

        // Mapping eksplisit NIS -> nama ayah agar fixture multi-anak stabil.
        $explicitParentByNis = [
            '23001' => 'Ahmad Fauzi',
            '23005' => 'Ahmad Fauzi',
        ];

        foreach ($daftarSiswa as $index => $siswa) {
            $explicitParent = $explicitParentByNis[$siswa['nis']] ?? null;

            $parent = $explicitParent
                ? $parents->firstWhere('full_name', $explicitParent)
                : $parents->firstWhere('full_name', $siswa['metadata']['orang_tua']['nama_ayah'] ?? '')
                    ?? ($parents->isNotEmpty() ? $parents->get($index % $parents->count()) : null);

            // === Akun login siswa (idempotent, email deterministic dari NIS) ===
            $studentEmail = strtolower(trim($siswa['nis'])).'@student.dareliman.sch.id';

            $studentUser = User::query()->firstOrCreate(
                ['email' => $studentEmail],
                [
                    'name' => $siswa['full_name'],
                    'password' => 'Password123!',
                    'is_active' => $siswa['is_active'],
                ]
            );

            $studentUser->syncRoles(['Siswa']);

            $studentUser->forceFill([
                'name' => $siswa['full_name'],
                'is_active' => $siswa['is_active'],
            ])->save();

            $student = Student::query()->updateOrCreate(
                ['nis' => $siswa['nis']],
                [
                    'user_id' => $studentUser->id,
                    'unit_id' => $unitPendidikanId,
                    'parent_id' => $parent?->id,
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

            // === Tautan pivot keluarga (idempotent; authoritative per siswa) ===
            // Pivot siswa fixture sepenuhnya dikelola seeder ini; baris stale
            // dari versi seeder lama di-reconcile agar mapping deterministik.
            StudentParent::query()->where('student_id', $student->id)->delete();

            if ($parent) {
                StudentParent::query()->create([
                    'student_id' => $student->id,
                    'parent_id' => $parent->id,
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'metadata' => [
                        'phone_normalized' => PhoneNormalizer::normalize((string) ($siswa['metadata']['orang_tua']['no_hp'] ?? '')),
                    ],
                ]);
            }
        }

        // === Pastikan setiap dari 15 Unit Pendidikan memiliki 25 Siswa (Total 375 Siswa) ===
        $allUnitsForStudents = \App\Models\EducationUnit::all();
        $defaultHashedPassword = \Illuminate\Support\Facades\Hash::make('Password123!');
        $maleStudentPool = [
            'Ahmad Zaky', 'Muhammad Fadli', 'Raihan Ananda', 'Faris Al-Faruq',
            'Ibrahim Naufal', 'Yusuf Habibi', 'Umar Al-Faruq', 'Hamzah Abbasy',
            'Ali Zainal', 'Bilal Rabah', 'Zaid Haritsah', 'Khalid Walid',
            'Usman Afan', 'Hasan Basri', 'Husain Ali', 'Thariq Ziyad',
            'Sutrisno Putra', 'Fadhil Mubarok', 'Rian Hidayat', 'Diki Wahyudi',
            'Bintang Pratama', 'Candra Wijaya', 'Dimas Anggara', 'Erlangga Putra', 'Fikri Haikal'
        ];

        $femaleStudentPool = [
            'Aisyah Humaira', 'Nabila Putri', 'Khadijah Nurul', 'Zahra Amalia',
            'Fatima Az-Zahra', 'Mariyam Al-Qibthiyyah', 'Ruqayyah Thahirah', 'Zainab Kubra',
            'Asma Binti Abu Bakar', 'Sumayyah Yasir', 'Shafiyyah Huyay', 'Hafsah Umar',
            'Khaulah Azwar', 'Nusaibah Kaab', 'Ummu Sulaim', 'Halimah Sadiah',
            'Putri Maharani', 'Rania Syakira', 'Salsabila Nadhifah', 'Tania Rahmawati',
            'Ulfah Safitri', 'Vina Melati', 'Wulan Dari', 'Yolanda Fitri', 'Zafira Aurelia'
        ];

        foreach ($allUnitsForStudents as $uIndex => $unitObj) {
            $unitKelas = Kelas::where('unit_pendidikan_id', $unitObj->id)->first();
            $className = $unitKelas ? ($unitKelas->nama_kelas . ' ' . $unitObj->code) : ('Kelas 1 ' . $unitObj->code);

            if (! $unitKelas) {
                $unitKelas = Kelas::create([
                    'kode_kelas' => 'K-' . strtoupper($unitObj->code) . '-1A',
                    'unit_pendidikan_id' => $unitObj->id,
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'semester_id' => $semesterId,
                    'jenjang' => $unitObj->level ?? 'SDIT',
                    'tingkat' => '1',
                    'nama_kelas' => $className,
                    'status' => 'Aktif',
                ]);
            }

            $existingClass = DB::table('classes')->where('id', $unitKelas->id)->first()
                ?? DB::table('classes')->where('academic_year_id', $tahunAjaranId)
                    ->where('semester_id', $semesterId)
                    ->where('name', $className)
                    ->first();

            if ($existingClass) {
                $classId = $existingClass->id;
            } else {
                $classId = $unitKelas->id;
                DB::table('classes')->insert([
                    'id' => $classId,
                    'academic_year_id' => $tahunAjaranId,
                    'semester_id' => $semesterId,
                    'name' => $className,
                    'level' => (string) ($unitKelas->tingkat ?? '1'),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            for ($sIndex = 0; $sIndex < 25; $sIndex++) {
                $isFemale = ($sIndex % 2 === 1);
                $namePool = $isFemale ? $femaleStudentPool : $maleStudentPool;
                $studentName = $namePool[$sIndex % count($namePool)];

                $nis = '24' . str_pad((string) ($uIndex + 1), 2, '0', STR_PAD_LEFT) . str_pad((string) ($sIndex + 1), 3, '0', STR_PAD_LEFT);
                $nisn = '0024' . str_pad((string) ($uIndex + 1), 2, '0', STR_PAD_LEFT) . str_pad((string) ($sIndex + 1), 4, '0', STR_PAD_LEFT);
                $studentEmail = strtolower($nis) . '@student.dareliman.sch.id';

                $studentUser = User::query()->firstOrCreate(
                    ['email' => $studentEmail],
                    [
                        'name' => $studentName,
                        'password' => $defaultHashedPassword,
                        'is_active' => true,
                    ]
                );
                $studentUser->syncRoles(['Siswa']);

                Student::query()->updateOrCreate(
                    [
                        'unit_id' => $unitObj->id,
                        'nis' => $nis,
                    ],
                    [
                        'user_id' => $studentUser->id,
                        'unit_id' => $unitObj->id,
                        'parent_id' => $parent?->id,
                        'class_id' => $classId,
                        'kelas_id' => $unitKelas->id,
                        'nisn' => $nisn,
                        'full_name' => $studentName,
                        'gender' => $isFemale ? 'female' : 'male',
                        'birth_place' => ($uIndex % 2 === 0) ? 'Padang' : '50 Kota',
                        'birth_date' => Carbon::create(2015 - ($sIndex % 4), 1 + ($sIndex % 11), 1 + ($sIndex % 25))->toDateString(),
                        'address' => 'Jl. Pendidikan No. ' . ($sIndex + 1) . ', Kota Padang',
                        'is_active' => true,
                        'tahun_masuk' => (string) (2024 - ($sIndex % 3)),
                        'metadata' => [
                            'status' => 'Aktif',
                            'tahun_masuk' => (string) (2024 - ($sIndex % 3)),
                            'orang_tua' => [
                                'nama_ayah' => 'Bapak ' . explode(' ', $studentName)[0],
                                'nama_ibu' => 'Ibu ' . explode(' ', $studentName)[0],
                                'no_hp' => '0813' . str_pad((string) ($uIndex * 25 + $sIndex + 1), 8, '0', STR_PAD_LEFT),
                            ],
                        ],
                    ]
                );
            }
        }
    }
}
