<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AttendancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Gate attendance access is separate from student self-portal.
            'gate_attendance.view',
            'gate_attendance.scan',
            'gate_attendance.config',

            // Daily Gate Attendance
            'student_attendance.daily.view',
            'student_attendance.daily.scan',
            'student_attendance.daily.create',
            'student_attendance.daily.update',
            'student_attendance.daily.verify',
            'student_attendance.daily.export',

            // Lesson Attendance
            'student_attendance.lesson.view',
            'student_attendance.lesson.create',
            'student_attendance.lesson.finalize',
            'student_attendance.lesson.correct.request',
            'student_attendance.lesson.correct.approve',
            'student_attendance.lesson.export',

            // Worship Attendance (Santri)
            'worship_attendance.view',
            'worship_attendance.template.manage',
            'worship_attendance.session.manage',
            'worship_attendance.scan',
            'worship_attendance.verify',
            'worship_attendance.private_status.view',
            'worship_attendance.export',

            // Legacy Permissions (Compatibility)
            'attendance.teacher.dashboard',
            'attendance.homeroom.dashboard',
            'attendance.student.view_own',
            'lesson_attendance.view',
            'lesson_attendance.view_own',
            'lesson_attendance.create',
            'lesson_attendance.update',
            'lesson_attendance.finalize',
            'lesson_attendance.unlock',
            'lesson_attendance.cancel',
            'lesson_attendance.correct',
            'lesson_attendance.export',
            'attendance_permission.view',
            'attendance_permission.view_own',
            'attendance_permission.create',
            'attendance_permission.update',
            'attendance_permission.submit',
            'attendance_permission.review',
            'attendance_permission.cancel',
            'attendance_correction.view',
            'attendance_correction.create',
            'attendance_correction.review',
            'attendance_correction.cancel',
            'attendance_follow_up.view',
            'attendance_follow_up.create',
            'attendance_follow_up.update',
            'attendance_follow_up.complete',
            'attendance_follow_up.close',
            'attendance_report.view',
            'attendance_report.export',

            // Step 04: teacher card, teaching attendance, session, presence.
            'teaching_attendance.scan',
            'teaching_attendance.view_own',
            'teaching_session.start',
            'teaching_session.close',
            'teacher_presence.heartbeat',
            'teacher_monitoring.view',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $map = [
            'Guru' => [
                'student_attendance.lesson.view',
                'student_attendance.lesson.create',
                'student_attendance.lesson.finalize',
                'student_attendance.lesson.correct.request',
                'attendance.teacher.dashboard',
                'lesson_attendance.view_own',
                'lesson_attendance.create',
                'lesson_attendance.update',
                'lesson_attendance.finalize',
                'lesson_attendance.cancel',
                'lesson_attendance.correct',
                'lesson_attendance.export',
                'teaching_attendance.scan',
                'teaching_attendance.view_own',
                'teaching_session.start',
                'teaching_session.close',
                'teacher_presence.heartbeat',
            ],
            'Wali Kelas' => [
                'student_attendance.daily.view',
                'student_attendance.lesson.view',
                'attendance.homeroom.dashboard',
                'lesson_attendance.view',
                'attendance_permission.view',
                'attendance_permission.review',
                'attendance_correction.view',
                'attendance_correction.review',
                'attendance_follow_up.view',
                'attendance_follow_up.create',
            ],
            'Musyrif / Musyrifah' => [
                'worship_attendance.view',
                'worship_attendance.scan',
                'worship_attendance.verify',
                'worship_attendance.private_status.view',
            ],
            'Tata Usaha' => [
                'gate_attendance.view',
                'gate_attendance.scan',
                'gate_attendance.config',
                'student_attendance.daily.view',
                'student_attendance.daily.scan',
                'student_attendance.daily.create',
                'student_attendance.daily.update',
                'student_attendance.daily.verify',
                'student_attendance.daily.export',
            ],
            'Siswa' => ['attendance.student.view_own', 'attendance_permission.view_own'],
        ];

        foreach ($map as $roleName => $items) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo($items);
            }
        }

        foreach (['Super Admin', 'Kepala Sekolah', 'Admin'] as $roleName) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo($permissions);
            }
        }

        foreach (['Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Divisi Pendidikan'] as $roleName) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo(['gate_attendance.view']);
            }
        }

        foreach (['Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Divisi Pendidikan', 'Kepala Bidang Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa', 'Divisi Program Khusus', 'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Waka Kurikulum', 'Wakil Kurikulum', 'Waka Kesiswaan', 'Wakil Kesiswaan'] as $roleName) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) {
                $role->givePermissionTo('teacher_monitoring.view');
            }
        }

        // Izin/sakit adalah transaksi parent-controlled. Revoke legacy write
        // permissions agar rerunning the seeder also repairs existing DB state.
        if ($studentRole = Role::query()->where(['name' => 'Siswa', 'guard_name' => 'web'])->first()) {
            $studentRole->revokePermissionTo([
                'kehadiran.siswa.izin_sakit',
                'student_attendance.permission.create',
                'student_attendance.permission.update',
                'student_attendance.permission.cancel',
                'attendance_permission.create',
                'attendance_permission.submit',
            ]);
        }
    }
}
