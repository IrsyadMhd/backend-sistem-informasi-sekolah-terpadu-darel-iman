<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessUnscannedAttendanceCutoff extends Command
{
    protected $signature = 'attendance:process-unscanned-cutoff {--unit_id=} {--date=} {--confirm-alpha}';

    protected $description = 'Process students who have not scanned gate attendance by cutoff time, setting initial status to belum_hadir or transitioning to alpha after confirmation cutoff.';

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date'))->toDateString() : today()->toDateString();
        $unitId = $this->option('unit_id');
        $confirmAlpha = $this->option('confirm-alpha');

        $this->info("Processing unscanned attendance cutoff for date {$date}...");

        $query = Student::query()->where('is_active', true);
        if ($unitId) {
            $query->where('education_unit_id', $unitId);
        }

        $activeStudents = $query->get();
        $scannedStudentIds = Attendance::whereDate('attendance_date', $date)
            ->whereNotNull('student_id')
            ->pluck('student_id')
            ->toArray();

        $approvedPermissions = StudentAttendancePermission::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where('status', 'approved')
            ->get()
            ->keyBy('student_id');

        $processedCount = 0;

        foreach ($activeStudents as $student) {
            if (in_array($student->id, $scannedStudentIds)) {
                continue;
            }

            // Check if student has approved leave/sick permission
            $permission = $approvedPermissions->get($student->id);
            if ($permission) {
                $status = strtolower($permission->type) === 'sakit' ? 'SAKIT' : 'IZIN';
                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'unit_pendidikan_id' => $student->education_unit_id,
                        'class_id' => $student->class_id,
                        'tipe_presensi' => 'Siswa',
                        'status' => $status,
                        'attendance_method' => 'SYSTEM',
                        'keterangan' => 'Izin/Sakit terverifikasi dari pengajuan: '.$permission->reason,
                    ]
                );
                $processedCount++;

                continue;
            }

            // Unscanned student without permission
            if ($confirmAlpha) {
                // Final confirmation deadline passed -> assign ALPHA
                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'unit_pendidikan_id' => $student->education_unit_id,
                        'class_id' => $student->class_id,
                        'tipe_presensi' => 'Siswa',
                        'status' => 'ALPHA',
                        'attendance_method' => 'SYSTEM',
                        'keterangan' => 'Tidak ada scan dan tidak ada keterangan izin/sakit hingga batas konfirmasi.',
                    ]
                );
            } else {
                // Initial cutoff -> assign BELUM_HADIR
                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'unit_pendidikan_id' => $student->education_unit_id,
                        'class_id' => $student->class_id,
                        'tipe_presensi' => 'Siswa',
                        'status' => 'BELUM_HADIR',
                        'attendance_method' => 'SYSTEM',
                        'keterangan' => 'Belum melakukan absensi scan gerbang.',
                    ]
                );
            }
            $processedCount++;
        }

        $this->info("Successfully processed {$processedCount} unscanned student attendance records.");
        Log::info("Command attendance:process-unscanned-cutoff finished for {$date}. Processed: {$processedCount}");

        return Command::SUCCESS;
    }
}
