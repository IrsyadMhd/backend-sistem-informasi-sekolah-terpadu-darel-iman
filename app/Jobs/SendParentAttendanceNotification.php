<?php

namespace App\Jobs;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendParentAttendanceNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $studentId,
        public string $type, // 'checkin' | 'checkout' | 'late'
        public string $scanTime,
        public ?string $status = null,
    ) {}

    public function handle(): void
    {
        $student = Student::find($this->studentId);
        if (! $student) {
            return;
        }

        $studentName = $student->nama_lengkap ?? $student->full_name ?? 'Siswa';

        $title = match ($this->type) {
            'checkin' => 'Siswa Telah Tiba di Sekolah',
            'checkout' => 'Siswa Telah Keluar dari Sekolah',
            'late' => 'Siswa Terlambat Datang',
            default => 'Notifikasi Presensi Siswa',
        };

        $message = match ($this->type) {
            'checkin' => "{$studentName} telah melakukan absensi masuk pada pukul {$this->scanTime} dengan status ".strtoupper($this->status ?? 'HADIR').'.',
            'checkout' => "{$studentName} tercatat keluar dari sekolah pada pukul {$this->scanTime}.",
            'late' => "{$studentName} tercatat tiba di sekolah pada pukul {$this->scanTime}, melewati batas waktu masuk sekolah.",
            default => "Informasi absensi {$studentName} pada pukul {$this->scanTime}.",
        };

        Log::info("Parent Notification Dispatched: [{$title}] {$message} for Student ID: {$this->studentId}");

        // Optionally dispatch to Notification model if exists
        try {
            if (class_exists(\App\Models\Notification::class)) {
                // Jika parents exist, notify parents
                $parents = $student->parents ?? [];
                foreach ($parents as $parent) {
                    if (isset($parent->user_id)) {
                        \App\Models\Notification::deliver(
                            userId: $parent->user_id,
                            title: $title,
                            body: $message,
                            channel: 'attendance',
                            metadata: ['student_id' => $this->studentId],
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed saving parent notification record: '.$e->getMessage());
        }
    }
}
