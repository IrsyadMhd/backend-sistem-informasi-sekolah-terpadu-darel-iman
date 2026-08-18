<?php

namespace App\Services\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;

class EmployeeAttendanceService
{
    /**
     * Evaluate and process auto-attendance for employee upon login.
     * Guaranteed single check-in per day (unique constraint + check).
     */
    public function processEmployeeLoginAttendance(User $user, string $source = 'login_password', ?string $ipAddress = null, ?string $deviceId = null): array
    {
        $employee = Employee::query()->where('user_id', $user->id)->first();
        if (! $employee) {
            $teacher = Teacher::query()->where('user_id', $user->id)->first();
            if ($teacher && $teacher->employee_id) {
                $employee = Employee::query()->find($teacher->employee_id);
            }
        }

        if (! $employee || $employee->status !== 'AKTIF') {
            return [
                'attendance_recorded' => false,
                'reason' => 'Pegawai tidak ditemukan atau berstatus tidak aktif.',
            ];
        }

        $now = Carbon::now();
        $today = $now->toDateString();

        // 1. Check if attendance already exists today for this employee
        $existingAttendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        if ($existingAttendance) {
            $lessonInfo = $this->evaluateTeacherLessonAttendance($user, $employee, $now);

            return [
                'attendance_recorded' => false,
                'already_checked_in' => true,
                'attendance' => $existingAttendance,
                'lesson_info' => $lessonInfo,
                'message' => 'Kehadiran pegawai hari ini sudah dicatat sebelumnya pada ' . ($existingAttendance->check_in_time ? Carbon::parse($existingAttendance->check_in_time)->format('H:i') : '-'),
            ];
        }

        // 2. Determine work attendance status based on work schedule / server time
        // Default standard work start: 07:30
        $workStartTime = Carbon::parse($today . ' 07:30:00');
        $status = $now->greaterThan($workStartTime->copy()->addMinutes(15)) ? 'TERLAMBAT' : 'HADIR';

        // 3. Resolve active academic context. Tabel `attendances` di PostgreSQL
        // bersifat partitioned: academic_year_id / semester_id / month adalah
        // bagian dari primary key, sehingga wajib terisi. Bila belum ada
        // konteks akademik aktif, kolom dibiarkan null (kompatibel SQLite).
        $activeAy = AcademicYear::query()->where('is_active', true)->first();
        $activeSem = $activeAy
            ? Semester::query()
                ->where('academic_year_id', $activeAy->id)
                ->where('is_active', true)
                ->orderBy('sequence', 'desc')
                ->first()
            : null;

        // 4. Create single daily attendance record
        $attendance = Attendance::create([
            'tipe_presensi' => 'Pegawai',
            'employee_id' => $employee->id,
            'unit_pendidikan_id' => $employee->unit_id,
            'academic_year_id' => $activeAy?->id,
            'semester_id' => $activeSem?->id,
            'month' => $now->month,
            'attendance_date' => $today,
            'check_in_time' => $now->toDateTimeString(),
            'status' => $status,
            'attendance_method' => strtoupper($source),
            'keterangan' => 'Absensi otomatis saat login portal pegawai/guru',
            'created_by' => (string) $user->id,
        ]);

        // 5. Evaluate teacher lesson attendance if applicable
        $lessonInfo = $this->evaluateTeacherLessonAttendance($user, $employee, $now);

        return [
            'attendance_recorded' => true,
            'already_checked_in' => false,
            'attendance' => $attendance,
            'lesson_info' => $lessonInfo,
            'message' => $status === 'TERLAMBAT'
                ? "Kehadiran pegawai tercatat (Terlambat) pada pukul {$now->format('H:i')}"
                : "Kehadiran Anda berhasil dicatat pada pukul {$now->format('H:i')}",
        ];
    }

    /**
     * Evaluate lesson attendance for subject teachers if login happens during schedule window.
     */
    private function evaluateTeacherLessonAttendance(User $user, Employee $employee, Carbon $now): array
    {
        $teacher = Teacher::query()->where('user_id', $user->id)->orWhere('employee_id', $employee->id)->first();
        if (! $teacher) {
            return [
                'is_teacher' => false,
                'lesson_recorded' => false,
            ];
        }

        $dayOfWeekMap = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
        $todayName = $dayOfWeekMap[$now->dayOfWeekIso] ?? 'Senin';
        $currentTimeStr = $now->format('H:i:s');

        // Configurable tolerance window (default 30 mins before, 30 mins after)
        $earlyToleranceMinutes = 30;
        $afterToleranceMinutes = 30;

        // Find active schedule matching current time window
        $schedules = ClassSchedule::query()
            ->where('employee_id', $employee->id)
            ->where('hari', $todayName)
            ->where('is_active', true)
            ->get();

        $matchedSchedule = null;
        foreach ($schedules as $sched) {
            $startTime = Carbon::parse($now->toDateString() . ' ' . $sched->jam_mulai)->subMinutes($earlyToleranceMinutes);
            $endTime = Carbon::parse($now->toDateString() . ' ' . $sched->jam_selesai)->addMinutes($afterToleranceMinutes);

            if ($now->between($startTime, $endTime)) {
                $matchedSchedule = $sched;
                break;
            }
        }

        if (! $matchedSchedule) {
            return [
                'is_teacher' => true,
                'lesson_recorded' => false,
                'message' => 'Login berhasil. Tidak ada jadwal mengajar aktif saat ini.',
            ];
        }

        // Check or create lesson attendance session
        $todayDate = $now->toDateString();
        $session = LessonAttendanceSession::firstOrCreate(
            [
                'schedule_id' => $matchedSchedule->id,
                'attendance_date' => $todayDate,
            ],
            [
                'meeting_number' => 1,
                'status' => 'active',
                'created_by' => $user->id,
            ]
        );

        return [
            'is_teacher' => true,
            'lesson_recorded' => true,
            'schedule_id' => $matchedSchedule->id,
            'subject_name' => $matchedSchedule->subject?->name ?? 'Mata Pelajaran',
            'class_name' => $matchedSchedule->kelas?->nama_kelas ?? 'Kelas',
            'session_id' => $session->id,
            'message' => "Sesi Mengajar Aktif: Presensi pembelajaran untuk {$matchedSchedule->subject?->name} ({$matchedSchedule->kelas?->nama_kelas}) tercatat.",
        ];
    }
}
