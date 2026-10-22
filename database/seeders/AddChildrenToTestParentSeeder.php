<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder Khusus:
 * Menambahkan data anak dengan unit berbeda untuk akun "Orang Tua Test".
 * - Anak 1: Unit Boarding (PONPES Putra Dar el-Iman - Kelas VII-A Asrama Al-Farabi)
 * - Anak 2: Unit Fullday (SDIT 1 Dar el-Iman - 50 Kota - Kelas 1-A Abu Bakar)
 */
class AddChildrenToTestParentSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // 1. Cari Parent Model "Orang Tua Test"
        $parentUser = User::where('email', 'orangtua@dareliman.sch.id')->first()
            ?? User::where('name', 'like', '%Orang Tua Test%')->first();

        if (! $parentUser) {
            $this->command->error("User Orang Tua Test tidak ditemukan!");
            return;
        }

        $parent = ParentModel::where('user_id', $parentUser->id)->first()
            ?? ParentModel::where('full_name', 'like', '%Orang Tua Test%')->first();

        if (! $parent) {
            $parent = ParentModel::create([
                'user_id' => $parentUser->id,
                'full_name' => 'Orang Tua Test',
                'phone' => '081299990022',
                'email' => 'orangtua@dareliman.sch.id',
                'occupation' => 'Wiraswasta / Profesional',
                'address' => 'Jl. Khatib Sulaiman No. 77, Kota Padang',
                'nik' => '1371000000000022',
                'metadata' => [
                    'nama_ayah' => 'Orang Tua Test',
                    'nama_ibu' => 'Ibu Test',
                    'no_wa' => '081299990022',
                ],
            ]);
        }

        $roleSiswa = Role::where('name', 'Siswa')->first();

        // 2. Ambil Unit & Kelas Boarding (PONPES-PA)
        $boardingUnit = EducationUnit::where('code', 'PONPES-PA')->first();
        $kelasBoarding = Kelas::where('kode_kelas', 'PONPES-7A')->first()
            ?? Kelas::where('unit_pendidikan_id', $boardingUnit?->id)->first();

        // 3. Ambil Unit & Kelas Fullday (SDIT-01)
        $fulldayUnit = EducationUnit::where('code', 'SDIT-01')->first();
        $kelasFullday = Kelas::where('kode_kelas', 'SDIT1-1A')->first()
            ?? Kelas::where('unit_pendidikan_id', $fulldayUnit?->id)->first();

        // 4. Data Anak Baru Lintas Unit untuk Orang Tua Test
        $childrenToAdd = [
            [
                'unit' => $boardingUnit,
                'kelas' => $kelasBoarding,
                'nis' => '202607099',
                'nisn' => '0123450099',
                'name' => 'Rayhan Pratama (Santri Boarding)',
                'gender' => 'male',
                'birth_place' => 'Padang',
                'birth_date' => '2013-05-15',
                'email' => 'santri.rayhan.test@dareliman.sch.id',
                'address' => 'Gedung Asrama Al-Farabi Kamar 01, Kompleks Ponpes Putra Dar el-Iman',
                'program_type' => 'boarding',
                'extra_meta' => [
                    'program_type' => 'boarding',
                    'kamar_asrama' => 'Al-Farabi 01',
                    'musyrif' => 'Musyrif Test',
                ],
            ],
            [
                'unit' => $fulldayUnit,
                'kelas' => $kelasFullday,
                'nis' => '202601099',
                'nisn' => '0187650099',
                'name' => 'Alya Zahra Ramadhani (Siswa Fullday)',
                'gender' => 'female',
                'birth_place' => 'Payakumbuh',
                'birth_date' => '2019-08-20',
                'email' => 'siswa.alya.test@dareliman.sch.id',
                'address' => 'Jl. Khatib Sulaiman No. 77, Kota Padang',
                'program_type' => 'fullday',
                'extra_meta' => [
                    'program_type' => 'fullday',
                    'wali_kelas' => 'Wali Kelas Test',
                ],
            ],
        ];

        foreach ($childrenToAdd as $c) {
            if (! $c['unit'] || ! $c['kelas']) {
                $this->command->warn("Unit atau kelas untuk {$c['name']} belum tersedia.");
                continue;
            }

            // User Akun Anak
            $studentUser = User::firstOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'password' => $defaultPassword,
                    'is_active' => true,
                ]
            );
            if ($roleSiswa) {
                $studentUser->syncRoles([$roleSiswa->name]);
            }

            // Record Siswa (Student)
            $metadata = array_merge([
                'status_siswa' => 'Aktif',
                'nama_ayah' => 'Orang Tua Test',
                'nama_ibu' => 'Ibu Test',
                'hp_ayah' => '081299990022',
                'nomor_wa_ayah' => '081299990022',
                'orang_tua' => [
                    'nama_ayah' => 'Orang Tua Test',
                    'nama_ibu' => 'Ibu Test',
                    'no_hp' => '081299990022',
                ],
            ], $c['extra_meta']);

            $targetParent = $parent;
            if ($c['program_type'] === 'boarding') {
                $boardingUser = User::firstOrCreate(
                    ['email' => 'wali.santri.202607099@dareliman.sch.id'],
                    [
                        'name' => 'Wali Santri Rayhan Pratama',
                        'password' => $defaultPassword,
                        'phone' => '081299990799',
                        'is_active' => true,
                        'metadata' => [
                            'education_unit_id' => $c['unit']->id,
                            'unit_code' => $c['unit']->code,
                            'unit_name' => $c['unit']->name,
                        ],
                    ]
                );
                $roleOrtu = Role::where('name', 'Orang Tua')->first();
                if ($roleOrtu) {
                    $boardingUser->syncRoles([$roleOrtu->name]);
                }
                $targetParent = ParentModel::firstOrCreate(
                    ['user_id' => $boardingUser->id],
                    [
                        'full_name' => 'Wali Santri Rayhan Pratama',
                        'phone' => '081299990799',
                        'email' => 'wali.santri.202607099@dareliman.sch.id',
                        'nik' => '1371000000000799',
                        'address' => 'Kompleks Ponpes Putra Dar el-Iman',
                    ]
                );
            }

            $student = Student::updateOrCreate(
                ['nis' => $c['nis']],
                [
                    'user_id' => $studentUser->id,
                    'parent_id' => $targetParent->id,
                    'unit_id' => $c['unit']->id,
                    'kelas_id' => $c['kelas']->id,
                    'class_id' => $c['kelas']->id,
                    'nisn' => $c['nisn'],
                    'full_name' => $c['name'],
                    'gender' => $c['gender'],
                    'birth_place' => $c['birth_place'],
                    'birth_date' => $c['birth_date'],
                    'address' => $c['address'],
                    'is_active' => true,
                    'tahun_masuk' => 2026,
                    'metadata' => $metadata,
                ]
            );

            // Relasi ke student_parents
            StudentParent::updateOrCreate(
                ['student_id' => $student->id, 'parent_id' => $targetParent->id],
                [
                    'relationship_type' => 'father',
                    'is_primary' => true,
                    'metadata' => ['phone' => $targetParent->phone ?? '081299990022'],
                ]
            );

            $this->command->info("Anak berhasil ditambahkan: {$student->full_name} ({$c['unit']->name} - {$c['kelas']->nama_kelas})");
        }

        $this->command->info("Selesai menambahkan data anak lintas unit untuk Orang Tua Test!");
    }
}
