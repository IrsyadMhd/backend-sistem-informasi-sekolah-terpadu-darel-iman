<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\LmsPresensi;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealtimeFridayOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $today = '2026-10-16'; // Jumat, 16 Oktober 2026

        $academicYear = AcademicYear::where('is_active', true)->first();
        $semester = Semester::where('is_active', true)->first();
        $secEmp = Employee::where('nama_lengkap', 'like', '%Hendra%')->first() ?? Employee::first();
        $musyrifEmp = Employee::where('nama_lengkap', 'like', '%Marlina%')->first() ?? $secEmp;

        // Users with authentic names for foreign key constraints on worship_attendance_details
        $marlinaUser = User::firstOrCreate(
            ['email' => 'marlina.putri@dareliman.sch.id'],
            ['name' => 'Ustzh. Marlina Putri, S.Pd.', 'password' => bcrypt('password')]
        );
        $hendraUser = User::firstOrCreate(
            ['email' => 'hendra.gunawan@dareliman.sch.id'],
            ['name' => 'Ust. Hendra Gunawan, S.Pd', 'password' => bcrypt('password')]
        );
        $rizkyUser = User::firstOrCreate(
            ['email' => 'rizky.alamsyah@dareliman.sch.id'],
            ['name' => 'Rizky Alamsyah, S.Pd.', 'password' => bcrypt('password')]
        );
        $nurulUser = User::firstOrCreate(
            ['email' => 'nurul.aini@dareliman.sch.id'],
            ['name' => 'Usth. Nurul Aini, S.Pd.', 'password' => bcrypt('password')]
        );

        // 1. Dapatkan 4 anak dari akun "Orang Tua Test"
        $parent = ParentModel::where('email', 'orangtua@dareliman.sch.id')->first();
        if (! $parent) {
            $this->command->error("Parent orangtua@dareliman.sch.id tidak ditemukan.");
            return;
        }

        $students = $parent->students()->with(['kelas', 'educationUnit'])->get();
        if ($students->isEmpty()) {
            $this->command->error("Tidak ada siswa terhubung dengan orangtua@dareliman.sch.id.");
            return;
        }

        $this->command->info("Membuat simulasi data operasional Jumat, 16 Oktober 2026 untuk " . $students->count() . " siswa...");

        // Templates ibadah
        $tplSubuh = WorshipAttendanceTemplate::where('prayer_name', 'subuh')->first();
        $tplDzuhur = WorshipAttendanceTemplate::where('prayer_name', 'dzuhur')->first();
        $tplDhuha = WorshipAttendanceTemplate::where('nama', 'like', '%Dhuha%')->first();

        // Sesi Ibadah Jumat
        $sessionSubuh = null;
        if ($tplSubuh) {
            $sessionSubuh = WorshipAttendanceSession::firstOrCreate([
                'template_id' => $tplSubuh->id,
                'session_date' => $today,
            ], [
                'scheduled_start_at' => $today . ' 04:50:00',
                'scheduled_end_at' => $today . ' 05:40:00',
                'opened_at' => $today . ' 04:45:00',
                'closed_at' => $today . ' 05:35:00',
                'location_name' => 'Masjid Mahad Abu Ja\'far',
                'supervisor_id' => $musyrifEmp?->id,
                'status' => 'completed',
                'generated_automatically' => true,
            ]);
        }

        $sessionDhuha = null;
        if ($tplDhuha) {
            $sessionDhuha = WorshipAttendanceSession::firstOrCreate([
                'template_id' => $tplDhuha->id,
                'session_date' => $today,
            ], [
                'scheduled_start_at' => $today . ' 09:30:00',
                'scheduled_end_at' => $today . ' 10:15:00',
                'opened_at' => $today . ' 09:25:00',
                'closed_at' => $today . ' 10:10:00',
                'location_name' => 'Musholla Kampus Sekolah Terpadu',
                'supervisor_id' => $secEmp?->id,
                'status' => 'completed',
                'generated_automatically' => true,
            ]);
        }

        $sessionJumat = null;
        if ($tplDzuhur) {
            $sessionJumat = WorshipAttendanceSession::firstOrCreate([
                'template_id' => $tplDzuhur->id,
                'session_date' => $today,
            ], [
                'scheduled_start_at' => $today . ' 12:00:00',
                'scheduled_end_at' => $today . ' 13:10:00',
                'opened_at' => $today . ' 11:55:00',
                'closed_at' => $today . ' 13:05:00',
                'location_name' => 'Masjid Jami\' Dar el-Iman',
                'supervisor_id' => $secEmp?->id,
                'status' => 'completed',
                'generated_automatically' => true,
            ]);
        }

        foreach ($students as $student) {
            $unitName = strtolower($student->educationUnit?->nama_unit ?? $student->educationUnit?->name ?? '');
            $isBoarding = str_contains($unitName, 'boarding')
                || str_contains($unitName, 'ponpes')
                || str_contains($unitName, 'mahad');

            // --- A. Presensi Gerbang (Attendance) ---
            $gateInTime = $isBoarding ? '06:30:00' : '07:08:24';
            if ($student->nis === '202601099') { // Alya Zahra
                $gateInTime = '07:08:15';
            } elseif ($student->nis === '202601002' || str_contains($student->full_name, 'Fathan')) {
                $gateInTime = '07:11:42';
            } elseif (str_contains($student->full_name, 'Ahmad Fauzi')) {
                $gateInTime = '06:58:30';
            }

            Attendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'attendance_date' => $today,
                ],
                [
                    'academic_year_id' => $academicYear?->id,
                    'semester_id' => $semester?->id,
                    'month' => 10,
                    'class_id' => $student->class_id ?? $student->kelas_id,
                    'unit_pendidikan_id' => $student->unit_id,
                    'check_in_time' => $today . ' ' . $gateInTime,
                    'check_out_time' => null, // Masih di sekolah pada pukul 14:00
                    'status' => 'hadir',
                    'attendance_method' => 'rfid',
                    'location' => $isBoarding ? 'Posko Utama Asrama Santri' : 'Gerbang Utama Sekolah',
                    'tipe_presensi' => 'gerbang',
                    'verified_by' => $isBoarding ? $musyrifEmp?->id : $secEmp?->id,
                    'keterangan' => 'Hadir tepat waktu sebelum bel masuk',
                    'metadata' => [
                        'device' => 'Gate Scanner Barrier RFID-01',
                        'scan_type' => 'card_rfid',
                    ],
                ]
            );

            // --- B. KBM Pelajaran (LmsPresensi) ---
            $schedules = ClassSchedule::with(['subject', 'teacher'])
                ->where('class_id', $student->class_id ?? $student->kelas_id)
                ->where('day_of_week', 5)
                ->orderBy('id', 'asc')
                ->take(2)
                ->get();

            $lessonTimes = [
                ['start' => '07:35:00', 'end' => '08:55:00'],
                ['start' => '09:20:00', 'end' => '10:40:00'],
            ];

            $idx = 0;
            foreach ($schedules as $sched) {
                $t = $lessonTimes[$idx] ?? ['start' => '10:00:00', 'end' => '11:20:00'];
                LmsPresensi::updateOrCreate(
                    [
                        'jadwal_pelajaran_id' => $sched->id,
                        'siswa_id' => $student->id,
                        'tanggal' => $today,
                    ],
                    [
                        'status_hadir' => 'hadir',
                        'pertemuan_ke' => 12,
                        'waktu_presensi' => $today . ' ' . $t['start'],
                        'arrival_time' => $t['start'],
                        'verification_status' => 'verified',
                        'keterangan' => 'Hadir tepat waktu, mengikuti materi dengan aktif.',
                        'recorded_method' => 'manual_teacher',
                        'recorded_at' => $today . ' ' . $t['start'],
                        'recorded_by' => $sched->teacher_id,
                    ]
                );
                $idx++;
            }

            // --- C. Presensi Ibadah / Sholat (WorshipAttendanceDetail) ---
            // Tentukan verifikator ibadah autentik per jenjang anak
            $worshipVerifierUser = match (true) {
                $isBoarding => $marlinaUser,
                $unitName === 'smait' || str_contains($unitName, 'sma') => $rizkyUser,
                default => $nurulUser,
            };

            // 1. Sholat Subuh (Hanya Boarding Santri)
            if ($isBoarding && $sessionSubuh) {
                WorshipAttendanceDetail::updateOrCreate(
                    [
                        'session_id' => $sessionSubuh->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'attendance_status' => 'hadir_berjamaah',
                        'attended_at' => $today . ' 05:04:12',
                        'method' => 'rfid_asrama',
                        'verified_by' => $marlinaUser->id,
                        'notes' => 'Hadir shaf pertama Masjid Mahad Abu Ja\'far',
                    ]
                );
            }

            // 2. Sholat Dhuha (Semua Siswa)
            if ($sessionDhuha) {
                WorshipAttendanceDetail::updateOrCreate(
                    [
                        'session_id' => $sessionDhuha->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'attendance_status' => 'hadir_berjamaah',
                        'attended_at' => $today . ' 09:48:30',
                        'method' => 'manual_teacher',
                        'verified_by' => $worshipVerifierUser->id,
                        'notes' => 'Melaksanakan sholat dhuha 4 rakaat bersama pembimbing',
                    ]
                );
            }

            // 3. Sholat Jumat Berjamaah (Semua Siswa)
            if ($sessionJumat) {
                WorshipAttendanceDetail::updateOrCreate(
                    [
                        'session_id' => $sessionJumat->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'attendance_status' => 'hadir_berjamaah',
                        'attended_at' => $today . ' 12:18:45',
                        'method' => 'manual_teacher',
                        'verified_by' => ($isBoarding ? $marlinaUser->id : $hendraUser->id),
                        'notes' => 'Sholat Jumat berjamaah dan menyimak khutbah Jumat',
                    ]
                );
            }

            // --- D. Setoran Tahfizh Harian (TahfizhDailyLog) ---
            $tahfizhData = [
                'surah' => "An-Nazi'at",
                'surah_no' => 79,
                'start' => 16,
                'end' => 30,
                'baris' => 10,
                'tilawah' => 'QS. Al-Kahfi: 1 - 30 (Sunnah Jumat)',
                'murajaah' => 'Juz 30 (Surah Al-Infitar - Al-Mutaffifin)',
                'teacher' => 'Usth. Nurul Aini, S.Pd.',
                'time' => '09:30:00',
            ];

            if (str_contains($student->full_name, 'Nabil')) {
                // Santri Pesantren Mahad Abu Ja'far (Juz 29 - Al-Mulk)
                $tahfizhData = [
                    'surah' => 'Al-Mulk',
                    'surah_no' => 67,
                    'start' => 1,
                    'end' => 15,
                    'baris' => 12,
                    'tilawah' => 'QS. Al-Kahfi: 1 - 50 (Sunnah Jumat Pesantren)',
                    'murajaah' => 'Juz 29 (Surah Al-Mulk - Al-Qalam)',
                    'teacher' => 'Ustzh. Marlina Putri, S.Pd.',
                    'time' => '08:30:00',
                ];
            } elseif (str_contains($student->full_name, 'Fathan')) {
                // Siswa Kelas 1-A SDIT 1 (An-Naba')
                $tahfizhData = [
                    'surah' => "An-Naba'",
                    'surah_no' => 78,
                    'start' => 1,
                    'end' => 20,
                    'baris' => 12,
                    'tilawah' => 'QS. Al-Kahfi: 1 - 20 (Sunnah Jumat Ceria)',
                    'murajaah' => 'Juz 30 (Surah An-Nas - Al-Ikhlas)',
                    'teacher' => 'Usth. Nurul Aini, S.Pd.',
                    'time' => '09:35:00',
                ];
            } elseif (str_contains($student->full_name, 'Ahmad Fauzi')) {
                // Siswa SMAIT Kelas X-MIPA (Al-Baqarah)
                $tahfizhData = [
                    'surah' => 'Al-Baqarah',
                    'surah_no' => 2,
                    'start' => 142,
                    'end' => 152,
                    'baris' => 15,
                    'tilawah' => 'QS. Al-Kahfi: 1 - 110 (Sunnah Jumat SMAIT)',
                    'murajaah' => 'Juz 2 (Pekan ke-12)',
                    'teacher' => 'Rizky Alamsyah',
                    'time' => '10:15:00',
                ];
            }

            \App\Models\TahfizhDailyLog::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'record_date' => $today,
                ],
                [
                    'academic_year_id' => $academicYear?->id,
                    'semester_id' => $semester?->id,
                    'class_id' => $student->class_id ?? $student->kelas_id,
                    'day_name' => 'Jumat',
                    'tilawah_text' => $tahfizhData['tilawah'],
                    'tilawah_baris' => 25,
                    'hafalan_surah_number' => $tahfizhData['surah_no'],
                    'hafalan_surah_name' => $tahfizhData['surah'],
                    'hafalan_ayah_start' => $tahfizhData['start'],
                    'hafalan_ayah_end' => $tahfizhData['end'],
                    'hafalan_baris' => $tahfizhData['baris'],
                    'murajaah_text' => $tahfizhData['murajaah'],
                    'murajaah_lembar' => 2,
                    'notes_teacher' => 'Barakallah fiik, bacaan makhraj dan kelancaran sangat baik.',
                    'notes_parent' => 'Mendampingi ananda tilawah bersama.',
                    'signature_teacher' => $tahfizhData['teacher'],
                    'signature_parent' => 'Ortu Verified',
                    'status' => 'submitted',
                    'metadata' => [
                        'teacher_name' => $tahfizhData['teacher'],
                        'setoran_type' => 'Ziyadah',
                        'calculated_juz' => $tahfizhData['surah_no'] >= 78 ? 30 : ($tahfizhData['surah_no'] === 67 ? 29 : 2),
                        'kelancaran_label' => 'Sangat Lancar (Mumtaz)',
                        'tajwid_label' => 'Sangat Baik (A)',
                    ],
                    'created_at' => $today . ' ' . $tahfizhData['time'],
                    'updated_at' => $today . ' ' . $tahfizhData['time'],
                ]
            );
        }

        $this->command->info("Simulasi data operasional Jumat, 16 Oktober 2026 berhasil disemai 100%!");
    }
}
