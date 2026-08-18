<?php

namespace App\Services;

use App\Jobs\SendParentAttendanceNotification;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\EducationUnit;
use App\Models\Semester;
use App\Models\SiteSetting;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GateAttendanceService
{
    public function __construct(private StudentQrCredentialService $studentQr)
    {
    }

    /**
     * Perform gate check-in scan validation & recording.
     */
    public function recordCheckIn(array $data, ?string $userId = null): array
    {
        $student = $this->resolveStudent($data);
        if (! $student) {
            return [
                'success' => false,
                'message' => 'Kartu/Identitas siswa tidak ditemukan atau tidak aktif.',
                'code' => 'STUDENT_NOT_FOUND',
            ];
        }

        // Validate active student & active card status
        if (isset($student->is_active) && ! $student->is_active) {
            return [
                'success' => false,
                'message' => 'Siswa dinyatakan tidak aktif dalam sistem.',
                'code' => 'STUDENT_INACTIVE',
            ];
        }

        if ($unitError = $this->studentUnitError($student, $data['unit_id'] ?? null)) {
            return $unitError;
        }

        $date = $data['attendance_date'] ?? today()->toDateString();
        $scanAt = $this->scanAt($date, $data['check_in_time'] ?? null);
        $scanTimeFormatted = $scanAt->format('H:i');

        // Determine schedule & late status
        $entryConfig = $this->getEntryConfig($this->studentUnitId($student));
        $status = $this->evaluateCheckInStatus($scanTimeFormatted, $entryConfig);

        try {
            $result = DB::transaction(function () use ($data, $date, $scanAt, $scanTimeFormatted, $status, $student, $userId): array {
                $this->lockAttendanceKey($student->id, $date);
                $existing = Attendance::where('student_id', $student->id)
                    ->whereDate('attendance_date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($existing && $existing->check_in_time) {
                    return [
                        'success' => false,
                        'message' => "Siswa {$student->nama_lengkap} sudah melakukan absensi masuk hari ini pada pukul ".Carbon::parse($existing->check_in_time)->format('H:i').'.',
                        'code' => 'DUPLICATE_CHECKIN',
                    'data' => $this->attendancePayload($existing),
                    ];
                }

                $attendance = Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $date,
                    ],
                    [
                        'id' => $existing?->id ?? (string) Str::uuid(),
                        'tipe_presensi' => 'Siswa',
                        'student_id' => $student->id,
                        'unit_pendidikan_id' => $this->studentUnitId($student),
                        'class_id' => $student->class_id,
                    'check_in_time' => $scanAt,
                        'status' => strtoupper($status),
                        'attendance_method' => strtoupper($data['attendance_method'] ?? 'QRCODE'),
                        'location' => $data['location'] ?? 'Gerbang Utama',
                        'latitude' => $data['latitude'] ?? null,
                        'longitude' => $data['longitude'] ?? null,
                    'photo_snapshot' => $data['photo_snapshot'] ?? null,
                    'keterangan' => $data['notes'] ?? null,
                    'created_by' => $userId,
                    ...$this->academicContext($student),
                    ]
                );

                return [
                    'success' => true,
                    'message' => "Absensi masuk {$student->nama_lengkap} berhasil dicatat ({$status}).",
                    'data' => $this->attendancePayload($attendance),
                    'notification' => [
                        'student_id' => $student->id,
                        'type' => strtolower($status) === 'terlambat' ? 'late' : 'checkin',
                        'time' => $scanTimeFormatted,
                        'status' => $status,
                    ],
                ];
            });

            if ($result['success'] && isset($result['notification'])) {
                SendParentAttendanceNotification::dispatch(...array_values($result['notification']));
                unset($result['notification']);
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan data absensi gerbang: '.$e->getMessage(),
                'code' => 'STORAGE_ERROR',
            ];
        }
    }

    /**
     * Perform gate check-out scan validation & recording.
     */
    public function recordCheckOut(array $data, ?string $userId = null): array
    {
        $student = $this->resolveStudent($data);
        if (! $student) {
            return [
                'success' => false,
                'message' => 'Kartu/Identitas siswa tidak ditemukan.',
                'code' => 'STUDENT_NOT_FOUND',
            ];
        }

        if (! $student->is_active) {
            return [
                'success' => false,
                'message' => 'Siswa dinyatakan tidak aktif dalam sistem.',
                'code' => 'STUDENT_INACTIVE',
            ];
        }

        if ($unitError = $this->studentUnitError($student, $data['unit_id'] ?? null)) {
            return $unitError;
        }

        $date = $data['attendance_date'] ?? today()->toDateString();
        $scanAt = $this->scanAt($date, $data['check_out_time'] ?? null);
        $scanTimeFormatted = $scanAt->format('H:i');

        $exitConfig = $this->getExitConfig($this->studentUnitId($student));
        $checkOutStatus = $this->evaluateCheckOutStatus($scanTimeFormatted, $exitConfig, $data);

        try {
            $result = DB::transaction(function () use ($data, $date, $scanAt, $scanTimeFormatted, $checkOutStatus, $student, $userId): array {
                $this->lockAttendanceKey($student->id, $date);
                $attendance = Attendance::where('student_id', $student->id)
                    ->whereDate('attendance_date', $date)
                    ->lockForUpdate()
                    ->first();

                if (! $attendance || ! $attendance->check_in_time) {
                    return [
                        'success' => false,
                        'message' => 'Absensi pulang hanya dapat dicatat setelah absensi masuk.',
                        'code' => 'CHECKIN_REQUIRED',
                    ];
                }

                if ($attendance->check_out_time) {
                    return [
                        'success' => false,
                        'message' => "Siswa {$student->nama_lengkap} sudah melakukan absensi pulang hari ini.",
                        'code' => 'DUPLICATE_CHECKOUT',
                        'data' => $this->attendancePayload($attendance),
                    ];
                }

                if ($attendance->check_in_time && $scanAt->lt($attendance->check_in_time)) {
                    return [
                        'success' => false,
                        'message' => 'Waktu absensi pulang tidak boleh lebih awal dari absensi masuk.',
                        'code' => 'CHECKOUT_BEFORE_CHECKIN',
                        'data' => $this->attendancePayload($attendance),
                    ];
                }

                $attendance->update([
                    'check_out_time' => $scanAt,
                    'check_out_status' => $checkOutStatus,
                    'check_out_method' => strtoupper($data['attendance_method'] ?? 'QRCODE'),
                    'pickup_person' => $data['pickup_person'] ?? null,
                    'pickup_relation' => $data['pickup_relation'] ?? null,
                    'pickup_verification' => $data['pickup_verification'] ?? null,
                    'photo_snapshot' => $data['photo_snapshot'] ?? $attendance->photo_snapshot,
                    'approved_by' => $data['approved_by'] ?? null,
                    'updated_by' => $userId,
                ]);

                return [
                    'success' => true,
                    'message' => "Absensi pulang {$student->nama_lengkap} berhasil dicatat.",
                    'data' => $this->attendancePayload($attendance->fresh(['student', 'educationUnit'])),
                    'notification' => [
                        'student_id' => $student->id,
                        'type' => 'checkout',
                        'time' => $scanTimeFormatted,
                        'status' => $checkOutStatus,
                    ],
                ];
            });

            if ($result['success'] && isset($result['notification'])) {
                SendParentAttendanceNotification::dispatch(...array_values($result['notification']));
                unset($result['notification']);
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan data absensi pulang: '.$e->getMessage(),
                'code' => 'STORAGE_ERROR',
            ];
        }
    }

    public function resolveStudent(array $data): ?Student
    {
        if (! empty($data['student_id'])) {
            return Student::active()->find($data['student_id']);
        }
        if (! empty($data['qr_token'])) {
            return $this->studentQr->resolve($data['qr_token']);
        }
        if (! empty($data['nisn'])) {
            return Student::active()->where('nisn', $data['nisn'])->first();
        }
        if (! empty($data['nis'])) {
            return Student::active()->where('nis', $data['nis'])->first();
        }
        if (! empty($data['card_number'])) {
            if ($student = $this->studentQr->resolve($data['card_number'])) {
                return $student;
            }

            return Student::active()->where(function ($query) use ($data) {
                $query->where('metadata->card_number', $data['card_number'])
                    ->orWhere('nis', $data['card_number']);
            })
                ->first();
        }

        return null;
    }

    private function studentUnitId(Student $student): ?string
    {
        return $student->unit_id ?: ($student->education_unit_id ?? $student->kelas?->unit_pendidikan_id);
    }

    private function lockAttendanceKey(string $studentId, string $date): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["gate-attendance:{$studentId}:{$date}"]);
        }
    }

    private function studentUnitError(Student $student, ?string $requestedUnitId): ?array
    {
        if (! $requestedUnitId) {
            return null;
        }

        $studentUnitId = $this->studentUnitId($student);
        if (! $studentUnitId || (string) $studentUnitId === (string) $requestedUnitId) {
            return $studentUnitId ? null : [
                'success' => false,
                'message' => 'Siswa belum memiliki unit pendidikan yang valid.',
                'code' => 'STUDENT_UNIT_MISSING',
            ];
        }

        return [
            'success' => false,
            'message' => 'Siswa berada di luar unit terminal yang dipilih.',
            'code' => 'UNIT_MISMATCH',
        ];
    }

    private function scanAt(string $date, ?string $time): Carbon
    {
        if (! $time) {
            return Carbon::parse($date)->setTimeFromTimeString(now()->format('H:i:s'));
        }

        $parsed = Carbon::parse($time);

        return Carbon::parse($date)->setTimeFromTimeString($parsed->format('H:i:s'));
    }

    private function academicContext(Student $student): array
    {
        $class = $student->kelas ?: $student->schoolClass;
        $academicYearId = $student->academic_year_id ?? $class?->tahun_ajaran_id ?? $class?->academic_year_id;
        $semesterId = $student->semester_id ?? $class?->semester_id;

        return [
            'academic_year_id' => $academicYearId ?: AcademicYear::query()->where('is_active', true)->value('id'),
            'semester_id' => $semesterId ?: Semester::query()->where('is_active', true)->value('id'),
        ];
    }

    private function attendancePayload(Attendance $attendance): array
    {
        $payload = $attendance->toArray();
        $student = $attendance->relationLoaded('student') ? $attendance->student : $attendance->student()->first();

        $payload['student'] = $student ? [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'nama_lengkap' => $student->nama_lengkap,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'class_id' => $student->class_id,
            'kelas_id' => $student->kelas_id,
        ] : null;

        if ($attendance->relationLoaded('educationUnit') && $attendance->educationUnit) {
            $payload['education_unit'] = $attendance->educationUnit->only(['id', 'name', 'code']);
        }

        return $payload;
    }

    public function getScheduleConfig(?string $unitId = null): array
    {
        if ($unitId) {
            $unit = EducationUnit::find($unitId);
            if ($unit && isset($unit->metadata['gate_schedule'])) {
                return array_merge([
                    'jam_masuk' => '07:15',
                    'toleransi_menit' => 10,
                    'jam_pulang' => '14:15',
                    'jam_cutoff_alpha' => '12:00',
                ], $unit->metadata['gate_schedule']);
            }
        }

        // Global fallback setting from SiteSetting or hardcoded default
        $globalSetting = SiteSetting::where('key', 'gate_schedule_default')->first();
        if ($globalSetting && is_array($globalSetting->value)) {
            return array_merge([
                'jam_masuk' => '07:15',
                'toleransi_menit' => 10,
                'jam_pulang' => '14:15',
                'jam_cutoff_alpha' => '12:00',
            ], $globalSetting->value);
        }

        return [
            'jam_masuk' => '07:15',
            'toleransi_menit' => 10,
            'jam_pulang' => '14:15',
            'jam_cutoff_alpha' => '12:00',
        ];
    }

    public function getAllUnitsScheduleConfig(): array
    {
        $units = EducationUnit::all();
        $globalConfig = $this->getScheduleConfig(null);

        $result = [];
        foreach ($units as $unit) {
            $unitConfig = isset($unit->metadata['gate_schedule'])
                ? array_merge($globalConfig, $unit->metadata['gate_schedule'])
                : $globalConfig;

            $result[] = [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name ?? $unit->nama,
                'code' => $unit->code,
                'schedule' => $unitConfig,
                'has_custom_schedule' => isset($unit->metadata['gate_schedule']),
            ];
        }

        return [
            'global' => $globalConfig,
            'units' => $result,
        ];
    }

    public function saveScheduleConfig(?string $unitId, array $data): array
    {
        $config = [
            'jam_masuk' => $data['jam_masuk'] ?? '07:15',
            'toleransi_menit' => (int) ($data['toleransi_menit'] ?? 10),
            'jam_pulang' => $data['jam_pulang'] ?? '14:15',
            'jam_cutoff_alpha' => $data['jam_cutoff_alpha'] ?? '12:00',
        ];

        if ($unitId) {
            $unit = EducationUnit::find($unitId);
            if ($unit) {
                $meta = $unit->metadata ?? [];
                $meta['gate_schedule'] = $config;
                $unit->metadata = $meta;
                $unit->save();
            }
        } else {
            SiteSetting::updateOrCreate(
                ['key' => 'gate_schedule_default'],
                ['value' => $config, 'group' => 'attendance']
            );
        }

        return [
            'success' => true,
            'message' => 'Pengaturan jam masuk dan jam pulang berhasil diperbarui.',
            'data' => $config,
        ];
    }

    private function getEntryConfig(?string $unitId): array
    {
        return $this->getScheduleConfig($unitId);
    }

    private function getExitConfig(?string $unitId): array
    {
        return $this->getScheduleConfig($unitId);
    }

    private function evaluateCheckInStatus(string $scanTime, array $config): string
    {
        $scan = Carbon::parse($scanTime);
        $scheduled = Carbon::parse($config['jam_masuk']);
        $toleranceLimit = (clone $scheduled)->addMinutes((int) ($config['toleransi_menit'] ?? 10));

        if ($scan->lte($scheduled)) {
            return 'hadir';
        }
        if ($scan->lte($toleranceLimit)) {
            return 'hadir_dalam_toleransi';
        }

        return 'terlambat';
    }

    private function evaluateCheckOutStatus(string $scanTime, array $config, array $data): string
    {
        $scan = Carbon::parse($scanTime);
        $scheduled = Carbon::parse($config['jam_pulang']);

        if (! empty($data['is_early']) || $scan->lt($scheduled)) {
            return 'pulang_lebih_awal';
        }

        return 'pulang_normal';
    }
}
