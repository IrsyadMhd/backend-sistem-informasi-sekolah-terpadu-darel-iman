<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Student;
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

        // 2. Seed Presensi Pegawai/Guru
        if ($employees->isNotEmpty()) {
            foreach ($employees as $employee) {
                Attendance::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $today,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'tipe_presensi' => 'Pegawai',
                        'employee_id' => $employee->id,
                        'academic_year_id' => $academicYearId,
                        'semester_id' => $semesterId,
                        'attendance_date' => $today,
                        'check_in_time' => now()->setHour(6)->setMinute(45),
                        'check_out_time' => now()->setHour(16)->setMinute(00),
                        'status' => 'HADIR',
                        'attendance_method' => 'GEOLOCATION',
                        'location' => 'Ruang Guru / Unit SDIT',
                        'latitude' => -6.200000,
                        'longitude' => 106.816666,
                        'keterangan' => 'Presensi Mengajar Guru',
                    ]
                );
            }
        }
    }
}
