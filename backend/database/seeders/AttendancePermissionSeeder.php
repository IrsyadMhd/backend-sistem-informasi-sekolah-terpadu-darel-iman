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
            'attendance.teacher.dashboard','attendance.homeroom.dashboard','attendance.student.view_own',
            'lesson_attendance.view','lesson_attendance.view_own','lesson_attendance.create','lesson_attendance.update',
            'lesson_attendance.finalize','lesson_attendance.unlock','lesson_attendance.cancel','lesson_attendance.correct','lesson_attendance.export',
            'attendance_permission.view','attendance_permission.view_own','attendance_permission.create','attendance_permission.update',
            'attendance_permission.submit','attendance_permission.review','attendance_permission.cancel',
            'attendance_correction.view','attendance_correction.create','attendance_correction.review','attendance_correction.cancel',
            'attendance_follow_up.view','attendance_follow_up.create','attendance_follow_up.update','attendance_follow_up.complete','attendance_follow_up.close',
            'attendance_report.view','attendance_report.export',
        ];
        foreach ($permissions as $name) Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);

        $map = [
            'Guru' => ['attendance.teacher.dashboard','lesson_attendance.view_own','lesson_attendance.create','lesson_attendance.update','lesson_attendance.finalize','lesson_attendance.cancel','lesson_attendance.correct','lesson_attendance.export','attendance_correction.view','attendance_correction.create','attendance_correction.cancel','attendance_report.view','attendance_report.export'],
            'Wali Kelas' => ['attendance.homeroom.dashboard','lesson_attendance.view','attendance_permission.view','attendance_permission.review','attendance_correction.view','attendance_correction.review','attendance_follow_up.view','attendance_follow_up.create','attendance_follow_up.update','attendance_follow_up.complete','attendance_follow_up.close','attendance_report.view','attendance_report.export'],
            'Siswa' => ['attendance.student.view_own','attendance_permission.view_own','attendance_permission.create','attendance_permission.update','attendance_permission.submit','attendance_permission.cancel'],
        ];
        foreach ($map as $roleName => $items) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) $role->givePermissionTo($items);
        }
        foreach (['Super Admin','Kepala Sekolah'] as $roleName) {
            if ($role = Role::query()->where(['name' => $roleName, 'guard_name' => 'web'])->first()) $role->givePermissionTo($permissions);
        }
    }
}
