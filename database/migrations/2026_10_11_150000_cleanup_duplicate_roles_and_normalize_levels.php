<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Peta dari nama alias duplikat ke nama role resmi (Canonical Title Case)
        $aliasToCanonical = [
            // Level 1: Super Admin
            'super_admin' => 'Super Admin',
            'Superadmin' => 'Super Admin',

            // Level 2: Admin
            'admin' => 'Admin',

            // Level 3: Operator & Tata Usaha
            'operator' => 'Operator',
            'TU' => 'Tata Usaha',
            'tu' => 'Tata Usaha',
            'tata_usaha' => 'Tata Usaha',

            // Level 4: Kepala Sekolah
            'kepala_sekolah' => 'Kepala Sekolah',
            'kepsek' => 'Kepala Sekolah',

            // Level 5: Pimpinan Yayasan / Waka / Divisi
            'ketua_yayasan' => 'Ketua Yayasan',
            'sekretaris_yayasan' => 'Sekretaris Yayasan',
            'bendahara_yayasan' => 'Bendahara Yayasan',
            'pengurus_yayasan' => 'Pengurus Yayasan',
            'Yayasan' => 'Pengurus Yayasan',
            'waka_kurikulum' => 'Waka Kurikulum',
            'Wakil Kurikulum' => 'Waka Kurikulum',
            'waka_kesiswaan' => 'Waka Kesiswaan',
            'Wakil Kesiswaan' => 'Waka Kesiswaan',
            'divisi_pendidikan' => 'Divisi Pendidikan',

            // Level 6: Guru & Pendidik
            'guru' => 'Guru',
            'guru_mata_pelajaran' => 'Guru Mata Pelajaran',
            'walas' => 'Wali Kelas',
            'wali_kelas' => 'Wali Kelas',
            'guru_tahfizh' => 'Guru Tahfizh',
            'guru_bk' => 'Guru BK',

            // Level 7: Musyrif
            'musyrif' => 'Musyrif',
            'Musyrif / Musyrifah' => 'Musyrif',

            // Level 8: Orang Tua
            'orang_tua' => 'Orang Tua',
            'Orangtua' => 'Orang Tua',
            'Wali Murid' => 'Orang Tua',
            'parent' => 'Orang Tua',

            // Level 9: Siswa
            'siswa' => 'Siswa',
            'student' => 'Siswa',
        ];

        // 2. Migrasikan pengguna dari role alias ke role resmi
        foreach ($aliasToCanonical as $aliasName => $canonicalName) {
            $aliasRole = DB::table('roles')->where('name', $aliasName)->first();
            $canonicalRole = DB::table('roles')->where('name', $canonicalName)->first();

            if (! $aliasRole || ! $canonicalRole) {
                continue;
            }

            // Ambil seluruh user yang memiliki role alias
            $userAssignments = DB::table('model_has_roles')
                ->where('role_id', $aliasRole->id)
                ->get();

            foreach ($userAssignments as $assignment) {
                // Periksa apakah user sudah memiliki role resmi
                $alreadyHasCanonical = DB::table('model_has_roles')
                    ->where('role_id', $canonicalRole->id)
                    ->where('model_type', $assignment->model_type)
                    ->where('model_id', $assignment->model_id)
                    ->exists();

                if ($alreadyHasCanonical) {
                    // Jika sudah punya, hapus assignment alias
                    DB::table('model_has_roles')
                        ->where('role_id', $aliasRole->id)
                        ->where('model_type', $assignment->model_type)
                        ->where('model_id', $assignment->model_id)
                        ->delete();
                } else {
                    // Jika belum, perbarui role_id ke role resmi
                    DB::table('model_has_roles')
                        ->where('role_id', $aliasRole->id)
                        ->where('model_type', $assignment->model_type)
                        ->where('model_id', $assignment->model_id)
                        ->update(['role_id' => $canonicalRole->id]);
                }
            }

            // Pindahkan permission dari alias role ke canonical role
            $aliasPermissions = DB::table('role_has_permissions')
                ->where('role_id', $aliasRole->id)
                ->pluck('permission_id');

            foreach ($aliasPermissions as $permId) {
                $alreadyHasPerm = DB::table('role_has_permissions')
                    ->where('role_id', $canonicalRole->id)
                    ->where('permission_id', $permId)
                    ->exists();

                if (! $alreadyHasPerm) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $canonicalRole->id,
                        'permission_id' => $permId,
                    ]);
                }
            }

            // Hapus referensi permission pada alias role
            DB::table('role_has_permissions')->where('role_id', $aliasRole->id)->delete();

            // Hapus sisa assignment di model_has_roles (jika ada)
            DB::table('model_has_roles')->where('role_id', $aliasRole->id)->delete();

            // Hapus role alias dari tabel roles
            DB::table('roles')->where('id', $aliasRole->id)->delete();
        }

        // 3. Pastikan seluruh role resmi yang tersisa memiliki level yang benar dan berurutan
        $canonicalLevels = [
            1 => ['Super Admin'],
            2 => ['Admin'],
            3 => ['Operator', 'Tata Usaha'],
            4 => ['Kepala Sekolah'],
            5 => [
                'Ketua Yayasan',
                'Sekretaris Yayasan',
                'Bendahara Yayasan',
                'Pengurus Yayasan',
                'Wakil Kepala Sekolah',
                'Waka Kurikulum',
                'Waka Kesiswaan',
                'Divisi Pendidikan',
                'Kepala Bidang Pendidikan',
                'Divisi Kurikulum',
                'Divisi Kesiswaan',
                'Divisi Bahasa',
                'Divisi Program Khusus',
                'Wakil Humas dan Program Khusus',
            ],
            6 => [
                'Guru',
                'Guru Mata Pelajaran',
                'Wali Kelas',
                'Guru Tahfizh',
                'Guru BK',
                'Guru PAI',
                'Pembimbing',
            ],
            7 => ['Musyrif', 'Musyrifah'],
            8 => ['Orang Tua'],
            9 => ['Siswa'],
            10 => ['Alumni'],
        ];

        foreach ($canonicalLevels as $lvl => $roleNames) {
            DB::table('roles')->whereIn('name', $roleNames)->update(['level' => $lvl]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for deduplication
    }
};
