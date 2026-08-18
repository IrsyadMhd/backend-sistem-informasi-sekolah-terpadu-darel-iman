<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsPresensi;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PresensiPembelajaranSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = Teacher::query()->with(['user', 'employee'])->whereNotNull('user_id')->orderBy('id')->first();
        $subject = Subject::query()->where(fn ($query) => $query
            ->where('status', true)
            ->orWhereNull('status'))->orderBy('id')->first();
        $schoolClass = SchoolClass::query()
            ->whereIn('id', Student::query()->active()->whereNotNull('class_id')->select('class_id'))
            ->orderBy('id')
            ->first();

        if (! $teacher || ! $teacher->user || ! $subject || ! $schoolClass) {
            $this->command->warn(
                'PresensiPembelajaranSeeder dilewati: akun guru, mata pelajaran, atau kelas yang memiliki siswa belum tersedia.'
            );

            return;
        }

        $students = Student::query()
            ->active()
            ->where('class_id', $schoolClass->id)
            ->orderBy('full_name')
            ->limit(15)
            ->get();

        if ($students->isEmpty()) {
            $this->command->warn('PresensiPembelajaranSeeder dilewati: kelas contoh tidak memiliki siswa aktif.');

            return;
        }

        $kelas = Kelas::query()
            ->where('tahun_ajaran_id', $schoolClass->academic_year_id)
            ->where('semester_id', $schoolClass->semester_id)
            ->where(function ($query) use ($schoolClass) {
                $query->where('nama_kelas', $schoolClass->name)
                    ->orWhere('kode_kelas', $schoolClass->name);
            })
            ->first();

        $semester = $schoolClass->semester_id
            ? Semester::find($schoolClass->semester_id)
            : null;
        $academicYear = $schoolClass->academic_year_id
            ? AcademicYear::find($schoolClass->academic_year_id)
            : null;

        if (! $semester || ! $academicYear) {
            $this->command->warn('PresensiPembelajaranSeeder dilewati: periode kelas belum lengkap.');

            return;
        }

        $periodStart = Carbon::parse($semester->start_date ?? $academicYear->start_date)->startOfDay();
        $periodEnd = Carbon::parse($semester->end_date ?? $academicYear->end_date)->startOfDay();
        $draftDate = now()->startOfDay()->between($periodStart, $periodEnd)
            ? now()->startOfDay()
            : $periodStart->copy()->addDays(14);

        if ($draftDate->gt($periodEnd)) {
            $draftDate = $periodEnd->copy();
        }

        $finalDate = $draftDate->copy()->subWeek();
        if ($finalDate->lt($periodStart)) {
            $finalDate = $draftDate->copy()->addWeek();
        }

        if ($finalDate->gt($periodEnd)) {
            $this->command->warn('PresensiPembelajaranSeeder dilewati: rentang semester kurang dari tujuh hari.');

            return;
        }

        DB::transaction(function () use (
            $teacher,
            $subject,
            $schoolClass,
            $kelas,
            $academicYear,
            $semester,
            $students,
            $draftDate,
            $finalDate
        ) {
            $schedule = ClassSchedule::query()->updateOrCreate([
                'class_id' => $schoolClass->id,
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'day_of_week' => $draftDate->dayOfWeekIso,
                'time_start' => '09:15:00',
            ], [
                'kelas_id' => $kelas?->id,
                'employee_id' => $teacher->employee_id,
                'time_end' => '10:45:00',
                'week_type' => 'all',
                'is_active' => true,
                'metadata' => [
                    'source' => 'PresensiPembelajaranSeeder',
                    'description' => 'Jadwal contoh untuk alur presensi pembelajaran.',
                ],
                'created_by' => $teacher->user_id,
                'updated_by' => $teacher->user_id,
            ]);

            $this->seedSession(
                schedule: $schedule,
                students: $students,
                date: $finalDate,
                meeting: 1,
                status: 'final',
                topic: 'Pengenalan Materi dan Tujuan Pembelajaran',
                notes: 'Pembelajaran berlangsung tertib. Presensi telah diperiksa dan difinalisasi.',
                userId: $teacher->user_id,
            );

            $this->seedSession(
                schedule: $schedule,
                students: $students,
                date: $draftDate,
                meeting: 2,
                status: 'draft',
                topic: 'Pendalaman Materi dan Latihan Terbimbing',
                notes: 'Draft presensi untuk diperiksa kembali sebelum finalisasi.',
                userId: $teacher->user_id,
            );
        });

        $this->command->info(
            "PresensiPembelajaranSeeder berhasil: {$students->count()} siswa, satu sesi draft, dan satu sesi final."
        );
    }

    private function seedSession(
        ClassSchedule $schedule,
        $students,
        Carbon $date,
        int $meeting,
        string $status,
        string $topic,
        string $notes,
        string $userId,
    ): void {
        $isFinal = $status === 'final';
        $session = LessonAttendanceSession::query()->updateOrCreate([
            'schedule_id' => $schedule->id,
            'attendance_date' => $date->toDateString(),
        ], [
            'meeting_number' => $meeting,
            'learning_material' => 'Materi pembelajaran pertemuan ke-'.$meeting,
            'learning_activity' => 'Apersepsi, penyampaian materi, latihan, dan refleksi.',
            'topic' => $topic,
            'meeting_notes' => $notes,
            'status' => $status,
            'attendance_method' => 'manual',
            'finalized_at' => $isFinal ? $date->copy()->setTime(11, 0) : null,
            'finalized_by' => $isFinal ? $userId : null,
            'locked_at' => null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'metadata' => ['source' => 'PresensiPembelajaranSeeder'],
        ]);

        $statuses = ['hadir', 'terlambat', 'izin', 'hadir', 'sakit', 'hadir', 'hadir'];

        foreach ($students as $index => $student) {
            $attendanceStatus = $statuses[($index + $meeting - 1) % count($statuses)];
            $arrivalTime = match ($attendanceStatus) {
                'hadir' => '09:'.str_pad((string) (10 + ($index % 5)), 2, '0', STR_PAD_LEFT),
                'terlambat' => '09:28',
                default => null,
            };

            LmsPresensi::query()->updateOrCreate([
                'jadwal_pelajaran_id' => $schedule->id,
                'siswa_id' => $student->id,
                'tanggal' => $date->toDateString(),
            ], [
                'session_id' => $session->id,
                'status_hadir' => $attendanceStatus,
                'keterangan' => match ($attendanceStatus) {
                    'izin' => 'Izin kegiatan keluarga.',
                    'sakit' => 'Sakit dan telah menyampaikan pemberitahuan.',
                    'terlambat' => 'Terlambat mengikuti pembelajaran.',
                    default => 'Mengikuti pembelajaran.',
                },
                'pertemuan_ke' => $meeting,
                'waktu_presensi' => $date->copy()->setTime(9, 15),
                'arrival_time' => $arrivalTime,
                'verification_status' => in_array($attendanceStatus, ['izin', 'sakit']) ? 'pending' : 'verified',
                'recorded_method' => 'manual',
                'recorded_at' => $date->copy()->setTime(9, 15),
                'recorded_by' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'capture_metadata' => ['source' => 'PresensiPembelajaranSeeder'],
            ]);
        }
    }
}
