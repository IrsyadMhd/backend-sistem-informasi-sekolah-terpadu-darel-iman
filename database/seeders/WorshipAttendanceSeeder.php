<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorshipAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Subuh Berjamaah',
                'code' => 'SUBUH_BERJAMAAH',
                'category' => 'shalat_wajib',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'santri_asrama',
                'time_source' => 'prayer_schedule',
                'prayer_name' => 'subuh',
                'start_time' => '04:30',
                'end_time' => '05:15',
                'open_offset_minutes' => 20,
                'iqamah_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 30,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Masjid Utama Pesantren',
                'attendance_methods' => ['rfid', 'qr', 'checklist'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Dzuhur Berjamaah',
                'code' => 'DZUHUR_BERJAMAAH',
                'category' => 'shalat_wajib',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'all_students',
                'time_source' => 'prayer_schedule',
                'prayer_name' => 'dzuhur',
                'start_time' => '12:00',
                'end_time' => '12:45',
                'open_offset_minutes' => 15,
                'iqamah_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 25,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Masjid Utama Pesantren',
                'attendance_methods' => ['rfid', 'qr', 'checklist'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Ashar Berjamaah',
                'code' => 'ASHAR_BERJAMAAH',
                'category' => 'shalat_wajib',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'all_students',
                'time_source' => 'prayer_schedule',
                'prayer_name' => 'ashar',
                'start_time' => '15:30',
                'end_time' => '16:15',
                'open_offset_minutes' => 15,
                'iqamah_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 25,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Masjid Utama Pesantren',
                'attendance_methods' => ['rfid', 'qr', 'checklist'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Magrib Berjamaah',
                'code' => 'MAGRIB_BERJAMAAH',
                'category' => 'shalat_wajib',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'santri_asrama',
                'time_source' => 'prayer_schedule',
                'prayer_name' => 'magrib',
                'start_time' => '18:00',
                'end_time' => '18:45',
                'open_offset_minutes' => 15,
                'iqamah_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 25,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Masjid Utama Pesantren',
                'attendance_methods' => ['rfid', 'qr', 'checklist'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Isya Berjamaah',
                'code' => 'ISYA_BERJAMAAH',
                'category' => 'shalat_wajib',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'santri_asrama',
                'time_source' => 'prayer_schedule',
                'prayer_name' => 'isya',
                'start_time' => '19:15',
                'end_time' => '20:00',
                'open_offset_minutes' => 15,
                'iqamah_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 25,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Masjid Utama Pesantren',
                'attendance_methods' => ['rfid', 'qr', 'checklist'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Shalat Dhuha Bersama',
                'code' => 'SHALAT_DHUHA',
                'category' => 'shalat_sunnah',
                'obligation_type' => 'sunnah',
                'gender_scope' => 'all',
                'participant_scope' => 'all_students',
                'time_source' => 'fixed',
                'start_time' => '07:30',
                'end_time' => '08:00',
                'open_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 15,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'location_name' => 'Masjid Utama',
                'attendance_methods' => ['checklist', 'rfid'],
                'verification_required' => false,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Qiyamul Lail & Tahajud',
                'code' => 'TAHAJUD_MALAM',
                'category' => 'shalat_sunnah',
                'obligation_type' => 'sunnah',
                'gender_scope' => 'all',
                'participant_scope' => 'tahfizh_only',
                'time_source' => 'fixed',
                'start_time' => '03:30',
                'end_time' => '04:10',
                'open_offset_minutes' => 15,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 15,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Mushalla Asrama Santri',
                'attendance_methods' => ['checklist', 'qr'],
                'verification_required' => true,
                'is_active' => true,
            ],
            [
                'id' => (string) Str::uuid(),
                'nama' => 'Halaqah Subuh & Mutabaah Kitab',
                'code' => 'HALAQAH_SUBUH',
                'category' => 'kegiatan_keagamaan',
                'obligation_type' => 'wajib',
                'gender_scope' => 'all',
                'participant_scope' => 'santri_asrama',
                'time_source' => 'fixed',
                'start_time' => '05:30',
                'end_time' => '06:30',
                'open_offset_minutes' => 10,
                'late_tolerance_minutes' => 10,
                'close_offset_minutes' => 15,
                'active_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'location_name' => 'Aula & Hall Pesantren',
                'attendance_methods' => ['checklist', 'qr'],
                'verification_required' => true,
                'is_active' => true,
            ],
        ];

        $todayStr = today()->toDateString();
        $students = Student::active()->limit(15)->get();

        foreach ($templates as $tData) {
            $template = WorshipAttendanceTemplate::where('code', $tData['code'])->first();
            if (! $template) {
                $template = WorshipAttendanceTemplate::create($tData);
            }

            // Generate today's session for each template
            $session = WorshipAttendanceSession::where('template_id', $template->id)
                ->whereDate('session_date', $todayStr)
                ->first();

            if (! $session) {
                $session = WorshipAttendanceSession::create([
                    'id' => (string) Str::uuid(),
                    'template_id' => $template->id,
                    'session_date' => $todayStr,
                    'scheduled_start_at' => Carbon::parse("{$todayStr} {$template->start_time}"),
                    'scheduled_end_at' => Carbon::parse("{$todayStr} {$template->end_time}"),
                    'opened_at' => Carbon::parse("{$todayStr} {$template->start_time}")->subMinutes(15),
                    'location_name' => $template->location_name,
                    'status' => 'opened',
                    'generated_automatically' => true,
                ]);
            }

            // Seed details for active students
            if ($session->wasRecentlyCreated && $students->isNotEmpty()) {
                $statusList = ['hadir_berjamaah', 'hadir_berjamaah', 'hadir_sendiri', 'terlambat', 'izin', 'sakit'];
                foreach ($students as $index => $st) {
                    $stStatus = $statusList[$index % count($statusList)];
                    WorshipAttendanceDetail::firstOrCreate(
                        [
                            'session_id' => $session->id,
                            'student_id' => $st->id,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'attendance_status' => $stStatus,
                            'method' => 'checklist',
                            'attended_at' => now(),
                            'is_private' => false,
                        ]
                    );
                }
            }
        }
    }
}
