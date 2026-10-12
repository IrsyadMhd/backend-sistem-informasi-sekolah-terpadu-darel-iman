<?php

namespace Database\Seeders;

use App\Models\BusinessOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessOptionSeeder extends Seeder
{
    public function run(): void
    {
        $optionsByGroup = [
            'parent_occupation' => [
                'Pegawai Negeri',
                'Pegawai Negeri (PNS/ASN)',
                'Pegawai Swasta',
                'Wiraswasta',
                'Pegawai BUMN',
                'TNI / POLRI',
                'Petani / Peternak',
                'Buruh',
                'Pensiunan',
                'Ibu Rumah Tangga',
                'Guru',
                'Dosen',
                'Pedagang',
                'Dokter / Tenaga Medis',
                'Tidak Bekerja',
                'Lainnya',
            ],
            'residence_type' => [
                'Bersama Orang Tua',
                'Wali',
                'Asrama',
                'Kos',
                'Panti Asuhan',
                'Lainnya',
            ],
            'education_level' => [
                'TK / PAUD',
                'SD / MI',
                'SMP / MTs',
                'SMA / SMK / MA',
                'Pondok Pesantren',
                'Perguruan Tinggi',
            ],
            'last_education' => [
                'SD/MI',
                'SMP/MTs',
                'SMA/SMK/MA',
                'D1/D2/D3',
                'S1/D4',
                'S2',
                'S3',
                'Tidak Sekolah',
            ],
            'marital_status' => [
                'Menikah',
                'Belum Menikah',
                'Cerai Hidup',
                'Cerai Mati',
            ],
            'student_status' => [
                'Aktif',
                'Lulus',
                'Mutasi',
                'Berhenti',
                'Cuti',
            ],
            'employee_status' => [
                'Tetap',
                'Kontrak',
                'Honorer',
                'Magang',
                'Percobaan',
                'Non-Aktif',
            ],
            'alumni_status' => [
                'Kuliah',
                'Bekerja',
                'Wirausaha',
                'Menikah',
                'Persiapan Kuliah',
                'Belum Bekerja',
                'Lainnya',
            ],
        ];

        foreach ($optionsByGroup as $group => $names) {
            foreach ($names as $index => $name) {
                $code = Str::slug($name, '_');
                BusinessOption::updateOrCreate(
                    [
                        'group' => $group,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'order' => $index + 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
