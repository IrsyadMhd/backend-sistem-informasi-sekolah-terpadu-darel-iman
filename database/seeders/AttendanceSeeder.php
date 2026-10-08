<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Student;
use App\Models\StudentAttendancePermission;
use App\Models\User;
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
            ? \App\Models\Semester::query()->where('academic_year_id', $academicYear->id)->where('is_active', true)->first()
                ?? \App\Models\Semester::query()->where('academic_year_id', $academicYear->id)->orderBy('sequence')->first()
            : null;
        $academicYearId = $academicYear?->id;
        $semesterId = $semester?->id;

        $datesToSeed = [
            $today,
            $yesterday,
            now()->subDays(2)->toDateString(),
            now()->subDays(3)->toDateString(),
        ];

        // 1. Seed Presensi Siswa (Sinkron multi-hari dengan presensi pegawai)
        if ($students->isNotEmpty()) {
            foreach ($datesToSeed as $dayOffset => $dateStr) {
                $targetCarbon = Carbon::parse($dateStr);
                if ($targetCarbon->isSunday()) {
                    continue;
                }

                foreach ($students as $index => $student) {
                    $mod = ($index + $dayOffset) % 5;
                    $status = match ($mod) {
                        0 => 'HADIR',
                        1 => 'HADIR',
                        2 => 'TERLAMBAT',
                        3 => 'IZIN',
                        4 => 'SAKIT',
                        default => 'HADIR',
                    };

                    $checkIn = $status === 'HADIR'
                        ? $targetCarbon->copy()->setHour(6)->setMinute(45 + ($index % 15))
                        : ($status === 'TERLAMBAT' ? $targetCarbon->copy()->setHour(7)->setMinute(15) : null);
                    $checkOut = in_array($status, ['HADIR', 'TERLAMBAT'])
                        ? $targetCarbon->copy()->setHour(15)->setMinute(30)
                        : null;

                    Attendance::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'attendance_date' => $dateStr,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'tipe_presensi' => 'Siswa',
                            'student_id' => $student->id,
                            'academic_year_id' => $academicYearId,
                            'semester_id' => $semesterId,
                            'month' => $targetCarbon->month,
                            'class_id' => $student->class_id ?? null,
                            'unit_pendidikan_id' => $student->unit_pendidikan_id ?? null,
                            'attendance_date' => $dateStr,
                            'check_in_time' => $checkIn,
                            'check_out_time' => $checkOut,
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
            }
        } else {
            // Dummy Sample Records if DB table empty
            foreach ($datesToSeed as $dateStr) {
                for ($i = 1; $i <= 5; $i++) {
                    Attendance::updateOrCreate(
                        [
                            'attendance_date' => $dateStr,
                            'location' => "Gerbang Utama SIMS Terpadu - Pintu $i",
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'tipe_presensi' => 'Siswa',
                            'academic_year_id' => $academicYearId,
                            'semester_id' => $semesterId,
                            'attendance_date' => $dateStr,
                            'check_in_time' => Carbon::parse($dateStr)->setHour(6)->setMinute(50 + $i),
                            'check_out_time' => Carbon::parse($dateStr)->setHour(15)->setMinute(30),
                            'status' => $i % 4 === 0 ? 'TERLAMBAT' : 'HADIR',
                            'attendance_method' => 'QRCODE',
                            'location' => "Gerbang Utama SIMS Terpadu - Pintu $i",
                            'keterangan' => 'Hadir tepat waktu',
                        ]
                    );
                }
            }
        }

        // 2. Seed Presensi Pegawai/Guru untuk hari ini, kemarin, dan pekan berjalan
        $allEmployees = Employee::query()->orderBy('id')->get();
        if ($allEmployees->isNotEmpty()) {
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

        // 3. Seed Perizinan Santri / Siswa (StudentAttendancePermission)
        if ($students->isNotEmpty()) {
            $reviewerUser = User::role(['Kepala Sekolah', 'Guru', 'Super Admin'])->first() ?? User::first();
            $permissionSamples = [
                [
                    'type' => 'Sakit',
                    'reason' => 'Demam tinggi dan radang tenggorokan, istirahat dokter selama 2 hari',
                    'status' => 'submitted',
                    'start_offset' => 0,
                    'duration' => 2,
                    'attachment' => 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?w=800&auto=format&fit=crop&q=80',
                    'notes' => 'Surat keterangan dokter dari RS Islam Padang terlampir.',
                ],
                [
                    'type' => 'Izin',
                    'reason' => 'Menghadiri walimatul ursy dan kepulangan keluarga inti dari luar kota',
                    'status' => 'submitted',
                    'start_offset' => 1,
                    'duration' => 1,
                    'attachment' => null,
                    'notes' => 'Permohonan izin diajukan oleh wali santri via aplikasi mobile.',
                ],
                [
                    'type' => 'Sakit',
                    'reason' => 'Gejala tipes, disarankan istirahat tirah baring oleh dokter spesialis anak',
                    'status' => 'approved',
                    'start_offset' => -2,
                    'duration' => 3,
                    'attachment' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800&auto=format&fit=crop&q=80',
                    'notes' => 'Surat diagnosis dan resep obat terlampir.',
                    'review_notes' => 'Izin sakit disetujui, semoga lekas sembuh dan bisa kembali belajar dengan sehat.',
                ],
                [
                    'type' => 'Izin',
                    'reason' => 'Keperluan keluarga mendadak ada musibah di kampung halaman',
                    'status' => 'approved',
                    'start_offset' => -4,
                    'duration' => 2,
                    'attachment' => null,
                    'notes' => 'Orang tua sudah konfirmasi lewat telepon ke musyrif/wali kelas.',
                    'review_notes' => 'Disetujui. Harap santri tetap mengejar materi pelajaran yang tertinggal.',
                ],
                [
                    'type' => 'Lainnya',
                    'reason' => 'Mengikuti olimpiade sains tingkat provinsi yang diadakan kemenag',
                    'status' => 'rejected',
                    'start_offset' => -1,
                    'duration' => 1,
                    'attachment' => null,
                    'notes' => 'Surat tugas sekolah belum disertakan.',
                    'review_notes' => 'Mohon lampirkan surat dispensasi resmi dari bagian kesiswaan madrasah.',
                ],
            ];

            foreach ($permissionSamples as $idx => $sample) {
                $student = $students[$idx % $students->count()];
                $startDate = now()->addDays($sample['start_offset'])->toDateString();
                $endDate = now()->addDays($sample['start_offset'] + $sample['duration'] - 1)->toDateString();

                StudentAttendancePermission::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    [
                        'academic_year_id' => $academicYearId,
                        'semester_id' => $semesterId,
                        'class_id' => $student->class_id ?? null,
                        'type' => $sample['type'],
                        'reason' => $sample['reason'],
                        'attachment_path' => $sample['attachment'],
                        'notes' => $sample['notes'],
                        'status' => $sample['status'],
                        'submitted_at' => now()->subHours(4 + $idx * 3),
                        'reviewed_by' => in_array($sample['status'], ['approved', 'rejected']) ? $reviewerUser?->id : null,
                        'reviewed_at' => in_array($sample['status'], ['approved', 'rejected']) ? now()->subHours(2 + $idx) : null,
                        'review_notes' => $sample['review_notes'] ?? null,
                    ]
                );
            }
        }
    }
}
