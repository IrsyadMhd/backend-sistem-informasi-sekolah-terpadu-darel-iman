<?php

namespace Database\Seeders;

use App\Models\JenisUnitPendidikan;
use Illuminate\Database\Seeder;

class MasterJenisUnitPendidikanSeeder extends Seeder
{
    public function run(): void
    {
        $defaultData = [
            [
                'kode_jenis' => 'TKIT',
                'nama_jenis' => 'Taman Kanak-kanak Islam Terpadu',
                'singkatan' => 'TKIT',
                'jenjang' => 'TK',
                'warna_badge' => '#10B981', // Hijau
                'icon' => 'Children',
                'urutan' => 1,
                'keterangan' => 'Jenis unit pendidikan jenjang Taman Kanak-kanak Islam Terpadu',
                'status' => true,
            ],
            [
                'kode_jenis' => 'TAUD',
                'nama_jenis' => 'Taman Anak Usia Dini',
                'singkatan' => 'TAUD',
                'jenjang' => 'PAUD',
                'warna_badge' => '#06B6D4', // Cyan
                'icon' => 'Home',
                'urutan' => 2,
                'keterangan' => 'Jenis unit pendidikan jenjang Taman Anak Usia Dini',
                'status' => true,
            ],
            [
                'kode_jenis' => 'SDIT',
                'nama_jenis' => 'Sekolah Dasar Islam Terpadu',
                'singkatan' => 'SDIT',
                'jenjang' => 'SD',
                'warna_badge' => '#3B82F6', // Biru
                'icon' => 'School',
                'urutan' => 3,
                'keterangan' => 'Jenis unit pendidikan jenjang Sekolah Dasar Islam Terpadu',
                'status' => true,
            ],
            [
                'kode_jenis' => 'MIT',
                'nama_jenis' => 'Madrasah Ibtidaiyah Terpadu',
                'singkatan' => 'MIT',
                'jenjang' => 'MI',
                'warna_badge' => '#F59E0B', // Amber
                'icon' => 'Book',
                'urutan' => 4,
                'keterangan' => 'Jenis unit pendidikan jenjang Madrasah Ibtidaiyah Terpadu',
                'status' => true,
            ],
            [
                'kode_jenis' => 'SMPIT',
                'nama_jenis' => 'Sekolah Menengah Pertama Islam Terpadu',
                'singkatan' => 'SMPIT',
                'jenjang' => 'SMP',
                'warna_badge' => '#6366F1', // Indigo
                'icon' => 'Graduation',
                'urutan' => 5,
                'keterangan' => 'Jenis unit pendidikan jenjang Sekolah Menengah Pertama Islam Terpadu',
                'status' => true,
            ],
            [
                'kode_jenis' => 'SMAIT',
                'nama_jenis' => 'Sekolah Menengah Atas Islam Terpadu',
                'singkatan' => 'SMAIT',
                'jenjang' => 'SMA',
                'warna_badge' => '#8B5CF6', // Purple
                'icon' => 'University',
                'urutan' => 6,
                'keterangan' => 'Jenis unit pendidikan jenjang Sekolah Menengah Atas Islam Terpadu',
                'status' => true,
            ],
            [
                'kode_jenis' => 'PONPES',
                'nama_jenis' => 'Pondok Pesantren',
                'singkatan' => 'PONPES',
                'jenjang' => 'Pondok Pesantren',
                'warna_badge' => '#059669', // Emerald
                'icon' => 'Mosque',
                'urutan' => 7,
                'keterangan' => 'Jenis unit pendidikan jenjang Pondok Pesantren',
                'status' => true,
            ],
            [
                'kode_jenis' => 'MAHAD',
                'nama_jenis' => "Ma'had",
                'singkatan' => 'MAHAD',
                'jenjang' => 'Mahad',
                'warna_badge' => '#D97706', // Warm Amber
                'icon' => 'Building',
                'urutan' => 8,
                'keterangan' => "Jenis unit pendidikan jenjang Ma'had",
                'status' => true,
            ],
        ];

        foreach ($defaultData as $item) {
            JenisUnitPendidikan::withTrashed()->firstOrCreate(
                ['kode_jenis' => $item['kode_jenis']],
                $item
            );
        }
    }
}
