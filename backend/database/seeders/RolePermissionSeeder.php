<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Yayasan',
            'Kepala Sekolah',
            'Wakil Kepala Sekolah',
            'Divisi Pendidikan',
            'Tata Usaha',
            'Guru',
            'Wali Kelas',
            'Orang Tua',
            'Siswa',
        ];

        $permissions = [
            // Dashboard & Pemantauan
            'dashboard.view',
            'dashboard.pemantauan.lihat',
            'dashboard.pemantauan.kelola',
            'divisi.monitoring',
            'divisi.laporan_bulanan',

            // Kehadiran & Absensi Siswa
            'kehadiran.siswa.monitoring',
            'kehadiran.siswa.absensi_digital',
            'kehadiran.siswa.rekap_keterlambatan',
            'kehadiran.siswa.rekap_ketidakhadiran',
            'kehadiran.siswa.barcode_kartu',
            'kehadiran.siswa.izin_sakit',

            // Tahfizh & Mutabaah
            'tahfizh.monitoring_target',
            'tahfizh.input_setoran_harian',
            'tahfizh.hitung_baris',
            'tahfizh.rekap_harian',
            'tahfizh.rekap_mingguan',
            'tahfizh.rekap_bulanan',
            'tahfizh.rekap_tahunan',
            'tahfizh.mutabaah_yaumiyah',
            'tahfizh.laporan_target',
            'tahfizh.perhitungan_tercapai',
            'tahfizh.hafalan_terbanyak',
            'tahfizh.total_hafalan',

            // Kesiswaan & Kelulusan
            'kesiswaan.rekap_prestasi',
            'kesiswaan.kelulusan_per_unit',
            'kesiswaan.kelulusan_per_tahun',
            'kesiswaan.alumni_tujuan_lanjut',
            'kesiswaan.data_lengkap_siswa',
            'kesiswaan.kelas_rombel',
            'kesiswaan.laporan_masuk_keluar',
            'kesiswaan.penugasan_siswa',
            'kesiswaan.catatan_siswa',

            // Pembelajaran & Kurikulum
            'pembelajaran.kurikulum.view',
            'pembelajaran.kurikulum.create',
            'pembelajaran.kurikulum.edit',
            'pembelajaran.kurikulum.delete',
            'pembelajaran.kurikulum.restore',
            'pembelajaran.kurikulum.export',
            'pembelajaran.kurikulum.import',
            'pembelajaran.materi',
            'pembelajaran.kisi_kisi_ujian',
            'pembelajaran.bank_soal',
            'pembelajaran.jadwal_pelajaran',
            'pembelajaran.kalender_pendidikan',

            // Informasi Sekolah & Data Pribadi
            'sekolah.data_pribadi_siswa',
            'sekolah.informasi_sekolah',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissionMap = [
            'Super Admin' => $permissions,
            'Kepala Sekolah' => $permissions,
            'Yayasan' => [
                'dashboard.view',
                'dashboard.pemantauan.lihat',
                'divisi.monitoring',
                'divisi.laporan_bulanan',
                'kesiswaan.rekap_prestasi',
                'kesiswaan.kelulusan_per_tahun',
            ],
            'Divisi Pendidikan' => [
                'dashboard.view',
                'dashboard.pemantauan.lihat',
                'dashboard.pemantauan.kelola',
                'divisi.monitoring',
                'divisi.laporan_bulanan',
                'kesiswaan.rekap_prestasi',
                'tahfizh.monitoring_target',
                'tahfizh.input_setoran_harian',
                'tahfizh.hitung_baris',
                'tahfizh.rekap_harian',
                'tahfizh.rekap_mingguan',
                'tahfizh.rekap_bulanan',
                'tahfizh.rekap_tahunan',
                'tahfizh.mutabaah_yaumiyah',
                'tahfizh.perhitungan_tercapai',
                'tahfizh.hafalan_terbanyak',
                'tahfizh.total_hafalan',
                'kesiswaan.kelulusan_per_unit',
                'kesiswaan.kelulusan_per_tahun',
                'kesiswaan.alumni_tujuan_lanjut',
                'kesiswaan.data_lengkap_siswa',
                'kesiswaan.kelas_rombel',
                'kesiswaan.laporan_masuk_keluar',
                'pembelajaran.jadwal_pelajaran',
                'pembelajaran.kalender_pendidikan',
            ],
            'Tata Usaha' => [
                'dashboard.view',
                'kehadiran.siswa.absensi_digital',
                'kehadiran.siswa.rekap_keterlambatan',
                'kehadiran.siswa.rekap_ketidakhadiran',
                'kesiswaan.kelulusan_per_tahun',
                'kesiswaan.alumni_tujuan_lanjut',
                'kesiswaan.data_lengkap_siswa',
                'kesiswaan.penugasan_siswa',
                'kesiswaan.catatan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
                'pembelajaran.jadwal_pelajaran',
                'pembelajaran.kalender_pendidikan',
                'sekolah.data_pribadi_siswa',
            ],
            'Guru' => [
                'dashboard.view',
                'kehadiran.siswa.monitoring',
                'kehadiran.siswa.absensi_digital',
                'tahfizh.monitoring_target',
                'tahfizh.input_setoran_harian',
                'tahfizh.hitung_baris',
                'tahfizh.rekap_harian',
                'tahfizh.rekap_mingguan',
                'tahfizh.rekap_bulanan',
                'tahfizh.rekap_tahunan',
                'tahfizh.mutabaah_yaumiyah',
                'tahfizh.perhitungan_tercapai',
                'tahfizh.hafalan_terbanyak',
                'tahfizh.total_hafalan',
                'kesiswaan.penugasan_siswa',
                'kesiswaan.catatan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
                'pembelajaran.jadwal_pelajaran',
                'pembelajaran.kalender_pendidikan',
            ],
            'Wali Kelas' => [
                'dashboard.view',
                'kehadiran.siswa.monitoring',
                'kehadiran.siswa.absensi_digital',
                'tahfizh.monitoring_target',
                'tahfizh.input_setoran_harian',
                'tahfizh.hitung_baris',
                'tahfizh.rekap_harian',
                'tahfizh.rekap_mingguan',
                'tahfizh.rekap_bulanan',
                'tahfizh.rekap_tahunan',
                'tahfizh.mutabaah_yaumiyah',
                'kesiswaan.penugasan_siswa',
                'kesiswaan.catatan_siswa',
                'pembelajaran.materi',
                'pembelajaran.jadwal_pelajaran',
            ],
            'Orang Tua' => [
                'dashboard.view',
                'kehadiran.siswa.barcode_kartu',
                'kehadiran.siswa.izin_sakit',
                'tahfizh.input_setoran_harian',
                'tahfizh.laporan_target',
                'tahfizh.mutabaah_yaumiyah',
                'sekolah.data_pribadi_siswa',
                'sekolah.informasi_sekolah',
                'kesiswaan.penugasan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
            ],
            'Siswa' => [
                'dashboard.view',
                'kehadiran.siswa.barcode_kartu',
                'kehadiran.siswa.izin_sakit',
                'tahfizh.input_setoran_harian',
                'tahfizh.laporan_target',
                'tahfizh.mutabaah_yaumiyah',
                'sekolah.data_pribadi_siswa',
                'sekolah.informasi_sekolah',
                'kesiswaan.penugasan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
            ],
        ];

        foreach ($roles as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if (isset($rolePermissionMap[$roleName])) {
                $role->syncPermissions($rolePermissionMap[$roleName]);
            }
        }
    }
}

