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
            'super_admin',
            'Yayasan',
            'Ketua Yayasan',
            'Sekretaris Yayasan',
            'Bendahara Yayasan',
            'ketua_yayasan',
            'sekretaris_yayasan',
            'bendahara_yayasan',
            'pengurus_yayasan',
            'Pengurus Yayasan',
            'Kepala Sekolah',
            'kepala_sekolah',
            'kepsek',
            'Wakil Kepala Sekolah',
            'Wakil Kurikulum',
            'Wakil Kesiswaan',
            'Waka Kurikulum',
            'waka_kurikulum',
            'Waka Kesiswaan',
            'waka_kesiswaan',
            'Divisi Pendidikan',
            'Kepala Bidang Pendidikan',
            'Divisi Kurikulum',
            'Divisi Kesiswaan',
            'Divisi Bahasa',
            'Divisi Program Khusus',
            'divisi_pendidikan',
            'Tata Usaha',
            'TU',
            'tu',
            'tata_usaha',
            'Guru',
            'guru',
            'Guru Mata Pelajaran',
            'guru_mata_pelajaran',
            'Wali Kelas',
            'walas',
            'wali_kelas',
            'Pembimbing',
            'Guru Tahfizh',
            'guru_tahfizh',
            'Guru BK',
            'guru_bk',
            'Guru PAI',
            'Operator',
            'operator',
            'Admin',
            'Musyrif',
            'musyrif',
            'Musyrifah',
            'Musyrif / Musyrifah',
            'Orang Tua',
            'orang_tua',
            'Orangtua',
            'Wali Murid',
            'parent',
            'Siswa',
            'siswa',
            'student',
            'Alumni',
            'alumni',
        ];

        $permissions = [
            // Vocabulary permission lintas-modul. Permission granular lama
            // tetap dipertahankan untuk menjaga kontrak API dan business flow.
            'dashboard.manage',
            'master.view',
            'master.create',
            'master.update',
            'master.delete',
            'academic.view',
            'academic.manage',
            'attendance.view',
            'attendance.manage',
            'lms.view',
            'lms.manage',
            'cbt.manage',
            'grades.manage',
            'report.view',
            'report.export',
            'portal.view',
            'approval.manage',
            'notification.manage',
            'chat.manage',
            'setting.manage',
            'user.manage',
            'permission.manage',
            'role.manage',
            'audit.view',
            'activity.view',

            // Dashboard & Pemantauan
            'dashboard.view',
            'dashboard.super-admin.view',
            'dashboard.kepala-sekolah.view',
            'dashboard.divisi-pendidikan.view',
            'dashboard.waka-kurikulum.view',
            'dashboard.waka-kesiswaan.view',
            'dashboard.tata-usaha.view',
            'dashboard.guru.view',
            'dashboard.guru-tahfizh.view',
            'dashboard.guru-bk.view',
            'dashboard.operator.view',
            'dashboard.pemantauan.lihat',
            'dashboard.pemantauan.kelola',
            'divisi.monitoring',
            'divisi.laporan_bulanan',

            // Dashboard Pengurus Yayasan (Foundation Monitoring)
            'foundation.dashboard.view',
            'foundation.unit.view',
            'foundation.employee.view',
            'foundation.teacher.view',
            'foundation.student.view',
            'foundation.student_new.view',
            'foundation.student_mutation.view',
            'foundation.graduation.view',
            'foundation.alumni.view',
            'foundation.information.view',
            'foundation.report.view',
            'foundation.report.export',
            'foundation.notification.view',
            'foundation.notification.read',
            'foundation.notification.read_all',
            'foundation.profile.view',
            'foundation.profile.update',
            'foundation.profile.change_password',

            // Kehadiran & Absensi Siswa
            'kehadiran.siswa.monitoring',
            'kehadiran.siswa.absensi_digital',
            'kehadiran.siswa.rekap_keterlambatan',
            'kehadiran.siswa.rekap_ketidakhadiran',
            'kehadiran.siswa.barcode_kartu',
            'kehadiran.siswa.izin_sakit',

            // Presensi Pembelajaran berbasis jadwal
            'lesson_attendance.view',
            'lesson_attendance.view_own',
            'lesson_attendance.create',
            'lesson_attendance.update',
            'lesson_attendance.finalize',
            'lesson_attendance.unlock',
            'lesson_attendance.cancel',
            'lesson_attendance.correct',
            'lesson_attendance.export',
            'homeroom_attendance.dashboard',
            'homeroom_attendance.view',
            'homeroom_attendance.follow_up',
            'homeroom_attendance.verify_permission',
            'homeroom_attendance.export',
            'student_attendance.view_own',
            'student_attendance.permission.create',
            'student_attendance.permission.update',
            'student_attendance.permission.cancel',
            'lesson_attendance.manual',
            'lesson_attendance.qr_scan',
            'lesson_attendance.barcode_scan',
            'lesson_attendance.face_scan',
            'lesson_attendance.fingerprint_scan',
            'lesson_attendance.session.start',
            'lesson_attendance.session.close',
            'lesson_attendance.scan_logs.view',
            'attendance_device.view',
            'attendance_device.manage',
            'attendance_device.receive_event',

            // Step 04: teacher card, teaching attendance, session, presence.
            'teaching_attendance.scan',
            'teaching_attendance.view_own',
            'teaching_session.start',
            'teaching_session.close',
            'teacher_presence.heartbeat',
            'teacher_monitoring.view',

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
            'mutabaah.view',
            'mutabaah.create',
            'mutabaah.update',
            'mutabaah.delete',
            'mutabaah.restore',
            'mutabaah.daily.reopen',
            'mutabaah.export',
            'mutabaah.import',
            'mutabaah.approve',
            'mutabaah.print',
            'mutabaah.input',
            'mutabaah.agenda.manage',
            'mutabaah.dashboard.view',
            'mutabaah.category.view', 'mutabaah.category.create', 'mutabaah.category.update', 'mutabaah.category.delete', 'mutabaah.category.restore',
            'mutabaah.agenda.view', 'mutabaah.agenda.create', 'mutabaah.agenda.update', 'mutabaah.agenda.delete', 'mutabaah.agenda.restore',
            'mutabaah.template.view', 'mutabaah.template.create', 'mutabaah.template.update', 'mutabaah.template.delete', 'mutabaah.template.assign',
            'mutabaah.supervisor.view', 'mutabaah.supervisor.create', 'mutabaah.supervisor.update', 'mutabaah.supervisor.delete',
            'mutabaah.daily.view', 'mutabaah.daily.input', 'mutabaah.daily.update', 'mutabaah.daily.finalize', 'mutabaah.daily.reopen',
            'mutabaah.recap.view', 'mutabaah.report.view', 'mutabaah.report.export',
            'mutabaah.parent.monitor', 'mutabaah.parent.sign',

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

            // Administrasi sistem (khusus Super Admin)
            'sistem.hak_akses',
            'employee.role_access.manage',
            'employee.position.manage',
            'sistem.pengaturan',
            'sistem.master_data',

            // Portal Guru
            'teacher.dashboard.view',
            'teacher.schedule.view',
            'teacher.attendance.view',
            'teacher.attendance.create',
            'teacher.attendance.update',
            'teacher.material.view',
            'teacher.material.create',
            'teacher.material.update',
            'teacher.material.delete',
            'teacher.assignment.view',
            'teacher.assignment.create',
            'teacher.assignment.update',
            'teacher.assignment.delete',
            'teacher.submission.view',
            'teacher.grade.view',
            'teacher.grade.create',
            'teacher.grade.update',
            'teacher.tahfizh.view',
            'teacher.tahfizh.create',
            'teacher.tahfizh.update',
            'teacher.mutabaah.view',
            'teacher.mutabaah.create',
            'teacher.mutabaah.update',
            'teacher.student_note.view',
            'teacher.student_note.create',
            'teacher.student_note.update',

            // Portal Orang Tua
            'parent.portal.view',
            'parent.child.view',
            'parent.attendance.view',
            'parent.permission.create',
            'parent.assignment.view',
            'parent.grade.view',
            'parent.tahfizh.view',
            'parent.mutabaah.view',
            'parent.notification.view',

            // Portal Siswa
            'student.portal.view',
            'student.profile.view',
            'student.attendance.view',
            'student.material.view',
            'student.assignment.view',
            'student.submission.create',
            'student.submission.update',
            'student.grade.view',
            'student.tahfizh.view',
            'student.mutabaah.view',
            'student.mutabaah.create',
            'student.notification.view',

            // Komunikasi Sekolah & Chat Guru
            'chat.conversation.view',
            'chat.conversation.create',
            'chat.conversation.archive',
            'chat.message.view',
            'chat.message.send',
            'chat.message.read',
            'chat.message.attachment',
            'chat.homeroom.view',
            'chat.subject_teacher.view',
            'chat.analytics.view',
            'chat.configuration.manage',

            // Standard Granular Permission Format module.action
            'student.view', 'student.view_all', 'student.view_own', 'student.create', 'student.update', 'student.delete', 'student.export', 'student.import',
            'employee.view', 'employee.view_all', 'employee.create', 'employee.update', 'employee.delete', 'employee.export', 'employee.import',
            'unit.view', 'unit.view_all', 'unit.create', 'unit.update', 'unit.delete',
            'academic.subject.view', 'academic.subject.create', 'academic.subject.update', 'academic.subject.delete',
            'academic.schedule.view', 'academic.schedule.create', 'academic.schedule.update', 'academic.schedule.delete',
            'academic.curriculum.view', 'academic.curriculum.create', 'academic.curriculum.update', 'academic.curriculum.delete',
            'academic.grade.view', 'academic.grade.create', 'academic.grade.update', 'academic.grade.finalize', 'academic.grade.publish',
            'academic.rapor.view', 'academic.rapor.create', 'academic.rapor.update', 'academic.rapor.publish',
            'tahfizh.deposit.view', 'tahfizh.deposit.create', 'tahfizh.deposit.update', 'tahfizh.deposit.finalize',
            'kesiswaan.violation.view', 'kesiswaan.violation.create', 'kesiswaan.violation.update',
            'kesiswaan.achievement.view', 'kesiswaan.achievement.create', 'kesiswaan.achievement.update',
            'bk.counseling.view', 'bk.counseling.create', 'bk.counseling.update',
            'asrama.activity.view', 'asrama.activity.manage',
            'alumni.view', 'alumni.update_own', 'alumni.tracer_study.create',
            'report.student.view', 'report.student.export',
            'report.academic.view', 'report.academic.export',
            'report.attendance.view', 'report.attendance.export',
            'report.tahfizh.view', 'report.tahfizh.export',
            'report.cross_unit.view', 'report.cross_unit.export',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $rolePermissionMap = [
            'Super Admin' => $permissions,
            'super_admin' => $permissions,
            'Admin' => [
                'dashboard.view',
                'dashboard.pemantauan.lihat',
                'master.view', 'master.create', 'master.update', 'master.delete',
                'employee.view', 'employee.view_all', 'employee.create', 'employee.update',
                'employee.delete', 'employee.export', 'employee.import',
                'unit.view', 'unit.view_all',
                'sistem.hak_akses', 'sistem.master_data',
                'permission.manage', 'role.manage',
            ],
            'Kepala Sekolah' => [
                'dashboard.view',
                'dashboard.pemantauan.lihat',
                'dashboard.pemantauan.kelola',
                'employee.view',
                'divisi.monitoring',
                'kehadiran.siswa.monitoring',
                'kehadiran.siswa.absensi_digital',
                'kehadiran.siswa.rekap_keterlambatan',
                'kehadiran.siswa.rekap_ketidakhadiran',
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
                'kesiswaan.rekap_prestasi',
                'kesiswaan.kelulusan_per_unit',
                'kesiswaan.kelulusan_per_tahun',
                'kesiswaan.alumni_tujuan_lanjut',
                'kesiswaan.data_lengkap_siswa',
                'kesiswaan.kelas_rombel',
                'kesiswaan.laporan_masuk_keluar',
                'kesiswaan.penugasan_siswa',
                'kesiswaan.catatan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
                'pembelajaran.jadwal_pelajaran',
                'pembelajaran.kalender_pendidikan',
            ],
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
                'employee.view',
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
                'kesiswaan.penugasan_siswa',
                'kesiswaan.catatan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
                'pembelajaran.jadwal_pelajaran',
                'pembelajaran.kalender_pendidikan',
            ],
            'Guru' => [
                'dashboard.view',
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
                'lesson_attendance.view_own',
                'lesson_attendance.create',
                'lesson_attendance.update',
                'lesson_attendance.finalize',
                'lesson_attendance.cancel',
                'lesson_attendance.correct',
                'lesson_attendance.export',
                'lesson_attendance.manual',
                'lesson_attendance.qr_scan',
                'lesson_attendance.barcode_scan',
                'lesson_attendance.face_scan',
                'lesson_attendance.fingerprint_scan',
                'lesson_attendance.session.start',
                'lesson_attendance.session.close',
                'lesson_attendance.scan_logs.view',
                'teaching_attendance.scan',
                'teaching_attendance.view_own',
                'teaching_session.start',
                'teaching_session.close',
                'teacher_presence.heartbeat',
                'chat.conversation.view',
                'chat.message.view',
                'chat.message.send',
                'chat.message.read',
                'chat.message.attachment',
                'chat.subject_teacher.view',
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
                'homeroom_attendance.dashboard',
                'homeroom_attendance.view',
                'homeroom_attendance.follow_up',
                'homeroom_attendance.verify_permission',
                'homeroom_attendance.export',
                'lesson_attendance.correct',
                'chat.conversation.view',
                'chat.message.view',
                'chat.message.send',
                'chat.message.read',
                'chat.message.attachment',
                'chat.homeroom.view',
            ],
            'Orang Tua' => [
                'dashboard.view',
                'parent.portal.view',
                'parent.child.view',
                'parent.attendance.view',
                'parent.permission.create',
                'parent.assignment.view',
                'parent.grade.view',
                'parent.tahfizh.view',
                'parent.mutabaah.view',
                'parent.notification.view',
                'kehadiran.siswa.barcode_kartu',
                'kehadiran.siswa.izin_sakit',
                'tahfizh.laporan_target',
                'sekolah.data_pribadi_siswa',
                'sekolah.informasi_sekolah',
                'kesiswaan.penugasan_siswa',
                'pembelajaran.materi',
                'pembelajaran.kisi_kisi_ujian',
                'pembelajaran.bank_soal',
                'chat.conversation.view',
                'chat.conversation.create',
                'chat.message.view',
                'chat.message.send',
                'chat.message.read',
                'chat.message.attachment',
                'chat.homeroom.view',
                'chat.subject_teacher.view',
            ],
            'Siswa' => [
                'dashboard.view',
                'student.portal.view',
                'student.profile.view',
                'student.attendance.view',
                'student.material.view',
                'student.assignment.view',
                'student.submission.create',
                'student.submission.update',
                'student.grade.view',
                'student.tahfizh.view',
                'student.mutabaah.view',
                 'student.mutabaah.create',
                 'student.notification.view',
                 'kehadiran.siswa.barcode_kartu',
                 'tahfizh.laporan_target',
                'sekolah.data_pribadi_siswa',
                'sekolah.informasi_sekolah',
                'kesiswaan.penugasan_siswa',
                'pembelajaran.materi',
                 'pembelajaran.kisi_kisi_ujian',
                 'pembelajaran.bank_soal',
                 'student_attendance.view_own',
             ],
        ];

        $foundationPerms = [
            'dashboard.view',
            'foundation.dashboard.view',
            'foundation.unit.view',
            'foundation.employee.view',
            'foundation.teacher.view',
            'foundation.student.view',
            'foundation.student_new.view',
            'foundation.student_mutation.view',
            'foundation.graduation.view',
            'foundation.alumni.view',
            'foundation.information.view',
            'foundation.report.view',
            'foundation.report.export',
            'foundation.notification.view',
            'foundation.notification.read',
            'foundation.notification.read_all',
            'foundation.profile.view',
            'foundation.profile.update',
            'foundation.profile.change_password',
            'employee.view',
            'report.cross_unit.view',
            'report.cross_unit.export',
            'teacher_monitoring.view',
        ];
        $readAll = ['mutabaah.dashboard.view', 'mutabaah.recap.view', 'mutabaah.report.view', 'mutabaah.report.export'];

        foreach (['Ketua Yayasan', 'Yayasan', 'ketua_yayasan', 'Sekretaris Yayasan', 'sekretaris_yayasan', 'Bendahara Yayasan', 'bendahara_yayasan', 'pengurus_yayasan', 'Pengurus Yayasan'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap[$roleName] ?? ['dashboard.view'], $readAll, $foundationPerms)));
        }

        // Hanya Admin dan Pengurus Yayasan yang mendapat kemampuan mutasi
        // global. Role yayasan lain tetap bersifat monitoring/read-only.
        $globalPersonnelPermissions = [
            'sistem.hak_akses', 'sistem.master_data',
            'master.view', 'master.create', 'master.update', 'master.delete',
            'employee.view', 'employee.view_all', 'employee.create', 'employee.update',
            'employee.delete', 'employee.export', 'employee.import',
            'unit.view', 'unit.view_all', 'permission.manage', 'role.manage',
        ];
        foreach (['Admin', 'Pengurus Yayasan', 'pengurus_yayasan'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $globalPersonnelPermissions,
            )));
        }
        foreach (['Kepala Sekolah', 'kepala_sekolah', 'kepsek'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Kepala Sekolah'] ?? [], [
                ...$readAll, 'mutabaah.supervisor.view', 'mutabaah.parent.monitor', 'report.academic.view', 'report.student.view',
            ])));
        }
        foreach (['Waka Kurikulum', 'waka_kurikulum'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Waka Kurikulum'] ?? [], [
                'pembelajaran.kurikulum.view', 'pembelajaran.kurikulum.create', 'pembelajaran.kurikulum.edit',
                'academic.curriculum.view', 'academic.curriculum.create', 'academic.curriculum.update',
                'academic.subject.view', 'academic.subject.create', 'academic.schedule.view', 'academic.schedule.create',
                'pembelajaran.materi', 'pembelajaran.kisi_kisi_ujian', 'pembelajaran.bank_soal', 'pembelajaran.jadwal_pelajaran',
            ])));
        }
        foreach (['Waka Kesiswaan', 'waka_kesiswaan'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Waka Kesiswaan'] ?? [], [
                'kesiswaan.rekap_prestasi', 'kesiswaan.data_lengkap_siswa', 'kesiswaan.kelas_rombel', 'kesiswaan.penugasan_siswa', 'kesiswaan.catatan_siswa',
                'kesiswaan.violation.view', 'kesiswaan.violation.create', 'kesiswaan.violation.update',
                'kesiswaan.achievement.view', 'kesiswaan.achievement.create', 'kesiswaan.achievement.update',
            ])));
        }
        $tuPermissions = [
            'mutabaah.dashboard.view', 'mutabaah.recap.view', 'mutabaah.report.view', 'mutabaah.report.export', 'mutabaah.parent.monitor',
            'mutabaah.category.view', 'mutabaah.category.create', 'mutabaah.category.update', 'mutabaah.category.delete', 'mutabaah.category.restore',
            'mutabaah.agenda.view', 'mutabaah.agenda.create', 'mutabaah.agenda.update', 'mutabaah.agenda.delete', 'mutabaah.agenda.restore',
            'mutabaah.template.view', 'mutabaah.template.create', 'mutabaah.template.update', 'mutabaah.template.delete', 'mutabaah.template.assign',
            'mutabaah.supervisor.view', 'mutabaah.supervisor.create', 'mutabaah.supervisor.update', 'mutabaah.supervisor.delete',
            'student.view', 'student.create', 'student.update', 'student.export', 'student.import',
            'employee.view', 'employee.create', 'employee.update', 'employee.export',
        ];
        foreach (['Tata Usaha', 'TU', 'tu', 'operator', 'tata_usaha', 'Operator'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Tata Usaha'] ?? ['dashboard.view'], $tuPermissions)));
        }
        $dailyPermissions = ['mutabaah.daily.view', 'mutabaah.daily.input', 'mutabaah.daily.update', 'mutabaah.daily.finalize', 'mutabaah.recap.view'];
        foreach (['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran', 'Wali Kelas', 'walas', 'wali_kelas', 'Pembimbing', 'Guru PAI', 'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap[$roleName] ?? ['dashboard.view'], $dailyPermissions)));
        }
        foreach (['Guru BK', 'guru_bk'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Guru BK'] ?? ['dashboard.view'], [
                'bk.counseling.view', 'bk.counseling.create', 'bk.counseling.update', 'kesiswaan.catatan_siswa', 'student.view',
            ])));
        }
        foreach (['Orang Tua', 'orang_tua', 'Orangtua', 'Wali Murid', 'parent'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Orang Tua'] ?? [], ['mutabaah.daily.view', 'mutabaah.parent.sign'])));
        }
        foreach (['Siswa', 'siswa', 'student'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Siswa'] ?? [], ['mutabaah.daily.view'])));
        }
        foreach (['Alumni', 'alumni'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Alumni'] ?? [], ['alumni.view', 'alumni.update_own', 'alumni.tracer_study.create'])));
        }
        foreach (['Divisi Pendidikan', 'divisi_pendidikan'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap['Divisi Pendidikan'] ?? [], ['divisi.monitoring', 'divisi.laporan_bulanan', 'report.cross_unit.view', 'employee.view'])));
        }

        // Pengelola unit hanya dapat mengubah assignment jabatan/role pada
        // pegawai di unitnya; definisi role dan permission tetap global.
        $unitAccessPermissions = ['employee.role_access.manage', 'employee.position.manage', 'employee.update', 'unit.view'];
        foreach ([
            'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
            'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
        ] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $unitAccessPermissions,
            )));
        }

        // Role kanonik baru mewarisi capability role lama yang ekuivalen.
        // Alias lama tidak dihapus supaya route dan kontrak otorisasi tetap utuh.
        $canonicalRoleTemplates = [
            'Kepala Bidang Pendidikan' => 'Divisi Pendidikan',
            'Divisi Kurikulum' => 'Waka Kurikulum',
            'Divisi Kesiswaan' => 'Waka Kesiswaan',
            'Divisi Bahasa' => 'Divisi Pendidikan',
            'Divisi Program Khusus' => 'Divisi Pendidikan',
            'Wakil Kepala Sekolah' => 'Waka Kurikulum',
            'Wakil Kurikulum' => 'Waka Kurikulum',
            'Wakil Kesiswaan' => 'Waka Kesiswaan',
            'Operator' => 'Tata Usaha',
            'Guru Tahfizh' => 'Guru',
            'Guru BK' => 'Guru',
            'Musyrif' => 'Guru',
            'Alumni' => 'Siswa',
        ];

        foreach ($canonicalRoleTemplates as $roleName => $templateRole) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$templateRole] ?? ['dashboard.view'],
                $rolePermissionMap[$roleName] ?? [],
            )));
        }

        // Portal guru tetap role-scoped, sedangkan setiap aksi dilindungi
        // permission granular agar capability dapat dicabut tanpa mengganti role.
        $teacherPortalCommon = [
            'teacher.dashboard.view',
            'teacher.schedule.view',
            'chat.conversation.view',
            'chat.message.view',
            'chat.message.send',
        ];
        $teacherAcademicPermissions = [
            'teacher.attendance.view', 'teacher.attendance.create', 'teacher.attendance.update',
            'teacher.material.view', 'teacher.material.create', 'teacher.material.update', 'teacher.material.delete',
            'teacher.assignment.view', 'teacher.assignment.create', 'teacher.assignment.update', 'teacher.assignment.delete',
            'teacher.submission.view',
            'teacher.grade.view', 'teacher.grade.create', 'teacher.grade.update',
            'teacher.tahfizh.view', 'teacher.tahfizh.create', 'teacher.tahfizh.update',
            'teacher.mutabaah.view', 'teacher.mutabaah.create', 'teacher.mutabaah.update',
            'teacher.student_note.view', 'teacher.student_note.create', 'teacher.student_note.update',
        ];

        foreach (['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran', 'Guru PAI'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $teacherPortalCommon,
                $teacherAcademicPermissions,
            )));
        }

        foreach (['Wali Kelas', 'walas', 'wali_kelas'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $teacherPortalCommon,
                ['teacher.attendance.view', 'teacher.assignment.view', 'teacher.grade.view',
                    'teacher.student_note.view', 'teacher.student_note.create', 'teacher.student_note.update'],
            )));
        }

        foreach (['Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $teacherPortalCommon,
                ['teacher.tahfizh.view', 'teacher.tahfizh.create', 'teacher.tahfizh.update',
                    'teacher.mutabaah.view', 'teacher.mutabaah.create', 'teacher.mutabaah.update'],
            )));
        }

        foreach (['Pembimbing'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $teacherPortalCommon,
                ['teacher.mutabaah.view', 'teacher.mutabaah.create', 'teacher.mutabaah.update'],
            )));
        }

        foreach (['Guru BK', 'guru_bk'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                $teacherPortalCommon,
                ['teacher.student_note.view', 'teacher.student_note.create', 'teacher.student_note.update'],
            )));
        }

        foreach ([
            'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
            'Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan',
            'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus',
        ] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                ['academic.grade.view'],
            )));
        }

        foreach (['Waka Kurikulum', 'waka_kurikulum', 'Wakil Kurikulum'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                ['academic.grade.view', 'academic.grade.create', 'academic.grade.update',
                    'academic.grade.finalize', 'academic.grade.publish'],
            )));
        }

        foreach (['Kepala Sekolah', 'Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Divisi Pendidikan', 'Kepala Bidang Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Waka Kurikulum', 'Wakil Kesiswaan', 'Waka Kesiswaan'] as $roleName) {
            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge(
                $rolePermissionMap[$roleName] ?? ['dashboard.view'],
                ['teacher_monitoring.view'],
            )));
        }

        $genericRead = ['portal.view', 'activity.view'];
        $genericReport = ['report.view', 'report.export', 'audit.view'];
        $genericAcademic = ['master.view', 'academic.view', 'attendance.view', 'lms.view'];
        $genericManage = ['dashboard.manage', 'master.create', 'master.update', 'academic.manage', 'attendance.manage', 'lms.manage', 'cbt.manage', 'grades.manage', 'approval.manage', 'notification.manage', 'chat.manage'];
        $foundationRoles = ['Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan'];
        $educationLeaders = ['Kepala Bidang Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Wakil Kesiswaan'];
        $unitOperators = ['Tata Usaha', 'Operator'];
        $educators = ['Guru', 'Guru Tahfizh', 'Guru BK', 'Wali Kelas', 'Musyrif'];

        foreach ($roles as $roleName) {
            if (! isset($rolePermissionMap[$roleName])) {
                continue;
            }

            $generic = $genericRead;
            if (in_array($roleName, [...$foundationRoles, ...$educationLeaders], true)) {
                $generic = [...$generic, ...$genericReport, ...$genericAcademic];
            }
            if (in_array($roleName, [...$educationLeaders, ...$unitOperators, ...$educators], true)) {
                $generic = [...$generic, ...$genericAcademic];
            }
            if (in_array($roleName, $unitOperators, true)) {
                $generic = [...$generic, ...$genericManage];
            }

            $rolePermissionMap[$roleName] = array_values(array_unique(array_merge($rolePermissionMap[$roleName], $generic)));
        }

        foreach (['Super Admin', 'super_admin'] as $roleName) {
            $rolePermissionMap[$roleName] = $permissions;
        }

        // These permissions were previously assigned through a broad leader
        // template even though canonical leaders are monitoring-only unless a
        // granular capability is listed above. Revoke only this known legacy
        // overgrant; administrator-managed unrelated permissions stay intact.
        $monitoringOnlyRevocations = [
            ...$genericManage,
            'dashboard.pemantauan.kelola',
            'divisi.laporan_bulanan',
        ];
        $monitoringOnlyRoles = [
            'Kepala Bidang Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan',
            'Divisi Bahasa', 'Divisi Program Khusus', 'Wakil Kepala Sekolah',
            'Wakil Kurikulum', 'Wakil Kesiswaan',
            'Kepala Sekolah', 'kepala_sekolah', 'kepsek',
        ];

        // Dashboard role-based access: setiap role hanya menerima permission dashboard
        // sesuai kewenangannya (sesuai DASHBOARD_ROLE_ROUTE_MATRIX).
        $dashboardAccessMap = [
            'dashboard.kepala-sekolah.view' => ['Kepala Sekolah', 'kepala_sekolah', 'kepsek'],
            'dashboard.divisi-pendidikan.view' => ['Divisi Pendidikan', 'divisi_pendidikan', 'Kepala Bidang Pendidikan', 'Divisi Bahasa', 'Divisi Program Khusus'],
            'dashboard.waka-kurikulum.view' => ['Waka Kurikulum', 'waka_kurikulum', 'Wakil Kepala Sekolah', 'Wakil Kurikulum', 'Divisi Kurikulum'],
            'dashboard.waka-kesiswaan.view' => ['Waka Kesiswaan', 'waka_kesiswaan', 'Wakil Kesiswaan', 'Divisi Kesiswaan'],
            'dashboard.tata-usaha.view' => ['Tata Usaha', 'TU', 'tu', 'tata_usaha'],
            'dashboard.operator.view' => ['Operator', 'operator', 'Tata Usaha', 'TU', 'tu', 'tata_usaha'],
            'dashboard.pemantauan.lihat' => ['Admin'],
            'dashboard.guru.view' => ['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran', 'Wali Kelas', 'walas', 'wali_kelas', 'Guru PAI', 'Pembimbing', 'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah', 'Guru BK', 'guru_bk'],
            'dashboard.guru-tahfizh.view' => ['Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah'],
            'dashboard.guru-bk.view' => ['Guru BK', 'guru_bk'],
            'teacher.dashboard.view' => ['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran', 'Wali Kelas', 'walas', 'wali_kelas', 'Guru PAI', 'Pembimbing', 'Guru Tahfizh', 'guru_tahfizh', 'Musyrif', 'musyrif', 'Musyrifah', 'Musyrif / Musyrifah', 'Guru BK', 'guru_bk'],
        ];

        foreach ($dashboardAccessMap as $dashboardPermission => $dashboardRoles) {
            foreach ($dashboardRoles as $dashboardRole) {
                $rolePermissionMap[$dashboardRole] = array_values(array_unique(array_merge(
                    $rolePermissionMap[$dashboardRole] ?? ['dashboard.view'],
                    [$dashboardPermission],
                )));
            }
        }

        foreach ($roles as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($roleName !== 'Super Admin' && $roleName !== 'super_admin' && isset($rolePermissionMap[$roleName])) {
                if (in_array($roleName, $monitoringOnlyRoles, true)) {
                    $role->revokePermissionTo($monitoringOnlyRevocations);
                }
                // Seeder hanya memastikan baseline. Permission tambahan yang
                // dikelola administrator/integrasi tidak boleh terhapus saat re-seed.
                $role->givePermissionTo($rolePermissionMap[$roleName]);
            }
        }

        // Apply the monitoring-only revocation after the baseline grant as
        // well; otherwise the broad role template would add it back.
        foreach ($monitoringOnlyRoles as $roleName) {
            Role::query()->where('name', $roleName)->first()?->revokePermissionTo($monitoringOnlyRevocations);
        }

        // Super Admin wajib memiliki seluruh permission tanpa terkecuali
        foreach (['Super Admin', 'super_admin'] as $superName) {
            $superAdminRole = Role::query()->firstOrCreate([
                'name' => $superName,
                'guard_name' => 'web',
            ]);
            $superAdminRole->syncPermissions(
                Permission::where('guard_name', 'web')->get()
            );
        }
    }
}
