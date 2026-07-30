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
            ['JBT-001', 'Ketua Yayasan', 1, 'Pengurus', 'Yayasan', 'semua_unit', 'Crown'],
            ['JBT-002', 'Sekretaris Yayasan', 2, 'Pengurus', 'Yayasan', 'semua_unit', 'ShieldCheck'],
            ['JBT-015', 'Bendahara Yayasan', 2, 'Pengurus', 'Yayasan', 'semua_unit', 'Wallet'],

            ['JBT-005', 'Kepala Bidang Pendidikan', 3, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Briefcase'],
            ['JBT-016', 'Bagian Kurikulum', 4, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'BookOpen'],
            ['JBT-017', 'Bagian Bahasa', 4, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'BookOpen'],
            ['JBT-018', 'Bagian Program Khusus', 4, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Sparkles'],
            ['JBT-019', 'Bagian Kesiswaan', 4, 'Bidang Pendidikan', 'Divisi Pendidikan', 'bidang_pendidikan', 'Users'],

            ['JBT-003', 'Kepala Sekolah', 5, 'Unit Pendidikan', 'Kepala Sekolah', 'unit_sendiri', 'UserTie'],
            ['JBT-004', 'Wakil Kurikulum', 6, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],
            ['JBT-020', 'Wakil Kesiswaan/Kesantrian', 6, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],
            ['JBT-021', 'Wakil Humas dan Program Khusus', 6, 'Unit Pendidikan', 'Wakil Kepala Sekolah', 'unit_sendiri', 'UserCheck'],
            ['JBT-006', 'Kepala Tata Usaha', 7, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Building'],
            ['JBT-008', 'Bendahara', 8, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Wallet'],
            ['JBT-007', 'Operator Sekolah', 8, 'Unit Pendidikan', 'Tata Usaha', 'unit_sendiri', 'Laptop'],
            ['JBT-010', 'Wali Kelas', 9, 'Unit Pendidikan', 'Wali Kelas', 'rombel_sendiri', 'Users'],
            ['JBT-009', 'Guru Mapel', 10, 'Unit Pendidikan', 'Guru', 'kelas_mapel_sendiri', 'GraduationCap'],
            ['JBT-022', 'Guru BK', 10, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'HeartHandshake'],
            ['JBT-011', 'Guru Tahfizh', 10, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'BookOpen'],
            ['JBT-023', 'Musyrif/Asrama', 10, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'Building'],
            ['JBT-024', 'Pembina Ekstrakurikuler', 10, 'Unit Pendidikan', 'Guru', 'siswa_binaan', 'Award'],
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

        $tataUsahaRole = Role::where('name', 'Tata Usaha')->where('guard_name', 'web')->first();
        Position::where('code', 'JBT-012')->update([
            'satuan_kerja' => 'Unit Pendidikan',
            'scope_akses' => 'unit_sendiri',
            'role_sistem_id' => $tataUsahaRole?->id,
        ]);
        Position::whereIn('code', ['JBT-013', 'JBT-014'])->update([
            'satuan_kerja' => 'Unit Pendidikan',
            'scope_akses' => 'unit_sendiri',
        ]);

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
