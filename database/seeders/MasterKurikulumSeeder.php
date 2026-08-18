<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class MasterKurikulumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = EducationUnit::all();
        $tahunAktif = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semesterAktif = Semester::where('is_active', true)->first() ?? Semester::first();

        if ($units->isEmpty() || ! $tahunAktif) {
            return;
        }

        $seedData = [
            [
                'kode_kurikulum' => 'KUR-TK-MERDEKA-SIT',
                'nama_kurikulum' => 'Kurikulum Merdeka PAUD/TK Islam Terpadu',
                'jenis_kurikulum' => 'SIT',
                'jenjang' => 'TK',
                'deskripsi' => 'Kurikulum Merdeka dikombinasikan dengan Standar Mutu Pendidikan SIT JSIT Indonesia untuk jenjang TK/PAUD.',
                'tanggal_mulai' => '2025-07-15',
                'tanggal_selesai' => '2026-06-30',
                'status' => true,
            ],
            [
                'kode_kurikulum' => 'KUR-SD-MERDEKA-SIT',
                'nama_kurikulum' => 'Kurikulum Merdeka SD Islam Terpadu',
                'jenis_kurikulum' => 'SIT',
                'jenjang' => 'SD',
                'deskripsi' => 'Kurikulum Merdeka Terpadu dengan penguatan Bina Pribadi Muslim (BPM) dan Al-Qur\'an.',
                'tanggal_mulai' => '2025-07-15',
                'tanggal_selesai' => '2026-06-30',
                'status' => true,
            ],
            [
                'kode_kurikulum' => 'KUR-SMP-MERDEKA-SIT',
                'nama_kurikulum' => 'Kurikulum Merdeka SMP Islam Terpadu',
                'jenis_kurikulum' => 'SIT',
                'jenjang' => 'SMP',
                'deskripsi' => 'Kurikulum Merdeka berbasis Proyek Penguatan Profil Pelajar Pancasila dan Rahmatan Lil Alamin (P5-PPRA).',
                'tanggal_mulai' => '2025-07-15',
                'tanggal_selesai' => '2026-06-30',
                'status' => true,
            ],
            [
                'kode_kurikulum' => 'KUR-SMA-MERDEKA-SIT',
                'nama_kurikulum' => 'Kurikulum Merdeka SMA Islam Terpadu',
                'jenis_kurikulum' => 'SIT',
                'jenjang' => 'SMA',
                'deskripsi' => 'Kurikulum Nasional Merdeka dengan integrasi Kurikulum Pesantren dan Persiapan Perguruan Tinggi.',
                'tanggal_mulai' => '2025-07-15',
                'tanggal_selesai' => '2026-06-30',
                'status' => true,
            ],
            [
                'kode_kurikulum' => 'KUR-PONPES-TAHFIZH',
                'nama_kurikulum' => 'Kurikulum Pesantren Tahfizh & Diniyah',
                'jenis_kurikulum' => 'Pesantren',
                'jenjang' => 'SMP',
                'deskripsi' => 'Kurikulum kekhasan Pondok Pesantren meliputi Tahfizh 30 Juz, Kutubut Turats, dan Bahasa Arab.',
                'tanggal_mulai' => '2025-07-15',
                'tanggal_selesai' => '2027-06-30',
                'status' => true,
            ],
        ];

        foreach ($seedData as $data) {
            $matchedUnit = $units->first(fn ($u) => str_contains(strtoupper($u->level ?? $u->name), strtoupper($data['jenjang']))) ?? $units->first();

            MasterKurikulum::firstOrCreate(
                ['kode_kurikulum' => $data['kode_kurikulum']],
                array_merge($data, [
                    'unit_pendidikan_id' => $matchedUnit->id,
                    'tahun_ajaran_id' => $tahunAktif->id,
                    'semester_id' => $semesterAktif?->id,
                ])
            );
        }
    }
}
