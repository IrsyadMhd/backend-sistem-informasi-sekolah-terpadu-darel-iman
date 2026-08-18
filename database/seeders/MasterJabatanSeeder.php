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

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'Super Admin')->first()?->givePermissionTo($permissions);

        $items = [
            // Level 1: Pengurus Yayasan
            ['JBT-001', 'Ketua Yayasan', 1, 'Pengurus', 'Yayasan', 'semua_unit', 'Crown'],
            ['JBT-002', 'Sekretaris Yayasan', 1, 'Pengurus', 'Yayasan', 'semua_unit', 'ShieldCheck'],
            ['JBT-015', 'Bendahara Yayasan', 1, 'Pengurus', 'Yayasan', 'semua_unit', 'Wallet'],

            // Level 2: Divisi Pendidikan
            ['JBT-005', 'Kepala Bidang Pendidikan', 2, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Briefcase'],
            ['JBT-016', 'Bagian Kurikulum', 2, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'BookOpen'],
            ['JBT-017', 'Bagian Bahasa', 2, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'BookOpen'],
            ['JBT-018', 'Bagian Program Khusus', 2, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Sparkles'],
            ['JBT-019', 'Bagian Kesiswaan', 2, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Users'],

            // Level 3: Kepala Sekolah
            ['JBT-003', 'Kepala Sekolah', 3, 'Unit Pendidikan', 'Kepala Sekolah', 'unit_sendiri', 'UserTie'],

            // Level 4: Wakil Kepala Sekolah
            ['JBT-004', 'Wakil Kurikulum', 4, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],
            ['JBT-020', 'Wakil Kesiswaan/Kesantrian', 4, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],
            ['JBT-021', 'Wakil Humas dan Program Khusus', 4, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],

            // Level 5: Kepala Divisi Unit
            ['JBT-025', 'Kepala Divisi Unit', 5, 'Unit Pendidikan', 'Kepala Divisi', 'unit_sendiri', 'Building'],

            // Level 6: Tata Usaha
            ['JBT-006', 'Kepala Tata Usaha', 6, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Building'],

            // Level 7: Operator Sekolah
            ['JBT-007', 'Operator Sekolah', 7, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Laptop'],

            // Level 8: Guru
            ['JBT-010', 'Wali Kelas', 8, 'Unit Pendidikan', 'Wali Kelas', 'rombel_sendiri', 'Users'],
            ['JBT-009', 'Guru Mapel', 8, 'Unit Pendidikan', 'Guru', 'kelas_mapel_sendiri', 'GraduationCap'],
            ['JBT-022', 'Guru BK', 8, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'HeartHandshake'],
            ['JBT-024', 'Pembina Ekstrakurikuler', 8, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'Award'],

            // Level 9: Musyrif
            ['JBT-011', 'Guru Tahfizh', 9, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'BookOpen'],
            ['JBT-023', 'Musyrif/Asrama', 9, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'Building'],

            // Level 10: Staf Administrasi / Pegawai
            ['JBT-008', 'Bendahara', 10, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Wallet'],
            ['JBT-012', 'Staf Administrasi', 10, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'FileText'],
            ['JBT-013', 'Satpam', 10, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Shield'],
            ['JBT-014', 'Cleaning Service', 10, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Broom'],
        ];

        foreach ($items as $index => [$code, $name, $level, $workUnit, $roleName, $scope, $icon]) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            Position::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'satuan_kerja' => $workUnit,
                    'level_jabatan' => $level,
                    'role_sistem_id' => $role?->id,
                    'scope_akses' => $scope,
                    'urutan' => $index + 1,
                    'warna' => $this->warna($workUnit),
                    'ikon' => $icon,
                    'description' => "Jabatan {$name} pada satuan kerja {$workUnit}.",
                    'is_active' => true,
                    'tampil_struktur' => true,
                    'boleh_login' => true,
                ]
            );
        }

        // Set Atasan Langsung Hierarki
        $ketuaYayasan = Position::where('code', 'JBT-001')->first();
        $kabidPendidikan = Position::where('code', 'JBT-005')->first();
        $kepalaSekolah = Position::where('code', 'JBT-003')->first();
        $wakilKurikulum = Position::where('code', 'JBT-004')->first();
        $wakilKesiswaan = Position::where('code', 'JBT-020')->first();
        $kepalaTU = Position::where('code', 'JBT-006')->first();

        // Level 2 -> Atasan: Ketua Yayasan
        Position::whereIn('code', ['JBT-005', 'JBT-016', 'JBT-017', 'JBT-018', 'JBT-019'])
            ->update(['atasan_langsung_id' => $ketuaYayasan?->id]);

        // Level 3 -> Atasan: Kabid Pendidikan
        Position::where('code', 'JBT-003')
            ->update(['atasan_langsung_id' => $kabidPendidikan?->id]);

        // Level 4, 5, 6 -> Atasan: Kepala Sekolah
        Position::whereIn('code', ['JBT-004', 'JBT-020', 'JBT-021', 'JBT-025', 'JBT-006'])
            ->update(['atasan_langsung_id' => $kepalaSekolah?->id]);

        // Level 7, 10 -> Atasan: Kepala Tata Usaha
        Position::whereIn('code', ['JBT-007', 'JBT-008', 'JBT-012', 'JBT-013', 'JBT-014'])
            ->update(['atasan_langsung_id' => $kepalaTU?->id]);

        // Level 8 (Guru) -> Atasan: Wakil Kurikulum
        Position::whereIn('code', ['JBT-009', 'JBT-010', 'JBT-022', 'JBT-024'])
            ->update(['atasan_langsung_id' => $wakilKurikulum?->id]);

        // Level 9 (Musyrif) -> Atasan: Wakil Kesiswaan
        Position::whereIn('code', ['JBT-011', 'JBT-023'])
            ->update(['atasan_langsung_id' => $wakilKesiswaan?->id]);

        $this->consolidateLegacyPositions();
    }

    private function warna(string $workUnit): string
    {
        return match ($workUnit) {
            'Pengurus' => '#8B5CF6',
            'Bidang Pendidikan' => '#0D9488',
            default => '#2563EB',
        };
    }

    private function consolidateLegacyPositions(): void
    {
        $legacyMap = [
            'JAB-001' => 'JBT-003',
            'JAB-002' => 'JBT-004',
            'JAB-003' => 'JBT-010',
            'JAB-004' => 'JBT-009',
            'JAB-005' => 'JBT-006',
            'JAB-006' => 'JBT-007',
            'JAB-007' => 'JBT-005',
            'JAB-008' => 'JBT-001',
        ];

        foreach ($legacyMap as $legacyCode => $officialCode) {
            $legacy = Position::where('code', $legacyCode)->first();
            $official = Position::where('code', $officialCode)->first();

            if (! $legacy || ! $official) {
                continue;
            }

            DB::table('employees')
                ->where('jabatan_id', $legacy->id)
                ->update(['jabatan_id' => $official->id, 'updated_at' => now()]);

            $legacy->delete();
        }
    }
}
