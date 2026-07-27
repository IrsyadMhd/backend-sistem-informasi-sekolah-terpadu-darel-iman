<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MasterJabatanSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'jabatan.view',
            'jabatan.create',
            'jabatan.edit',
            'jabatan.delete',
            'jabatan.export',
            'jabatan.import',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($permissions);
        }

        $jabatanSeed = [
            [
                'code' => 'JBT-001',
                'name' => 'Ketua Yayasan',
                'level_jabatan' => 1,
                'urutan' => 1,
                'warna' => '#8B5CF6', // Purple
                'ikon' => 'Crown',
                'description' => 'Pimpinan Tertinggi Organisasi Yayasan Dar El-Iman',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-002',
                'name' => 'Pengurus Yayasan',
                'level_jabatan' => 2,
                'urutan' => 2,
                'warna' => '#6366F1', // Indigo
                'ikon' => 'ShieldCheck',
                'description' => 'Anggota Pengurus Yayasan bidang Operasional',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-003',
                'name' => 'Kepala Sekolah',
                'level_jabatan' => 3,
                'urutan' => 3,
                'warna' => '#3B82F6', // Blue
                'ikon' => 'UserTie',
                'description' => 'Penanggung Jawab Utama Unit Sekolah',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-004',
                'name' => 'Wakil Kepala Sekolah',
                'level_jabatan' => 4,
                'urutan' => 4,
                'warna' => '#0284C7', // Sky Blue
                'ikon' => 'UserCheck',
                'description' => 'Wakil Pimpinan Bidang Kurikulum/Kesiswaan/Sarpras',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-005',
                'name' => 'Kepala Divisi',
                'level_jabatan' => 5,
                'urutan' => 5,
                'warna' => '#0D9488', // Teal
                'ikon' => 'Briefcase',
                'description' => 'Kepala Divisi Operasional Terpadu',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-006',
                'name' => 'Kepala Tata Usaha',
                'level_jabatan' => 6,
                'urutan' => 6,
                'warna' => '#059669', // Emerald
                'ikon' => 'Building',
                'description' => 'Kepala Administrasi & Kepegawaian Unit',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-007',
                'name' => 'Operator Sekolah',
                'level_jabatan' => 7,
                'urutan' => 7,
                'warna' => '#10B981', // Green
                'ikon' => 'Laptop',
                'description' => 'Pengelola Sistem Informasi Sekolah & Dapodik',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-008',
                'name' => 'Bendahara',
                'level_jabatan' => 8,
                'urutan' => 8,
                'warna' => '#D97706', // Amber
                'ikon' => 'Wallet',
                'description' => 'Pengelola Keuangan & Anggaran Sekolah',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-009',
                'name' => 'Guru',
                'level_jabatan' => 9,
                'urutan' => 9,
                'warna' => '#2563EB', // Royal Blue
                'ikon' => 'GraduationCap',
                'description' => 'Tenaga Pendidik Pengampu Mata Pelajaran',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-010',
                'name' => 'Wali Kelas',
                'level_jabatan' => 10,
                'urutan' => 10,
                'warna' => '#7C3AED', // Violet
                'ikon' => 'Users',
                'description' => 'Pembimbing & Penanggung Jawab Rombongan Belajar',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-011',
                'name' => 'Pembimbing Tahfizh',
                'level_jabatan' => 11,
                'urutan' => 11,
                'warna' => '#047857', // Dark Green
                'ikon' => 'BookOpen',
                'description' => 'Pengampu Halqah Tahfizh & Mutabaah',
                'tampil_struktur' => true,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-012',
                'name' => 'Staf Administrasi',
                'level_jabatan' => 12,
                'urutan' => 12,
                'warna' => '#475569', // Slate
                'ikon' => 'FileText',
                'description' => 'Staf Operasional Administrasi TU',
                'tampil_struktur' => false,
                'boleh_login' => true,
            ],
            [
                'code' => 'JBT-013',
                'name' => 'Satpam',
                'level_jabatan' => 13,
                'urutan' => 13,
                'warna' => '#DC2626', // Red
                'ikon' => 'Shield',
                'description' => 'Petugas Keamanan Lingkungan Sekolah',
                'tampil_struktur' => false,
                'boleh_login' => false,
            ],
            [
                'code' => 'JBT-014',
                'name' => 'Cleaning Service',
                'level_jabatan' => 14,
                'urutan' => 14,
                'warna' => '#6B7280', // Gray
                'ikon' => 'Broom',
                'description' => 'Petugas Kebersihan Lingkungan Sekolah',
                'tampil_struktur' => false,
                'boleh_login' => false,
            ],
        ];

        foreach ($jabatanSeed as $item) {
            Position::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'level_jabatan' => $item['level_jabatan'],
                    'urutan' => $item['urutan'],
                    'warna' => $item['warna'],
                    'ikon' => $item['ikon'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'tampil_struktur' => $item['tampil_struktur'],
                    'boleh_login' => $item['boleh_login'],
                ]
            );
        }
    }
}
