<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder data awal untuk Master Data Kelas / Rombongan Belajar (Rombel).
 */
class KelasSeeder extends Seeder
{
    /**
     * Jalankan seeder database.
     */
    public function run(): void
    {
        // 1. Pastikan minimal 1 Tahun Ajaran ada.
        // PostgreSQL partial unique index `uniq_one_active_academic_year` mengizinkan
        // HANYA satu academic year aktif. Seeder lain (DataDummySiswaSeeder) bisa membuat
        // year aktif lain, jadi deaktivasi year aktif lama sebelum menetapkan 2026/2027.
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

        // 2. Pastikan minimal 1 Semester ada
        $semester = Semester::firstOrCreate(
            ['academic_year_id' => $tahunAjaran->id, 'sequence' => 1],
            [
                'name' => 'Ganjil',
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ]
        );

        // 3. Ambil data Unit Pendidikan yang ada
        $units = EducationUnit::query()->orderBy('code')->get();
        if ($units->isEmpty()) {
            $units = collect([
                EducationUnit::create(['name' => 'SDIT Dar el-Iman', 'code' => 'SDIT-01', 'level' => 'SDIT', 'is_active' => true]),
                EducationUnit::create(['name' => 'SMPIT Dar el-Iman', 'code' => 'SMPIT-01', 'level' => 'SMPIT', 'is_active' => true]),
                EducationUnit::create(['name' => 'SMAIT Dar el-Iman', 'code' => 'SMAIT-01', 'level' => 'SMAIT', 'is_active' => true]),
            ]);
        }

        // 4. Ambil data Employee (Guru) untuk wali kelas
        $employees = Employee::where('status', 'Aktif')->orderBy('id')->get();

        // Sampel data kelas per jenjang unit
        $sampleClasses = [
            'SDIT' => [
                ['tingkat' => '1', 'kode' => 'SD-1A', 'nama' => 'Kelas 1 Abu Bakar', 'ruangan' => 'Gedung A R-101', 'kapasitas' => 28],
                ['tingkat' => '1', 'kode' => 'SD-1B', 'nama' => 'Kelas 1 Umar', 'ruangan' => 'Gedung A R-102', 'kapasitas' => 28],
                ['tingkat' => '2', 'kode' => 'SD-2A', 'nama' => 'Kelas 2 Utsman', 'ruangan' => 'Gedung A R-201', 'kapasitas' => 30],
                ['tingkat' => '3', 'kode' => 'SD-3A', 'nama' => 'Kelas 3 Ali', 'ruangan' => 'Gedung A R-301', 'kapasitas' => 30],
            ],
            'SMPIT' => [
                ['tingkat' => '7', 'kode' => 'SMP-7A', 'nama' => 'Kelas 7 Tahfizh A', 'ruangan' => 'Gedung B R-101', 'kapasitas' => 32],
                ['tingkat' => '7', 'kode' => 'SMP-7B', 'nama' => 'Kelas 7 Reguler B', 'ruangan' => 'Gedung B R-102', 'kapasitas' => 32],
                ['tingkat' => '8', 'kode' => 'SMP-8A', 'nama' => 'Kelas 8 Tahfizh A', 'ruangan' => 'Gedung B R-201', 'kapasitas' => 30],
                ['tingkat' => '9', 'kode' => 'SMP-9A', 'nama' => 'Kelas 9 Unggulan A', 'ruangan' => 'Gedung B R-301', 'kapasitas' => 30],
            ],
            'SMAIT' => [
                ['tingkat' => '10', 'kode' => 'SMA-10IPA', 'nama' => 'Kelas 10 MIPA 1', 'ruangan' => 'Lab IPA 1', 'kapasitas' => 35],
                ['tingkat' => '11', 'kode' => 'SMA-11IPA', 'nama' => 'Kelas 11 MIPA 1', 'ruangan' => 'Gedung C R-201', 'kapasitas' => 35],
                ['tingkat' => '12', 'kode' => 'SMA-12IPS', 'nama' => 'Kelas 12 IPS 1', 'ruangan' => 'Gedung C R-301', 'kapasitas' => 35],
            ],
        ];

        $employeeIndex = 0;

        foreach ($units as $unit) {
            $levelKey = strtoupper(preg_replace('/[^A-Za-z]/', '', $unit->level ?? 'SDIT'));
            if (! isset($sampleClasses[$levelKey])) {
                $levelKey = 'SDIT';
            }

            foreach ($sampleClasses[$levelKey] as $c) {
                // Tentukan Wali Kelas secara berurutan tanpa duplikasi per tahun ajaran
                $waliKelasId = null;
                if ($employees->isNotEmpty() && $employeeIndex < $employees->count()) {
                    $waliKelasId = $employees[$employeeIndex]->id;
                    $employeeIndex++;
                }

                $kelasModel = Kelas::updateOrCreate(
                    ['kode_kelas' => $c['kode'].'-'.$unit->code],
                    [
                        'unit_pendidikan_id' => $unit->id,
                        'tahun_ajaran_id' => $tahunAjaran->id,
                        'semester_id' => $semester->id,
                        'jenjang' => $unit->level ?? 'SDIT',
                        'tingkat' => $c['tingkat'],
                        'nama_kelas' => $c['nama'],
                        'wali_kelas_id' => $waliKelasId,
                        'kapasitas' => $c['kapasitas'],
                        'ruangan' => $c['ruangan'],
                        'status' => 'Aktif',
                    ]
                );

                // Sinkronkan ke tabel legacy classes untuk backward compatibility FK.
                // classes adalah tabel legacy: 1 baris per (tahun_ajaran, semester, nama_kelas).
                // Jangan menimpa id yang sudah ada — id classes harus stabil lintas run.
                $existingClass = DB::table('classes')
                    ->where('academic_year_id', $tahunAjaran->id)
                    ->where('semester_id', $semester->id)
                    ->where('name', $c['nama'])
                    ->first();

                if ($existingClass) {
                    DB::table('classes')
                        ->where('id', $existingClass->id)
                        ->update([
                            'level' => (string) $c['tingkat'],
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('classes')->insert([
                        'id' => $kelasModel->id,
                        'academic_year_id' => $tahunAjaran->id,
                        'semester_id' => $semester->id,
                        'name' => $c['nama'],
                        'level' => (string) $c['tingkat'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
