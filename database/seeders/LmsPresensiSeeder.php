<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\HomeroomAttendanceFollowUp;
use App\Models\Kelas;
use App\Models\LessonAttendanceCorrection;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsPresensiSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::query()->with(['kelas', 'educationUnit'])->get();
        if ($students->isEmpty()) {
            $this->command->warn('Skipping LmsPresensiSeeder: No students found in database.');

            return;
        }

        $academicYear = AcademicYear::query()->where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::query()->where('is_active', true)->first() ?? Semester::first();
        $subjects = Subject::query()->get();
        $employee = Employee::query()->first();
        $employees = Employee::query()->get();

        if ($subjects->isEmpty() || ! $academicYear || ! $semester) {
            $this->command->warn('Skipping LmsPresensiSeeder: Missing dependencies (Subjects/AcademicYear/Semester).');

            return;
        }

        $employeeId = $employee ? $employee->id : null;
        $employeeUserId = $employee ? ($employee->user_id ?? $employee->id) : null;

        $classes = Kelas::query()->where('status', 'Aktif')->get();
        if ($classes->isEmpty()) {
            $classes = Kelas::query()->get();
        }

        $firstClass = $classes->first();
        $firstClassId = $firstClass ? $firstClass->id : null;

        $schoolClass = SchoolClass::query()->first();
        $validClassId = $schoolClass ? $schoolClass->id : null;

        // Fetch existing schedules first
        $schedules = ClassSchedule::query()->with(['kelas', 'subject', 'employee'])->get();

        // If no schedules exist, create new schedules safely without violating legacy class_id FK
        if ($schedules->isEmpty() && $classes->isNotEmpty()) {
            foreach ($classes as $cIdx => $kelasItem) {
                $sub = $subjects[$cIdx % $subjects->count()];
                $emp = $employees->count() > 0 ? $employees[$cIdx % $employees->count()] : $employee;
                $empId = $emp ? $emp->id : $employeeId;

                $newSched = new ClassSchedule();
                $newSched->id = (string) Str::uuid();
                $newSched->kelas_id = $kelasItem->id;
                $newSched->class_id = $validClassId; // null or valid UUID from classes table
                $newSched->subject_id = $sub->id;
                $newSched->employee_id = $empId;
                $newSched->academic_year_id = $academicYear->id;
                $newSched->semester_id = $semester->id;
                $newSched->day_of_week = (now()->dayOfWeekIso % 7) ?: 1;
                $newSched->time_start = '07:30:00';
                $newSched->time_end = '09:00:00';
                $newSched->week_type = 'all';
                $newSched->is_active = true;
                $newSched->save();
            }

            $schedules = ClassSchedule::query()->with(['kelas', 'subject', 'employee'])->get();
        }

        if ($schedules->isEmpty()) {
            $this->command->warn('Skipping LmsPresensiSeeder: No schedules available.');

            return;
        }

        $statusDistribution = [
            'hadir', 'hadir', 'terlambat', 'hadir', 'izin',
            'hadir', 'sakit', 'hadir', 'alpa', 'hadir',
            'terlambat', 'hadir', 'izin', 'hadir', 'sakit',
            'hadir', 'alpa', 'hadir', 'hadir', 'terlambat',
        ];

        $dates = [
            now()->format('Y-m-d'),
            now()->subDays(1)->format('Y-m-d'),
            now()->subDays(2)->format('Y-m-d'),
            now()->subDays(3)->format('Y-m-d'),
            now()->subDays(4)->format('Y-m-d'),
            now()->subDays(5)->format('Y-m-d'),
            now()->subDays(6)->format('Y-m-d'),
        ];

        $countSeeded = 0;
        $createdPresensi = [];

        // 1. Seed Sesi & Presensi Harian / Per Matpel
        foreach ($dates as $dIdx => $dateStr) {
            $carbonDate = Carbon::parse($dateStr);
            foreach ($students as $sIdx => $student) {
                $schedule = $schedules->firstWhere('kelas_id', $student->kelas_id)
                    ?? $schedules->firstWhere('kelas_id', $student->class_id)
                    ?? $schedules[$sIdx % $schedules->count()];

                $subjectName = ($schedule->subject && !empty($schedule->subject->name))
                    ? $schedule->subject->name
                    : 'Pembelajaran';

                $session = LessonAttendanceSession::firstOrCreate([
                    'schedule_id' => $schedule->id,
                    'attendance_date' => $dateStr,
                ], [
                    'id' => (string) Str::uuid(),
                    'meeting_number' => $dIdx + 1,
                    'learning_material' => 'Materi ' . $subjectName,
                    'learning_activity' => 'Diskusi & Pemahaman Konsep',
                    'meeting_notes' => 'Sesi berjalan lancar dan interaktif',
                    'status' => 'final',
                    'created_by' => $employeeUserId,
                    'updated_by' => $employeeUserId,
                ]);

                $status = $statusDistribution[($sIdx + $dIdx) % count($statusDistribution)];
                $notes = match ($status) {
                    'hadir' => 'Hadir tepat waktu dan mengikuti sesi pembelajaran',
                    'terlambat' => 'Terlambat 15 menit karena kendala transportasi',
                    'izin' => 'Izin menghadiri acara keluarga (surat terlampir)',
                    'sakit' => 'Sakit demam dan berobat ke klinik (surat dokter terlampir)',
                    'alpa' => 'Tanpa keterangan resmi',
                    default => 'Hadir di kelas',
                };
                $arrivalTime = match ($status) {
                    'hadir' => '07:25:00',
                    'terlambat' => '07:45:00',
                    default => null,
                };

                $presensi = LmsPresensi::updateOrCreate([
                    'jadwal_pelajaran_id' => $schedule->id,
                    'siswa_id' => $student->id,
                    'tanggal' => $dateStr,
                ], [
                    'session_id' => $session->id,
                    'status_hadir' => $status,
                    'keterangan' => $notes,
                    'pertemuan_ke' => $dIdx + 1,
                    'waktu_presensi' => $carbonDate->copy()->setTime(7, 30),
                    'arrival_time' => $arrivalTime,
                    'verification_status' => in_array($status, ['izin', 'sakit']) ? 'pending' : 'verified',
                    'created_by' => $employeeUserId,
                    'updated_by' => $employeeUserId,
                ]);

                $createdPresensi[] = $presensi;
                $countSeeded++;
            }
        }

        // 2. Seed Verifikasi Izin / Sakit (StudentAttendancePermission)
        $permCount = 0;
        $permissionTypes = ['izin', 'sakit'];
        $permissionStatuses = ['submitted', 'approved', 'rejected'];

        foreach ($students->take(15) as $idx => $student) {
            $pType = $permissionTypes[$idx % 2];
            $pStatus = $permissionStatuses[$idx % 3];
            $startDate = now()->subDays($idx + 1)->format('Y-m-d');
            $endDate = now()->subDays($idx)->format('Y-m-d');

            $studentUserId = isset($student->user_id) ? $student->user_id : $employeeId;

            StudentAttendancePermission::updateOrCreate([
                'student_id' => $student->id,
                'start_date' => $startDate,
            ], [
                'id' => (string) Str::uuid(),
                'end_date' => $endDate,
                'type' => $pType,
                'reason' => $pType === 'sakit' ? 'Sakit demam & flu butuh istirahat' : 'Izin keperluan keluarga mendesak',
                'notes' => 'Surat permohonan resmi terlampir dari orang tua',
                'status' => $pStatus,
                'review_notes' => $pStatus === 'approved' ? 'Disetujui oleh wali kelas' : ($pStatus === 'rejected' ? 'Ditolak: lampiran tidak valid' : null),
                'reviewed_by' => $pStatus !== 'submitted' ? $employeeId : null,
                'reviewed_at' => $pStatus !== 'submitted' ? now() : null,
                'created_by' => $studentUserId,
            ]);
            $permCount++;
        }

        // 3. Seed Koreksi Presensi (LessonAttendanceCorrection)
        $corrCount = 0;
        foreach (array_slice($createdPresensi, 0, 15) as $idx => $presensiItem) {
            $cStatus = $permissionStatuses[$idx % 3];

            LessonAttendanceCorrection::updateOrCreate([
                'attendance_id' => $presensiItem->id,
            ], [
                'id' => (string) Str::uuid(),
                'previous_status' => 'alpa',
                'proposed_status' => 'hadir',
                'reason' => 'Siswa hadir tetapi lupa di-scan oleh guru piket',
                'status' => $cStatus,
                'before_data' => json_encode(['status_hadir' => 'alpa']),
                'after_data' => json_encode(['status_hadir' => 'hadir']),
                'requested_by' => $employeeId ? $employeeId : (string) Str::uuid(),
                'approved_by' => $cStatus === 'approved' ? $employeeId : null,
                'approved_at' => $cStatus === 'approved' ? now() : null,
            ]);
            $corrCount++;
        }

        // 4. Seed Tindak Lanjut Siswa (HomeroomAttendanceFollowUp)
        $followUpCount = 0;
        $actions = ['panggilan_orang_tua', 'konseling_bk', 'surat_peringatan', 'home_visit'];
        $priorities = ['low', 'medium', 'high'];
        $fuStatuses = ['new', 'in_progress', 'completed'];

        foreach ($students->take(12) as $idx => $student) {
            $act = $actions[$idx % count($actions)];
            $prio = $priorities[$idx % count($priorities)];
            $st = $fuStatuses[$idx % count($fuStatuses)];
            $classId = $student->kelas_id ?? ($student->class_id ?? $firstClassId);

            if ($classId) {
                HomeroomAttendanceFollowUp::updateOrCreate([
                    'student_id' => $student->id,
                    'case_date' => now()->subDays($idx + 2)->format('Y-m-d'),
                ], [
                    'id' => (string) Str::uuid(),
                    'class_id' => $classId,
                    'case_type' => 'Presensi & Disiplin',
                    'occurrence_count' => ($idx % 3) + 1,
                    'priority' => $prio,
                    'action' => match ($act) {
                        'panggilan_orang_tua' => 'Panggilan Orang Tua / Wali ke Sekolah',
                        'konseling_bk' => 'Sesi Konseling Khusus dengan Guru BK',
                        'surat_peringatan' => 'Penerbitan Surat Peringatan Pertama (SP-1)',
                        'home_visit' => 'Kunjungan Rumah (Home Visit) oleh Wali Kelas',
                        default => 'Pembinaan Khusus Rombel',
                    },
                    'parent_communication' => 'Orang tua telah dihubungi via WA/Telepon',
                    'follow_up_date' => now()->subDays($idx)->format('Y-m-d'),
                    'due_date' => now()->addDays(3)->format('Y-m-d'),
                    'status' => $st,
                    'notes' => 'Catatan penanganan berjalan sesuai dengan SOP kedisiplinan sekolah',
                    'created_by' => $employeeId ? $employeeId : (string) Str::uuid(),
                ]);
                $followUpCount++;
            }
        }

        $this->command->info("LmsPresensiSeeder executed successfully:");
        $this->command->info("- Presensi Records: {$countSeeded}");
        $this->command->info("- Verifikasi Izin: {$permCount}");
        $this->command->info("- Koreksi Presensi: {$corrCount}");
        $this->command->info("- Tindak Lanjut Siswa: {$followUpCount}");
    }
}
