<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::orderBy('id')->limit(15)->get();
        $employees = Employee::orderBy('id')->limit(5)->get();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $academicYear = \App\Models\AcademicYear::query()->where('is_active', true)->first()
            ?? \App\Models\AcademicYear::query()->orderBy('id')->first();
        $semester = $academicYear
            ? \App\Models\Semester::query()->where('academic_year_id', $academicYear->id)->orderBy('sequence')->first()
            : null;
        $academicYearId = $academicYear?->id;
        $semesterId = $semester?->id;

        // 1. Seed Presensi Siswa
        if ($students->isNotEmpty()) {
            foreach ($students as $index => $student) {
                $status = match ($index % 5) {
                    0 => 'HADIR',
                    1 => 'HADIR',
                    2 => 'TERLAMBAT',
                    3 => 'IZIN',
                    4 => 'SAKIT',
                    default => 'HADIR',
                };

                Attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $today,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tipe_presensi' => 'Siswa',
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYearId,
                        'semester_id' => $semesterId,
                        'class_id' => $student->class_id ?? null,
                        'unit_pendidikan_id' => $student->unit_pendidikan_id ?? null,
                        'attendance_date' => $today,
                        'check_in_time' => $status === 'HADIR' ? now()->setHour(6)->setMinute(55) : ($status === 'TERLAMBAT' ? now()->setHour(7)->setMinute(15) : null),
                        'check_out_time' => in_array($status, ['HADIR', 'TERLAMBAT']) ? now()->setHour(15)->setMinute(30) : null,
                        'status' => $status,
                        'attendance_method' => $index % 2 === 0 ? 'QRCODE' : 'GEOLOCATION',
                        'location' => 'Gedung Utama SDIT (GPS Terverifikasi)',
                        'latitude' => -6.200000,
                        'longitude' => 106.816666,
                        'keterangan' => $status === 'IZIN' ? 'Acara Keluarga' : ($status === 'SAKIT' ? 'Demam & Flu (Surat Dokter Ada)' : 'Presensi Otomatis Systems'),
                        'metadata' => [
                            'device' => 'Mobile App / Scanner Gate 1',
                            'ip_address' => '192.168.1.100',
                        ],
                    ]
                );
            }
        } else {
            // Dummy Sample Records if DB table empty
            for ($i = 1; $i <= 10; $i++) {
                Attendance::create([
                    'id' => (string) Str::uuid(),
                    'tipe_presensi' => 'Siswa',
                    'attendance_date' => $today,
                    'check_in_time' => now()->setHour(6)->setMinute(50 + $i),
                    'check_out_time' => now()->setHour(15)->setMinute(30),
                    'status' => $i % 4 === 0 ? 'TERLAMBAT' : 'HADIR',
                    'attendance_method' => 'QRCODE',
                    'location' => 'Gerbang Utama SIMS Terpadu',
                    'keterangan' => 'Hadir tepat waktu',
                ]);
            }
        }

        // 2. Seed Presensi Pegawai/Guru untuk hari ini, kemarin, dan pekan berjalan
        $allEmployees = Employee::all();
        if ($allEmployees->isNotEmpty()) {
            $datesToSeed = [
                $today,
                $yesterday,
                now()->subDays(2)->toDateString(),
                now()->subDays(3)->toDateString(),
            ];

            foreach ($datesToSeed as $dayOffset => $dateStr) {
                $targetCarbon = Carbon::parse($dateStr);
                // Lewati akhir pekan (Minggu) jika ingin realistis KBM
                if ($targetCarbon->isSunday()) {
                    continue;
                }

                foreach ($allEmployees as $index => $employee) {
                    $mod = ($index + $dayOffset) % 8;
                    $status = 'HADIR';
                    $checkIn = $targetCarbon->copy()->setHour(6)->setMinute(40 + ($index % 18));
                    $checkOut = $targetCarbon->copy()->setHour(16)->setMinute(0);
                    $location = 'Presensi Gerbang Utama';
                    $keterangan = 'Presensi Masuk Tepat Waktu';

                    if ($mod === 3) {
                        $status = 'DINAS_LUAR';
                        $checkIn = $targetCarbon->copy()->setHour(7)->setMinute(15);
                        $checkOut = $targetCarbon->copy()->setHour(15)->setMinute(30);
                        $location = 'Balai Diklat / Dinas Pendidikan';
                        $keterangan = 'Penugasan Luar / Pelatihan Kurikulum';
                    } elseif ($mod === 5) {
                        $status = 'SAKIT';
                        $checkIn = null;
                        $checkOut = null;
                        $location = 'Kediaman Pegawai';
                        $keterangan = 'Izin Sakit (Surat Dokter Terlampir)';
                    } elseif ($mod === 7) {
                        $status = 'ALPHA';
                        $checkIn = null;
                        $checkOut = null;
                        $location = null;
                        $keterangan = 'Tidak Hadir / Belum Ada Keterangan';
                    }

                    $unitId = $employee->unit_id ?? $employee->unit_pendidikan_id ?? null;

                    Attendance::updateOrCreate(
                        [
                            'employee_id' => $employee->id,
                            'attendance_date' => $dateStr,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'tipe_presensi' => 'Pegawai',
                            'employee_id' => $employee->id,
                            'unit_pendidikan_id' => $unitId,
                            'academic_year_id' => $academicYearId,
                            'semester_id' => $semesterId,
                            'month' => $targetCarbon->month,
                            'attendance_date' => $dateStr,
                            'check_in_time' => $checkIn,
                            'check_out_time' => $checkOut,
                            'status' => $status,
                            'attendance_method' => $status === 'DINAS_LUAR' ? 'MANUAL' : 'GEOLOCATION',
                            'location' => $location,
                            'latitude' => -6.200000,
                            'longitude' => 106.816666,
                            'keterangan' => $keterangan,
                        ]
                    );
                }
            }
        }
    }
}
