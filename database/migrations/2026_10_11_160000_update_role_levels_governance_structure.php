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

        // Pemetaan tingkatan hierarki tata kelola lembaga yang telah diselaraskan:
        // Tingkat 1: Super Admin & Admin (IT & System Admin)
        // Tingkat 2: Pimpinan & Pengurus Yayasan (Badan Hukum Lembaga)
        // Tingkat 3: Operator & Tata Usaha (Pelaksana Administrasi & Data Operasional)
        // Tingkat 4: Kepala Sekolah & Divisi Pendidikan (Pimpinan Satuan Pendidikan & Bidang)
        // Tingkat 5: Tenaga Pendidik / Guru
        // Tingkat 6: Musyrif Asrama
        // Tingkat 7: Orang Tua / Wali Murid
        // Tingkat 8: Siswa / Santri
        // Tingkat 9: Alumni
        $governanceLevels = [
            1 => [
                'Super Admin',
                'Admin',
            ],
            2 => [
                'Ketua Yayasan',
                'Sekretaris Yayasan',
                'Bendahara Yayasan',
                'Pengurus Yayasan',
            ],
            3 => [
                'Operator',
                'Tata Usaha',
            ],
            4 => [
                'Kepala Sekolah',
                'Wakil Kepala Sekolah',
                'Waka Kurikulum',
                'Waka Kesiswaan',
                'Wakil Humas dan Program Khusus',
                'Divisi Pendidikan',
                'Kepala Bidang Pendidikan',
                'Divisi Kurikulum',
                'Divisi Kesiswaan',
                'Divisi Bahasa',
                'Divisi Program Khusus',
            ],
            5 => [
                'Guru',
                'Guru Mata Pelajaran',
                'Wali Kelas',
                'Guru Tahfizh',
                'Guru BK',
                'Guru PAI',
                'Pembimbing',
            ],
            6 => [
                'Musyrif',
                'Musyrifah',
            ],
            7 => [
                'Orang Tua',
            ],
            8 => [
                'Siswa',
            ],
            9 => [
                'Alumni',
            ],
        ];

        foreach ($governanceLevels as $level => $roleNames) {
            DB::table('roles')
                ->whereIn('name', $roleNames)
                ->update(['level' => $level]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversible if needed
    }
};
