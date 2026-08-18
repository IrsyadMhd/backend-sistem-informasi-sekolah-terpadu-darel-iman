<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsPresensiSeeder extends Seeder
{
    public function run(): void
    {
        $students = Student::query()->with(['kelas', 'educationUnit'])->limit(30)->get();
        if ($students->isEmpty()) {
            $this->command->warn('Skipping LmsPresensiSeeder: No students found.');

            return;
        }

        $academicYear = AcademicYear::query()->where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::query()->where('is_active', true)->first() ?? Semester::first();
        $subjects = Subject::query()->limit(5)->get();
        $employee = Employee::query()->first();

        if ($subjects->isEmpty() || ! $academicYear || ! $semester) {
            $this->command->warn('Skipping LmsPresensiSeeder: Missing dependencies.');

            return;
        }

        // Generate schedules for classes if not present
        $classes = Kelas::query()->where('status', 'Aktif')->get();
        if ($classes->isEmpty()) {
            $classes = Kelas::query()->get();
        }

        foreach ($classes as $cIdx => $kelasItem) {
            $sub = $subjects[$cIdx % $subjects->count()];
            ClassSchedule::firstOrCreate([
                'kelas_id' => $kelasItem->id,
                'subject_id' => $sub->id,
            ], [
                'id' => Str::uuid(),
                'class_id' => $kelasItem->id,
                'employee_id' => $employee?->id,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'day_of_week' => (now()->dayOfWeekIso % 7) ?: 1,
                'time_start' => '07:30:00',
                'time_end' => '09:00:00',
                'week_type' => 'all',
                'is_active' => true,
            ]);
        }

        $schedules = ClassSchedule::query()->with(['kelas', 'subject'])->get();
        if ($schedules->isEmpty()) {
            $this->command->warn('Skipping LmsPresensiSeeder: No schedules created.');

            return;
        }

        // Status pattern to ensure balanced distribution for all 5 statuses
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
        ];

        $countSeeded = 0;

        foreach ($dates as $dIdx => $dateStr) {
            $carbonDate = Carbon::parse($dateStr);
            foreach ($students as $sIdx => $student) {
                // Find matching schedule for student's class
                $schedule = $schedules->firstWhere('kelas_id', $student->kelas_id)
                    ?? $schedules->firstWhere('class_id', $student->class_id)
                    ?? $schedules[$sIdx % $schedules->count()];

                // Create attendance session if needed
                $session = LessonAttendanceSession::firstOrCreate([
                    'schedule_id' => $schedule->id,
                    'attendance_date' => $dateStr,
                ], [
                    'meeting_number' => $dIdx + 1,
                    'learning_material' => 'Pembelajaran '.($schedule->subject?.name ?? 'Umum'),
                    'learning_activity' => 'Diskusi & Latihan',
                    'topic' => 'Topik '.($schedule->subject?.name ?? 'Akademik').' Pertemuan '.($dIdx + 1),
                    'status' => 'final',
                    'attendance_method' => 'manual',
                    'created_by' => $employee?->user_id,
                    'updated_by' => $employee?->user_id,
                ]);

                $status = $statusDistribution[($sIdx + $dIdx) % count($statusDistribution)];
                $notes = match ($status) {
                    'hadir' => 'Mengikuti pembelajaran dengan tertib',
                    'terlambat' => 'Terlambat 15 menit karena kendala lalu lintas',
                    'izin' => 'Izin keperluan keluarga (surat terlampir)',
                    'sakit' => 'Sakit demam/flu (surat dokter terlampir)',
                    'alpa' => 'Tanpa keterangan resmi',
                    default => 'Mengikuti presensi kelas',
                };
                $arrivalTime = match ($status) {
                    'hadir' => '07:25',
                    'terlambat' => '07:45',
                    default => null,
                };

                LmsPresensi::updateOrCreate([
                    'jadwal_pelajaran_id' => $schedule->id,
                    'siswa_id' => $student->id,
                    'tanggal' => $dateStr,
                ], [
                    'session_id' => $session->id,
                    'status_hadir' => $status,
                    'catatan' => $notes,
                    'keterangan' => $notes,
                    'pertemuan_ke' => $dIdx + 1,
                    'waktu_presensi' => $carbonDate->copy()->setTime(7, 30),
                    'arrival_time' => $arrivalTime,
                    'verification_status' => in_array($status, ['izin', 'sakit']) ? 'pending' : 'verified',
                    'recorded_method' => 'manual',
                    'recorded_at' => $carbonDate->copy()->setTime(7, 30),
                    'created_by' => $employee?->user_id,
                    'updated_by' => $employee?->user_id,
                ]);
                $countSeeded++;
            }
        }

        $this->command->info("LmsPresensiSeeder executed successfully with {$countSeeded} presensi records across 5 statuses.");
    }
}

