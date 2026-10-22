<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder data awal untuk Master Data Kelas / Rombongan Belajar (Rombel)
 * untuk seluruh 15 Unit Pendidikan di bawah Yayasan Dar el-Iman.
 */
class KelasSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Tahun Ajaran Aktif 2026/2027
        AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

        $tahunAjaran = AcademicYear::firstOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]
        );

        if (! $tahunAjaran->is_active) {
            $tahunAjaran->forceFill(['is_active' => true])->save();
        }

        // 2. Pastikan Semester Ganjil & Genap
        $semesterGanjil = Semester::firstOrCreate(
            ['academic_year_id' => $tahunAjaran->id, 'sequence' => 1],
            [
                'name' => 'Ganjil',
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );

        $semesterGenap = Semester::firstOrCreate(
            ['academic_year_id' => $tahunAjaran->id, 'sequence' => 2],
            [
                'name' => 'Genap',
                'start_date' => '2027-01-01',
                'end_date' => '2027-06-30',
                'is_active' => false,
            ]
        );

        // 3. Konfigurasi Rombel & Tingkat Kelas Lengkap 15 Unit Pendidikan
        $unitClassDefinitions = [
            'TKIT-01' => [
                'jenjang' => 'TKIT',
                'classes' => [
                    ['tingkat' => 'TK-A', 'kode' => 'TK1-A1', 'nama' => 'Kelas TK-A1 (Usamah)', 'ruangan' => 'R-TK1-01', 'kapasitas' => 30],
                    ['tingkat' => 'TK-A', 'kode' => 'TK1-A2', 'nama' => 'Kelas TK-A2 (Thalhah)', 'ruangan' => 'R-TK1-02', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK1-B1', 'nama' => 'Kelas TK-B1 (Zubair)', 'ruangan' => 'R-TK1-03', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK1-B2', 'nama' => 'Kelas TK-B2 (Sa\'ad)', 'ruangan' => 'R-TK1-04', 'kapasitas' => 30],
                ],
            ],
            'TKIT-02' => [
                'jenjang' => 'TKIT',
                'classes' => [
                    ['tingkat' => 'TK-A', 'kode' => 'TK2-A1', 'nama' => 'Kelas TK-A1 (Hamzah)', 'ruangan' => 'R-TK2-01', 'kapasitas' => 30],
                    ['tingkat' => 'TK-A', 'kode' => 'TK2-A2', 'nama' => 'Kelas TK-A2 (Bilal)', 'ruangan' => 'R-TK2-02', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK2-B1', 'nama' => 'Kelas TK-B1 (Salman)', 'ruangan' => 'R-TK2-03', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK2-B2', 'nama' => 'Kelas TK-B2 (Khalid)', 'ruangan' => 'R-TK2-04', 'kapasitas' => 30],
                ],
            ],
            'TKIT-03' => [
                'jenjang' => 'TKIT',
                'classes' => [
                    ['tingkat' => 'TK-A', 'kode' => 'TK3-A1', 'nama' => 'Kelas TK-A1 (Abu Ubaidah)', 'ruangan' => 'R-TK3-01', 'kapasitas' => 30],
                    ['tingkat' => 'TK-A', 'kode' => 'TK3-A2', 'nama' => 'Kelas TK-A2 (Abdurrahman)', 'ruangan' => 'R-TK3-02', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK3-B1', 'nama' => 'Kelas TK-B1 (Ja\'far)', 'ruangan' => 'R-TK3-03', 'kapasitas' => 30],
                    ['tingkat' => 'TK-B', 'kode' => 'TK3-B2', 'nama' => 'Kelas TK-B2 (Mu\'adz)', 'ruangan' => 'R-TK3-04', 'kapasitas' => 30],
                ],
            ],
            'TAUD-01' => [
                'jenjang' => 'TAUD',
                'classes' => [
                    ['tingkat' => 'TAUD-A', 'kode' => 'TAUD-A', 'nama' => 'Kelas TAUD-A (Shighor)', 'ruangan' => 'R-TAUD-01', 'kapasitas' => 30],
                    ['tingkat' => 'TAUD-B', 'kode' => 'TAUD-B', 'nama' => 'Kelas TAUD-B (Kibar)', 'ruangan' => 'R-TAUD-02', 'kapasitas' => 30],
                ],
            ],
            'MIT-01' => [
                'jenjang' => 'MIT',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'MIT-1A', 'nama' => 'Kelas 1-A MIT SaQu', 'ruangan' => 'Gedung MIT R-101', 'kapasitas' => 30],
                    ['tingkat' => '2', 'kode' => 'MIT-2A', 'nama' => 'Kelas 2-A MIT SaQu', 'ruangan' => 'Gedung MIT R-201', 'kapasitas' => 30],
                    ['tingkat' => '3', 'kode' => 'MIT-3A', 'nama' => 'Kelas 3-A MIT SaQu', 'ruangan' => 'Gedung MIT R-301', 'kapasitas' => 30],
                ],
            ],
            'SDIT-01' => [
                'jenjang' => 'SDIT',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'SDIT1-1A', 'nama' => 'Kelas 1-A (Abu Bakar Ash-Shiddiq)', 'ruangan' => 'Gedung A R-101', 'kapasitas' => 30],
                    ['tingkat' => '2', 'kode' => 'SDIT1-2A', 'nama' => 'Kelas 2-A (Umar bin Khattab)', 'ruangan' => 'Gedung A R-201', 'kapasitas' => 30],
                    ['tingkat' => '3', 'kode' => 'SDIT1-3A', 'nama' => 'Kelas 3-A (Utsman bin Affan)', 'ruangan' => 'Gedung A R-301', 'kapasitas' => 30],
                    ['tingkat' => '4', 'kode' => 'SDIT1-4A', 'nama' => 'Kelas 4-A (Ali bin Abi Thalib)', 'ruangan' => 'Gedung B R-101', 'kapasitas' => 30],
                    ['tingkat' => '5', 'kode' => 'SDIT1-5A', 'nama' => 'Kelas 5-A (Thalhah bin Ubaidillah)', 'ruangan' => 'Gedung B R-201', 'kapasitas' => 30],
                    ['tingkat' => '6', 'kode' => 'SDIT1-6A', 'nama' => 'Kelas 6-A (Zubair bin Awwam)', 'ruangan' => 'Gedung B R-301', 'kapasitas' => 30],
                ],
            ],
            'SDIT-02' => [
                'jenjang' => 'SDIT',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'SDIT2-1A', 'nama' => 'Kelas 1-A (Abu Dzar Al-Ghifari)', 'ruangan' => 'Gedung C R-101', 'kapasitas' => 30],
                    ['tingkat' => '2', 'kode' => 'SDIT2-2A', 'nama' => 'Kelas 2-A (Mu\'adz bin Jabal)', 'ruangan' => 'Gedung C R-201', 'kapasitas' => 30],
                    ['tingkat' => '3', 'kode' => 'SDIT2-3A', 'nama' => 'Kelas 3-A (Sa\'ad bin Mu\'adz)', 'ruangan' => 'Gedung C R-301', 'kapasitas' => 30],
                    ['tingkat' => '4', 'kode' => 'SDIT2-4A', 'nama' => 'Kelas 4-A (Usaid bin Hudhair)', 'ruangan' => 'Gedung D R-101', 'kapasitas' => 30],
                    ['tingkat' => '5', 'kode' => 'SDIT2-5A', 'nama' => 'Kelas 5-A (Abdullah bin Mas\'ud)', 'ruangan' => 'Gedung D R-201', 'kapasitas' => 30],
                    ['tingkat' => '6', 'kode' => 'SDIT2-6A', 'nama' => 'Kelas 6-A (Hudzaifah bin Al-Yaman)', 'ruangan' => 'Gedung D R-301', 'kapasitas' => 30],
                ],
            ],
            'SDIT-03' => [
                'jenjang' => 'SDIT',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'SDIT3-1A', 'nama' => 'Kelas 1-A (Amr bin Al-Ash)', 'ruangan' => 'Gedung E R-101', 'kapasitas' => 30],
                    ['tingkat' => '2', 'kode' => 'SDIT3-2A', 'nama' => 'Kelas 2-A (Zaid bin Tsabit)', 'ruangan' => 'Gedung E R-201', 'kapasitas' => 30],
                    ['tingkat' => '3', 'kode' => 'SDIT3-3A', 'nama' => 'Kelas 3-A (Ubay bin Ka\'ab)', 'ruangan' => 'Gedung E R-301', 'kapasitas' => 30],
                ],
            ],
            'SDIT-04' => [
                'jenjang' => 'SDIT',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'SDIT4-1A', 'nama' => 'Kelas 1-A (Anas bin Malik)', 'ruangan' => 'Gedung F R-101', 'kapasitas' => 30],
                    ['tingkat' => '2', 'kode' => 'SDIT4-2A', 'nama' => 'Kelas 2-A (Jabir bin Abdullah)', 'ruangan' => 'Gedung F R-201', 'kapasitas' => 30],
                    ['tingkat' => '3', 'kode' => 'SDIT4-3A', 'nama' => 'Kelas 3-A (Abu Hurairah)', 'ruangan' => 'Gedung F R-301', 'kapasitas' => 30],
                ],
            ],
            'SMPIT-01' => [
                'jenjang' => 'SMPIT',
                'classes' => [
                    ['tingkat' => '7', 'kode' => 'SMP1-7A', 'nama' => 'Kelas VII-A (Ikhwan)', 'ruangan' => 'Gedung SMP1 R-101', 'kapasitas' => 30],
                    ['tingkat' => '7', 'kode' => 'SMP1-7B', 'nama' => 'Kelas VII-B (Akhwat)', 'ruangan' => 'Gedung SMP1 R-102', 'kapasitas' => 30],
                    ['tingkat' => '8', 'kode' => 'SMP1-8A', 'nama' => 'Kelas VIII-A (Reguler)', 'ruangan' => 'Gedung SMP1 R-201', 'kapasitas' => 30],
                    ['tingkat' => '9', 'kode' => 'SMP1-9A', 'nama' => 'Kelas IX-A (Unggulan)', 'ruangan' => 'Gedung SMP1 R-301', 'kapasitas' => 30],
                ],
            ],
            'SMPIT-02' => [
                'jenjang' => 'SMPIT',
                'classes' => [
                    ['tingkat' => '7', 'kode' => 'SMP2-7A', 'nama' => 'Kelas VII-A (Tahfizh 50 Kota)', 'ruangan' => 'Gedung SMP2 R-101', 'kapasitas' => 30],
                    ['tingkat' => '8', 'kode' => 'SMP2-8A', 'nama' => 'Kelas VIII-A (Tahfizh 50 Kota)', 'ruangan' => 'Gedung SMP2 R-201', 'kapasitas' => 30],
                    ['tingkat' => '9', 'kode' => 'SMP2-9A', 'nama' => 'Kelas IX-A (Tahfizh 50 Kota)', 'ruangan' => 'Gedung SMP2 R-301', 'kapasitas' => 30],
                ],
            ],
            'SMAIT-01' => [
                'jenjang' => 'SMAIT',
                'classes' => [
                    ['tingkat' => '10', 'kode' => 'SMA1-10MIPA', 'nama' => 'Kelas X-MIPA (Tahfizh Sains)', 'ruangan' => 'Gedung SMA R-101', 'kapasitas' => 30],
                    ['tingkat' => '11', 'kode' => 'SMA1-11MIPA', 'nama' => 'Kelas XI-MIPA (Olimpiade)', 'ruangan' => 'Gedung SMA R-201', 'kapasitas' => 30],
                    ['tingkat' => '12', 'kode' => 'SMA1-12IPS',  'nama' => 'Kelas XII-IPS (Dirasat Islam)', 'ruangan' => 'Gedung SMA R-301', 'kapasitas' => 30],
                ],
            ],
            'PONPES-PA' => [
                'jenjang' => 'PONPES',
                'classes' => [
                    ['tingkat' => '7', 'kode' => 'PONPES-7A', 'nama' => 'VII-A (Asrama Al-Farabi)', 'ruangan' => 'Kamar Asrama Putra 101', 'kapasitas' => 50],
                    ['tingkat' => '8', 'kode' => 'PONPES-8A', 'nama' => 'VIII-A (Asrama Ibnu Khaldun)', 'ruangan' => 'Kamar Asrama Putra 201', 'kapasitas' => 50],
                    ['tingkat' => '9', 'kode' => 'PONPES-9A', 'nama' => 'IX-A (Asrama Ibnu Sina)', 'ruangan' => 'Kamar Asrama Putra 301', 'kapasitas' => 50],
                ],
            ],
            'PONPES-PI' => [
                'jenjang' => 'PONPES',
                'classes' => [
                    ['tingkat' => '7', 'kode' => 'PONPES-PI-7A', 'nama' => 'VII-A (Asrama Khadijah)', 'ruangan' => 'Kamar Asrama Putri 101', 'kapasitas' => 50],
                    ['tingkat' => '8', 'kode' => 'PONPES-PI-8A', 'nama' => 'VIII-A (Asrama Aisyah)', 'ruangan' => 'Kamar Asrama Putri 201', 'kapasitas' => 50],
                    ['tingkat' => '9', 'kode' => 'PONPES-PI-9A', 'nama' => 'IX-A (Asrama Fatimah)', 'ruangan' => 'Kamar Asrama Putri 301', 'kapasitas' => 50],
                ],
            ],
            'MAHAD-01' => [
                'jenjang' => 'MAHAD',
                'classes' => [
                    ['tingkat' => '1', 'kode' => 'MAHAD-S1', 'nama' => 'Semester 1 (Gedung Abu Bakar)', 'ruangan' => 'Kamar Tholabah 101', 'kapasitas' => 50],
                    ['tingkat' => '3', 'kode' => 'MAHAD-S3', 'nama' => 'Semester 3 (Gedung Umar)', 'ruangan' => 'Kamar Tholabah 201', 'kapasitas' => 50],
                ],
            ],
        ];

        $unitsByCode = EducationUnit::all()->keyBy('code');
        $hashedPassword = Hash::make('password');

        $totalKelasCreated = 0;

        foreach ($unitClassDefinitions as $uCode => $def) {
            $unit = $unitsByCode->get($uCode);
            if (! $unit) {
                continue;
            }

            // Ambil guru yang ada di unit ini
            $unitEmployees = Employee::where('status', 'Aktif')
                ->where('unit_id', $unit->id)
                ->orderBy('nama_lengkap')
                ->get();

            $employeeIndex = 0;

            foreach ($def['classes'] as $c) {
                // Tentukan Wali Kelas dari pegawai di unit yang sama
                $waliKelasId = null;
                if ($unitEmployees->isNotEmpty() && $employeeIndex < $unitEmployees->count()) {
                    $waliKelasId = $unitEmployees[$employeeIndex]->id;
                    $employeeIndex++;
                } else {
                    // Jika unit kekurangan guru, buat guru/wali baru untuk unit ini secara otomatis
                    $teacherName = sprintf('Ust. Wali %s (%s)', $c['tingkat'], $unit->code);
                    $teacherEmail = strtolower(sprintf('wali.%s.%s@dareliman.sch.id', preg_replace('/[^A-Za-z0-9]/', '', $c['kode']), strtolower($unit->code)));

                    $user = User::firstOrCreate(
                        ['email' => $teacherEmail],
                        [
                            'name' => $teacherName,
                            'password' => $hashedPassword,
                        ]
                    );
                    $user->syncRoles(['Wali Kelas', 'Guru']);

                    $emp = Employee::firstOrCreate(
                        ['email' => $teacherEmail],
                        [
                            'id' => (string) Str::uuid(),
                            'nama_lengkap' => $teacherName,
                            'nama_panggilan' => 'Wali ' . $c['tingkat'],
                            'niy' => 'WALI-' . $unit->code . '-' . Str::random(4),
                            'unit_id' => $unit->id,
                            'user_id' => $user->id,
                            'status' => 'Aktif',
                            'status_pegawai' => 'Tetap',
                            'jenis_kelamin' => str_contains($unit->name, 'Putri') ? 'P' : 'L',
                        ]
                    );
                    $waliKelasId = $emp->id;
                }

                $kelasModel = Kelas::updateOrCreate(
                    [
                        'unit_pendidikan_id' => $unit->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'kode_kelas' => $c['kode'],
                    ],
                    [
                        'semester_id' => $semesterGanjil->id,
                        'jenjang' => $def['jenjang'],
                        'tingkat' => $c['tingkat'],
                        'nama_kelas' => $c['nama'],
                        'wali_kelas_id' => $waliKelasId,
                        'kapasitas' => $c['kapasitas'],
                        'ruangan' => $c['ruangan'],
                        'status' => 'Aktif',
                    ]
                );

                // Sinkronkan ke tabel legacy classes untuk backward compatibility
                $legacyName = sprintf('%s - %s', $c['nama'], $unit->code);
                $existingClass = DB::table('classes')
                    ->where('academic_year_id', $tahunAjaran->id)
                    ->where('semester_id', $semesterGanjil->id)
                    ->where('name', $legacyName)
                    ->first();

                if ($existingClass) {
                    DB::table('classes')
                        ->where('id', $existingClass->id)
                        ->update([
                            'level' => (string) $c['tingkat'],
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('classes')->updateOrInsert(
                        ['id' => $kelasModel->id],
                        [
                            'academic_year_id' => $tahunAjaran->id,
                            'semester_id' => $semesterGanjil->id,
                            'name' => $legacyName,
                            'level' => (string) $c['tingkat'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $totalKelasCreated++;
            }
        }

        if ($this->command) {
            $this->command->info(sprintf('KelasSeeder sukses: %d rombel dan tingkat kelas untuk 15 unit pendidikan telah dibuat/disinkronkan.', $totalKelasCreated));
        }
    }
}

