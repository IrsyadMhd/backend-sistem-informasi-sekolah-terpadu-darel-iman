<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorshipAttendanceService
{
    /**
     * Generate daily sessions for all active worship templates on target date.
     */
    public function generateDailySessions(?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date) : today();
        $dateStr = $targetDate->toDateString();

        $templates = WorshipAttendanceTemplate::query()
            ->where('is_active', true)
            ->get();

        $generated = 0;

        foreach ($templates as $template) {
            // Check active days filter if configured
            if (! empty($template->active_days) && is_array($template->active_days)) {
                $dayName = strtolower($targetDate->format('l'));
                if (! in_array($dayName, array_map('strtolower', $template->active_days))) {
                    continue;
                }
            }

            $times = $this->calculateSessionTimes($template, $targetDate);

            // SQLite stores a cast `date` as midnight (`Y-m-d 00:00:00`). A regular
            // firstOrCreate lookup with `Y-m-d` therefore misses the existing row,
            // then collides with the database unique index. Use whereDate for the
            // lookup and retain the unique index as protection against concurrent calls.
            $session = WorshipAttendanceSession::query()
                ->where('template_id', $template->id)
                ->whereDate('session_date', $dateStr)
                ->first();

            if (! $session) {
                try {
                    WorshipAttendanceSession::create([
                        'id' => (string) Str::uuid(),
                        'template_id' => $template->id,
                        'session_date' => $targetDate->copy()->startOfDay(),
                        'scheduled_start_at' => $times['start'],
                        'scheduled_end_at' => $times['end'],
                        'opened_at' => $times['open'],
                        'location_name' => $template->location_name ?? 'Masjid Utama',
                        'status' => 'opened',
                        'generated_automatically' => true,
                    ]);
                    $generated++;
                } catch (QueryException $exception) {
                    // A simultaneous request may have inserted the same session after
                    // our lookup. Only swallow the expected unique-key race.
                    $existing = WorshipAttendanceSession::query()
                        ->where('template_id', $template->id)
                        ->whereDate('session_date', $dateStr)
                        ->exists();

                    if (! $existing) {
                        throw $exception;
                    }
                }
            }
        }

        return [
            'date' => $dateStr,
            'sessions_generated' => $generated,
        ];
    }

    /**
     * Submit or record scan attendance for a santri worship session.
     */
    public function recordWorshipScan(WorshipAttendanceSession $session, array $data, ?User $actor = null): array
    {
        if ($session->status === 'closed' || $session->status === 'cancelled') {
            return [
                'success' => false,
                'message' => 'Sesi presensi ibadah ini sudah ditutup.',
                'code' => 'SESSION_CLOSED',
            ];
        }

        $student = $this->resolveStudent($data);
        if (! $student) {
            return [
                'success' => false,
                'message' => 'Data santri tidak ditemukan.',
                'code' => 'STUDENT_NOT_FOUND',
            ];
        }

        $template = $session->template;
        if ($template) {
            // Validate Gender Scope
            if ($template->gender_scope === 'male' && strtolower($student->jenis_kelamin ?? $student->gender ?? '') === 'p') {
                return [
                    'success' => false,
                    'message' => 'Sesi ibadah ini khusus untuk santri putra.',
                    'code' => 'GENDER_MISMATCH',
                ];
            }
            if ($template->gender_scope === 'female' && strtolower($student->jenis_kelamin ?? $student->gender ?? '') === 'l') {
                return [
                    'success' => false,
                    'message' => 'Sesi ibadah ini khusus untuk santri putri.',
                    'code' => 'GENDER_MISMATCH',
                ];
            }
        }

        // Determine Status based on scan time vs scheduled start
        $scanTime = now();
        $status = 'hadir_berjamaah';
        if ($session->scheduled_start_at && $scanTime->gt($session->scheduled_start_at->addMinutes($template->late_tolerance_minutes ?? 10))) {
            $status = 'terlambat';
        }

        $detail = WorshipAttendanceDetail::updateOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $student->id,
            ],
            [
                'id' => (string) Str::uuid(),
                'attendance_status' => $status,
                'attended_at' => $scanTime,
                'method' => $data['method'] ?? 'qr',
                'device_identifier' => $data['device_identifier'] ?? null,
                'verified_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
                'is_private' => false,
            ]
        );

        return [
            'success' => true,
            'message' => "Presensi ibadah {$student->nama_lengkap} berhasil dicatat ({$status}).",
            'data' => $detail->load('student'),
        ];
    }

    /**
     * Musyrif / Musyrifah manual verification for santri worship attendance.
     * Supports female privacy protection (haid / uzur_syarii).
     */
    public function verifyStudentWorship(WorshipAttendanceSession $session, string $studentId, array $data, User $actor): array
    {
        $status = strtolower($data['attendance_status'] ?? 'hadir_berjamaah');

        // Check if female private status
        $isPrivate = in_array($status, ['haid', 'uzur_syarii']);

        // Audit privacy status access if applicable
        if ($isPrivate) {
            Log::info("VERIFY_FEMALE_PRIVACY_STATUS: User {$actor->id} set status {$status} for student {$studentId}");
        }

        $detail = WorshipAttendanceDetail::updateOrCreate(
            [
                'session_id' => $session->id,
                'student_id' => $studentId,
            ],
            [
                'id' => (string) Str::uuid(),
                'attendance_status' => $status,
                'attended_at' => $data['attended_at'] ?? now(),
                'method' => 'checklist',
                'verified_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'is_private' => $isPrivate,
            ]
        );

        return [
            'success' => true,
            'message' => 'Status presensi ibadah santri berhasil diverifikasi.',
            'data' => $detail->load('student'),
        ];
    }

    /**
     * Mask private female statuses for users without 'worship_attendance.private_status.view' permission.
     */
    public function formatDetailForUser(WorshipAttendanceDetail $detail, ?User $user): array
    {
        $canViewPrivate = $user && ($user->hasRole('Super Admin') || $user->hasPermissionTo('worship_attendance.private_status.view'));

        $array = $detail->toArray();

        if ($detail->is_private && ! $canViewPrivate) {
            $array['attendance_status'] = 'uzur'; // Masked status for public display
            $array['notes'] = 'Uzur Syar\'i';
        }

        return $array;
    }

    private function calculateSessionTimes(WorshipAttendanceTemplate $template, Carbon $date): array
    {
        if ($template->time_source === 'prayer_schedule' && ! empty($template->prayer_name)) {
            // Retrieve prayer time from DB or calculate offset
            $prayerTime = $this->getPrayerTime($template->prayer_name, $date);
            $start = (clone $date)->setTimeFromTimeString($prayerTime);
            $open = (clone $start)->subMinutes($template->open_offset_minutes ?? 15);
            $end = (clone $start)->addMinutes($template->close_offset_minutes ?? 30);

            return ['start' => $start, 'open' => $open, 'end' => $end];
        }

        // Fixed Time
        $startStr = $template->start_time ?? '04:45';
        $endStr = $template->end_time ?? '05:30';

        $start = (clone $date)->setTimeFromTimeString($startStr);
        $end = (clone $date)->setTimeFromTimeString($endStr);
        $open = (clone $start)->subMinutes($template->open_offset_minutes ?? 15);

        return ['start' => $start, 'open' => $open, 'end' => $end];
    }

    private function getPrayerTime(string $prayerName, Carbon $date): string
    {
        $map = [
            'subuh' => '04:45',
            'zuhur' => '12:15',
            'asar' => '15:30',
            'magrib' => '18:20',
            'isya' => '19:30',
        ];

        return $map[strtolower($prayerName)] ?? '04:45';
    }

    private function resolveStudent(array $data): ?Student
    {
        if (! empty($data['student_id'])) {
            return Student::find($data['student_id']);
        }
        if (! empty($data['card_number'])) {
            return Student::where('metadata->card_number', $data['card_number'])
                ->orWhere('nis', $data['card_number'])
                ->first();
        }

        return null;
    }
}
