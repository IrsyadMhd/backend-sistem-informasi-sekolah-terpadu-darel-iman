<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LessonAttendanceSession;
use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Models\LmsPenugasan;
use App\Models\LmsPresensi;
use App\Models\MasterKurikulum;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\TujuanPembelajaran;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\TahfizhRecord;
use App\Models\Teacher;
use App\Models\TeachingAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GuruTestWorkspaceSeeder
 *
 * Seed data simulasi lengkap untuk akun Guru Test (guru@dareliman.sch.id)
 * agar semua tab di http://localhost:5173/portal-guru/workspace terisi data:
 *
 *  1. Jadwal Pelajaran    → class_schedules       (Senin–Jumat, full 5 slot/hari, dari 1 Agustus 2026)
 *  2. Presensi Siswa      → lms_presensi          (per sesi pertemuan)
 *  3. Materi Belajar      → lms_materi            (20 materi, 2 per minggu sejak Agustus)
 *  4. Penugasan           → lms_penugasan         (12 tugas, deadline bertahap)
 *  5. Penilaian Siswa     → student_grades        (nilai lengkap per siswa per mapel)
 *  6. Tahfizh Al-Quran    → tahfizh_records       (setoran Ziyadah & Murojaah)
 *  7. Rekap Tahfizh       → (dari tahfizh_records, di-aggregate by front-end)
 *  8. Mutabaah Yaumiyyah  → mutabaah_daily_header + mutabaah_daily_details
 *  9. Catatan Siswa       → student_notes         (catatan akademik, perilaku, prestasi)
 * 10. Log Absensi Guru    → teaching_attendances  (log hadir guru per sesi)
 */
class GuruTestWorkspaceSeeder extends Seeder
{
    private const GURU_EMAIL = 'guru@dareliman.sch.id';

    // Slot jam pelajaran Senin–Jumat
    private const JAM_BELAJAR = [
        ['mulai' => '07:30', 'selesai' => '08:50'],
        ['mulai' => '09:00', 'selesai' => '10:20'],
        ['mulai' => '10:40', 'selesai' => '12:00'],
        ['mulai' => '13:00', 'selesai' => '14:20'],
        ['mulai' => '14:30', 'selesai' => '15:50'],
    ];

    // Hari Senin–Jumat (ISO: 1=Senin ... 5=Jumat)
    private const HARI_AKTIF = [1, 2, 3, 4, 5];

    private const MAPEL_PRIORITAS = [
        'Matematika', 'IPA', 'Bahasa Indonesia', 'Bahasa Inggris',
        'PAI', 'Tahfizh', 'IPS', 'Bahasa Arab',
    ];

    private const SURAH_LIST = [
        ['nomor' => 1,   'nama' => 'Al-Fatihah',   'ayat' => 7,  'juz' => 1],
        ['nomor' => 2,   'nama' => 'Al-Baqarah',   'ayat' => 286,'juz' => 1],
        ['nomor' => 36,  'nama' => 'Yasin',         'ayat' => 83, 'juz' => 23],
        ['nomor' => 67,  'nama' => 'Al-Mulk',       'ayat' => 30, 'juz' => 29],
        ['nomor' => 78,  'nama' => "An-Naba'",      'ayat' => 40, 'juz' => 30],
        ['nomor' => 87,  'nama' => "Al-A'la",       'ayat' => 19, 'juz' => 30],
        ['nomor' => 93,  'nama' => 'Ad-Duha',       'ayat' => 11, 'juz' => 30],
        ['nomor' => 94,  'nama' => 'Al-Insyirah',   'ayat' => 8,  'juz' => 30],
        ['nomor' => 103, 'nama' => 'Al-Asr',        'ayat' => 3,  'juz' => 30],
        ['nomor' => 108, 'nama' => 'Al-Kautsar',    'ayat' => 3,  'juz' => 30],
        ['nomor' => 110, 'nama' => 'An-Nasr',       'ayat' => 3,  'juz' => 30],
        ['nomor' => 112, 'nama' => 'Al-Ikhlas',     'ayat' => 4,  'juz' => 30],
        ['nomor' => 113, 'nama' => 'Al-Falaq',      'ayat' => 5,  'juz' => 30],
        ['nomor' => 114, 'nama' => 'An-Nas',        'ayat' => 6,  'juz' => 30],
    ];

    // ───────────────────────────────────────────────────────────────────────
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn('GuruTestWorkspaceSeeder hanya untuk lokal/development.');
            return;
        }

        $this->command?->info('═══════════════════════════════════════════════════');
        $this->command?->info('  GuruTestWorkspaceSeeder — Guru Test Workspace');
        $this->command?->info('═══════════════════════════════════════════════════');

        // ── Resolve akun Guru Test ──
        $user = User::query()->where('email', self::GURU_EMAIL)->first();
        if (! $user) {
            $this->command?->error('User guru@dareliman.sch.id tidak ditemukan.');
            $this->command?->warn('Jalankan DefaultRoleUserSeeder terlebih dahulu.');
            return;
        }

        $teacher = Teacher::query()->where('user_id', $user->id)->first();
        $employee = Employee::query()->where('user_id', $user->id)->first();

        if (! $teacher || ! $employee) {
            $this->command?->error('Teacher/Employee untuk guru@dareliman.sch.id tidak ditemukan.');
            $this->command?->warn('Jalankan DefaultRoleUserSeeder terlebih dahulu.');
            return;
        }

        $this->command?->info("  ✓ User ditemukan: {$user->name} ({$user->email})");
        $this->command?->info("  ✓ Employee ID: {$employee->id}");
        $this->command?->info("  ✓ Teacher ID : {$teacher->id}");

        // ── Resolve Tahun Ajaran & Semester ──
        $academicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();

        if (! $academicYear) {
            $this->command?->error('Tidak ada AcademicYear. Jalankan seeder tahun ajaran dulu.');
            return;
        }

        $semester = Semester::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('is_active', true)
            ->first()
            ?? Semester::query()
                ->where('academic_year_id', $academicYear->id)
                ->orderBy('sequence')
                ->first();

        if (! $semester) {
            $this->command?->error('Tidak ada Semester aktif.');
            return;
        }

        $this->command?->info("  ✓ Tahun Ajaran: {$academicYear->name}");
        $this->command?->info("  ✓ Semester    : {$semester->name}");

        // ── Resolve Unit Pendidikan ──
        $unitId = $employee->unit_id;
        if (! $unitId) {
            $this->command?->warn('  ⚠ employee.unit_id kosong, coba ambil dari metadata user.');
            $unitId = data_get($user->metadata, 'unit_id')
                ?? data_get($user->metadata, 'education_unit_id');
        }

        // ── Resolve Mapel (prioritaskan yang ada di unit) ──
        $subjects = Subject::query()
            ->when($unitId, fn ($q) => $q->where(
                fn ($q2) => $q2->where('unit_pendidikan_id', $unitId)->orWhereNull('unit_pendidikan_id')
            ))
            ->where(fn ($q) => $q->where('status', true)->orWhereNull('status'))
            ->orderByRaw("CASE " . implode(' ', array_map(
                fn ($n, $i) => "WHEN (nama_mapel ILIKE '%{$n}%' OR name ILIKE '%{$n}%') THEN {$i}",
                self::MAPEL_PRIORITAS,
                range(0, count(self::MAPEL_PRIORITAS) - 1)
            )) . " ELSE 99 END")
            ->get();

        if ($subjects->isEmpty()) {
            $subjects = Subject::query()->orderBy('id')->get();
        }

        if ($subjects->isEmpty()) {
            $this->command?->error('Tidak ada Subject/Mapel di database.');
            return;
        }

        // ── Resolve Kelas ──
        $kelasList = Kelas::query()
            ->where('tahun_ajaran_id', $academicYear->id)
            ->when($unitId, fn ($q) => $q->where('unit_pendidikan_id', $unitId))
            ->where('status', 'Aktif')
            ->orderBy('nama_kelas')
            ->get();

        if ($kelasList->isEmpty()) {
            $kelasList = Kelas::query()
                ->where('tahun_ajaran_id', $academicYear->id)
                ->where('status', 'Aktif')
                ->orderBy('nama_kelas')
                ->get();
        }

        if ($kelasList->isEmpty()) {
            $kelasList = Kelas::query()->orderBy('nama_kelas')->get();
        }

        if ($kelasList->isEmpty()) {
            $this->command?->error('Tidak ada Kelas di database.');
            return;
        }

        $this->command?->info("  ✓ Kelas tersedia: {$kelasList->count()} kelas");
        $this->command?->info("  ✓ Mapel tersedia: {$subjects->count()} mapel");

        // ────────────────────────────────────────────────────────────────
        // MULAI SEEDING DALAM TRANSAKSI
        // ────────────────────────────────────────────────────────────────
        DB::transaction(function () use (
            $user, $teacher, $employee, $academicYear, $semester,
            $unitId, $subjects, $kelasList
        ) {
            // 1. Jadwal Pelajaran (Senin–Jumat, full sejak 1 Agustus 2026)
            $schedules = $this->seedJadwalPelajaran(
                $employee, $academicYear, $semester, $subjects, $kelasList
            );

            if ($schedules->isEmpty()) {
                $this->command?->warn('  ⚠ Jadwal kosong, ambil jadwal yang sudah ada...');
                $schedules = ClassSchedule::query()
                    ->where('employee_id', $employee->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->with(['kelas', 'subject'])
                    ->get();
            }

            if ($schedules->isEmpty()) {
                $this->command?->warn('  ⚠ Tidak ada jadwal ditemukan, lewati sesi selanjutnya.');
                return;
            }

            // 2. Log Absensi Guru (TeachingAttendance) + 3. Presensi Siswa per sesi
            $this->seedTeachingAttendanceAndPresensi(
                $employee, $academicYear, $semester, $unitId, $schedules
            );

            // 3. Modul Ajar (RPP Digital) untuk Guru Test
            $this->seedModulAjar($user, $employee, $academicYear, $semester, $schedules);

            // 4. Materi Belajar
            $this->seedMateriBelajar($teacher, $employee, $academicYear, $semester, $subjects, $kelasList);

            // 5. Penugasan
            $this->seedPenugasan($teacher, $employee, $academicYear, $semester, $subjects, $kelasList);

            // 6. Penilaian Siswa
            $this->seedPenilaianSiswa($employee, $academicYear, $semester, $subjects, $kelasList, $user->id);

            // 7 & 8. Tahfizh Records
            $this->seedTahfizh($teacher, $employee, $academicYear, $semester, $kelasList);

            // 9. Mutabaah Yaumiyyah
            $this->seedMutabaah($employee, $academicYear, $semester, $kelasList);

            // 10. Catatan Siswa
            $this->seedCatatanSiswa($teacher, $academicYear, $semester, $kelasList);
        });

        $this->command?->info('');
        $this->command?->info('  ✅ GuruTestWorkspaceSeeder selesai!');
        $this->command?->info('  📧 Login: guru@dareliman.sch.id / Guru@2026!');
        $this->command?->info('  🌐 URL  : http://localhost:5173/portal-guru/workspace');
        $this->command?->info('═══════════════════════════════════════════════════');
    }

    // ════════════════════════════════════════════════════════════════════════
    // 1. JADWAL PELAJARAN — Senin–Jumat, 5 slot/hari, mulai 1 Agustus 2026
    // ════════════════════════════════════════════════════════════════════════
    private function seedJadwalPelajaran(
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $subjects,
        $kelasList
    ): \Illuminate\Support\Collection {
        $startDate = Carbon::parse('2026-08-01');
        $created = 0;
        $allSchedules = collect();
        $subjectCount = $subjects->count();
        $kelasCount   = $kelasList->count();

        foreach (self::HARI_AKTIF as $hariIdx => $dayOfWeek) {
            foreach (self::JAM_BELAJAR as $slotIdx => $slot) {
                // Rotasi kelas dan mapel agar tidak monoton
                $kelas   = $kelasList->get(($hariIdx * count(self::JAM_BELAJAR) + $slotIdx) % $kelasCount);
                $subject = $subjects->get(($hariIdx + $slotIdx * 2) % $subjectCount);

                $sched = ClassSchedule::query()->updateOrCreate(
                    [
                        'academic_year_id' => $ay->id,
                        'semester_id'      => $semester->id,
                        'kelas_id'         => $kelas->id,
                        'day_of_week'      => $dayOfWeek,
                        'time_start'       => $slot['mulai'] . ':00',
                        'employee_id'      => $employee->id,
                    ],
                    [
                        'subject_id'  => $subject->id,
                        'time_end'    => $slot['selesai'] . ':00',
                        'week_type'   => 'all',
                        'is_active'   => true,
                        'metadata'    => [
                            'source'     => 'GuruTestWorkspaceSeeder',
                            'start_date' => $startDate->toDateString(),
                            'room'       => $kelas->ruangan ?: 'Ruang ' . $kelas->nama_kelas,
                        ],
                    ]
                );

                $allSchedules->push($sched->load(['kelas', 'subject']));
                $created++;
            }
        }

        $this->command?->info("  ✓ [1] Jadwal Pelajaran : {$created} slot (Senin–Jumat × 5 jam)");
        return $allSchedules;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 2 & 10. LOG ABSENSI GURU + PRESENSI SISWA per pertemuan
    //         Dari 1 Agustus 2026 s/d hari ini, setiap hari aktif
    // ════════════════════════════════════════════════════════════════════════
    private function seedTeachingAttendanceAndPresensi(
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        ?string $unitId,
        \Illuminate\Support\Collection $schedules
    ): void {
        $startDate = Carbon::parse('2026-08-01');
        $endDate   = Carbon::now()->min(Carbon::parse('2026-09-28')); // sampai hari ini maks
        $totalTA   = 0;
        $totalPres = 0;
        $meeting   = 1;

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dow = $current->dayOfWeekIso; // 1=Mon … 7=Sun

            if (in_array($dow, self::HARI_AKTIF)) {
                // Ambil jadwal untuk hari ini
                $daySchedules = $schedules->where('day_of_week', $dow)->values();

                foreach ($daySchedules as $sched) {
                    $attendDate = $current->toDateString();
                    $checkIn    = Carbon::parse($attendDate . ' ' . $sched->time_start)
                        ->addMinutes(rand(0, 5));

                    // ── Log Absensi Guru ──
                    $ta = TeachingAttendance::query()->updateOrCreate(
                        [
                            'schedule_id'  => $sched->id,
                            'employee_id'  => $employee->id,
                            'attendance_date' => $attendDate,
                        ],
                        [
                            'education_unit_id' => $unitId ?? $sched->kelas?->unit_pendidikan_id,
                            'academic_year_id'  => $ay->id,
                            'semester_id'       => $semester->id,
                            'check_in_at'       => $checkIn->toDateTimeString(),
                            'status'            => 'hadir',
                            'attendance_method' => ['manual', 'qr', 'face'][rand(0, 2)],
                            'created_by'        => $employee->user_id,
                            'metadata'          => [
                                'source'         => 'GuruTestWorkspaceSeeder',
                                'meeting_number' => $meeting,
                            ],
                        ]
                    );
                    $totalTA++;

                    // ── Sesi Pembelajaran ──
                    $topic = $this->topicFor($sched->subject?->nama_mapel ?? $sched->subject?->name ?? 'Pembelajaran', $meeting);
                    $session = LessonAttendanceSession::query()->updateOrCreate(
                        [
                            'schedule_id'    => $sched->id,
                            'attendance_date' => $attendDate,
                        ],
                        [
                            'teaching_attendance_id' => $ta->id,
                            'meeting_number'         => $meeting,
                            'topic'                  => $topic,
                            'learning_material'      => $topic,
                            'learning_activity'      => 'Ceramah, Diskusi, Latihan Soal',
                            'meeting_notes'          => "Pertemuan ke-{$meeting}. Materi berjalan lancar.",
                            'status'                 => 'final',
                            'finalized_at'           => $checkIn->addHours(2)->toDateTimeString(),
                            'finalized_by'           => $employee->user_id,
                            'created_by'             => $employee->user_id,
                            'metadata'               => ['source' => 'GuruTestWorkspaceSeeder'],
                        ]
                    );

                    // ── Presensi Siswa ──
                    $students = Student::query()
                        ->where('kelas_id', $sched->kelas_id)
                        ->orderBy('full_name')
                        ->get();

                    $statusPool = array_merge(
                        array_fill(0, 85, 'hadir'),
                        array_fill(0, 5, 'terlambat'),
                        array_fill(0, 4, 'sakit'),
                        array_fill(0, 3, 'izin'),
                        array_fill(0, 3, 'alpa')
                    );

                    foreach ($students as $sIdx => $student) {
                        $status = $statusPool[$sIdx % count($statusPool)];
                        $waktu  = $status === 'hadir'
                            ? $checkIn->copy()->addMinutes(rand(0, 10))->toDateTimeString()
                            : null;

                        LmsPresensi::query()->updateOrCreate(
                            [
                                'jadwal_pelajaran_id' => $sched->id,
                                'session_id'          => $session->id,
                                'siswa_id'            => $student->id,
                                'tanggal'             => $attendDate,
                            ],
                            [
                                'status_hadir'        => $status,
                                'pertemuan_ke'        => $meeting,
                                'waktu_presensi'      => $waktu,
                                'arrival_time'        => $waktu,
                                'verification_status' => 'verified',
                                'recorded_method'     => 'manual',
                                'recorded_at'         => now()->toDateTimeString(),
                                'recorded_by'         => $employee->user_id,
                                'created_by'          => $employee->user_id,
                            ]
                        );
                        $totalPres++;
                    }
                }
                $meeting++;
            }
            $current->addDay();
        }

        $this->command?->info("  ✓ [2] Log Absensi Guru : {$totalTA} record (teaching_attendances)");
        $this->command?->info("  ✓ [3] Presensi Siswa   : {$totalPres} record (lms_presensi)");
    }

    private function determineFase(?string $tingkat, ?string $jenjang): string
    {
        $tingkatClean = strtoupper(trim((string) $tingkat));
        $jenjangClean = strtoupper(trim((string) $jenjang));

        if (str_contains($tingkatClean, 'TK') || str_contains($tingkatClean, 'PAUD') || str_contains($jenjangClean, 'TK')) {
            return 'Fase Fondasi';
        }
        if (in_array($tingkatClean, ['1', '2', 'I', 'II']) || str_contains($tingkatClean, 'KELAS 1') || str_contains($tingkatClean, 'KELAS 2')) {
            return 'Fase A';
        }
        if (in_array($tingkatClean, ['3', '4', 'III', 'IV']) || str_contains($tingkatClean, 'KELAS 3') || str_contains($tingkatClean, 'KELAS 4')) {
            return 'Fase B';
        }
        if (in_array($tingkatClean, ['5', '6', 'V', 'VI']) || str_contains($tingkatClean, 'KELAS 5') || str_contains($tingkatClean, 'KELAS 6')) {
            return 'Fase C';
        }
        if (in_array($tingkatClean, ['7', '8', '9', 'VII', 'VIII', 'IX']) || str_contains($jenjangClean, 'SMP')) {
            return 'Fase D';
        }
        if ($tingkatClean === '10' || $tingkatClean === 'X') {
            return 'Fase E';
        }
        if (in_array($tingkatClean, ['11', '12', 'XI', 'XII']) || str_contains($jenjangClean, 'SMA')) {
            return 'Fase F';
        }
        return 'Fase Terpadu';
    }

    // ════════════════════════════════════════════════════════════════════════
    // 3. MODUL AJAR (RPP DIGITAL) — Modul ajar kurikulum terpadu untuk Guru Test
    // ════════════════════════════════════════════════════════════════════════
    private function seedModulAjar(
        User $user,
        Employee $employee,
        AcademicYear $academicYear,
        Semester $semester,
        $schedules
    ): \Illuminate\Support\Collection {
        $count = 0;
        $createdModuls = collect();

        // Kelompokkan jadwal mengajar Guru Test berdasarkan mapel dan kelas
        $uniquePairs = $schedules->groupBy(function ($item) {
            return $item->subject_id . '_' . $item->kelas_id;
        });

        // Ambil semua semester yang ada di tahun ajaran aktif (Ganjil & Genap)
        $allSemesters = Semester::query()
            ->where('academic_year_id', $academicYear->id)
            ->orderBy('sequence')
            ->get();

        if ($allSemesters->isEmpty()) {
            $allSemesters = collect([$semester]);
        }

        foreach ($uniquePairs as $pairKey => $scheduleGroup) {
            $firstSchedule = $scheduleGroup->first();
            $subject = $firstSchedule->subject;
            $kelas = $firstSchedule->kelas;

            if (! $subject || ! $kelas) {
                continue;
            }

            $unitId = $kelas->unit_pendidikan_id ?? $employee->unit_id;

            // Cari kurikulum yang sesuai secara dinamis tanpa hardcode
            $kurikulum = MasterKurikulum::query()
                ->where('unit_pendidikan_id', $unitId)
                ->where('status', true)
                ->first()
                ?? MasterKurikulum::query()
                    ->where('unit_pendidikan_id', $unitId)
                    ->first()
                ?? MasterKurikulum::query()
                    ->where('status', true)
                    ->first()
                ?? MasterKurikulum::query()->first();

            if (! $kurikulum) {
                continue;
            }

            $fase = $this->determineFase($kelas->tingkat, $kelas->jenjang);
            $mapelName = $subject->nama_mapel ?? $subject->name ?? 'Mata Pelajaran';
            $kelasName = $kelas->nama_kelas ?? $kelas->name ?? 'Kelas';

            // Ambil Capaian Pembelajaran & Tujuan Pembelajaran jika tersedia
            $cp = CapaianPembelajaran::query()
                ->where('mata_pelajaran_id', $subject->id)
                ->where('kurikulum_id', $kurikulum->id)
                ->first()
                ?? CapaianPembelajaran::query()
                    ->where('mata_pelajaran_id', $subject->id)
                    ->first();

            $tp = $cp ? TujuanPembelajaran::query()->where('cp_id', $cp->id)->first() : null;

            // Buat Modul Ajar untuk setiap semester
            foreach ($allSemesters as $sem) {
                $semName = $sem->name ?? $sem->semester ?? 'Ganjil';

                $modulParts = [
                    [
                        'suffix' => 'M1',
                        'sub_judul' => 'Bagian I: Konsep Dasar & Pemahaman Inti',
                        'alokasi' => 16,
                    ],
                    [
                        'suffix' => 'M2',
                        'sub_judul' => 'Bagian II: Pendalaman, Aplikasi & Proyek Terpadu',
                        'alokasi' => 16,
                    ],
                ];

                foreach ($modulParts as $partInfo) {
                    $cleanKelas = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $kelas->kode_kelas ?? $kelasName), 0, 8));
                    $cleanMapel = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $subject->kode_mapel ?? $mapelName), 0, 10));
                    $cleanSem = strtoupper(substr($semName, 0, 3));
                    $cleanTeacher = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $employee->id), -4));
                    $kodeModul = "MOD-{$cleanKelas}-{$cleanMapel}-{$cleanSem}-{$partInfo['suffix']}-{$cleanTeacher}";

                    $judulModul = "Modul Ajar {$mapelName} {$kelasName} ({$semName}) — {$partInfo['sub_judul']}";

                    $modulAjar = LmsModulAjar::query()
                        ->where('guru_id', $employee->id)
                        ->where('mata_pelajaran_id', $subject->id)
                        ->where('kelas_id', $kelas->id)
                        ->where('semester_id', $sem->id)
                        ->where('kode_modul', $kodeModul)
                        ->first();

                    if (! $modulAjar) {
                        $modulAjar = LmsModulAjar::query()->create([
                            'unit_pendidikan_id'       => $unitId,
                            'tahun_ajaran_id'          => $academicYear->id,
                            'semester_id'              => $sem->id,
                            'kurikulum_id'             => $kurikulum->id,
                            'mata_pelajaran_id'        => $subject->id,
                            'guru_id'                  => $employee->id,
                            'kelas_id'                 => $kelas->id,
                            'rombel_id'                => $kelas->id,
                            'cp_id'                    => $cp?->id,
                            'tp_id'                    => $tp?->id,
                            'kode_modul'               => $kodeModul,
                            'judul_modul'              => $judulModul,
                            'fase'                     => $fase,
                            'semester'                 => $semName,
                            'alokasi_waktu_jp'         => $partInfo['alokasi'],
                            'tujuan_pembelajaran'      => "Peserta didik memahami, menganalisis, dan mempraktikkan materi {$mapelName} pada tingkat {$kelasName} dengan capaian kompetensi unggul dan berakhlak mulia.",
                            'profil_pelajar_pancasila' => 'Beriman, Bertakwa kepada Tuhan YME, Berakhlak Mulia, Mandiri, Bernalar Kritis, Gotong Royong',
                            'target_peserta_didik'     => 'Peserta Didik Reguler / Tipikal',
                            'model_pembelajaran'       => 'Problem Based Learning (PBL) & Project Based Learning (PjBL)',
                            'metode_pembelajaran'      => 'Talaqqi Terbimbing, Diskusi Interaktif, Praktik Lapangan/Laboratorium, Refleksi Diri',
                            'media_pembelajaran'       => 'Slide Pembelajaran Interaktif, Video Animasi Edukasi, Lembar Kerja Siswa (LKS), Smart Screen',
                            'sumber_belajar'           => 'Buku Paket Resmi Darel Iman, Modul Digital Kurikulum Terpadu, Sumber Rujukan Tepercaya',
                            'kegiatan_pendahuluan'     => "1. Pembukaan majelis belajar dengan salam, doa bersama, dan tadarus tilawah singkat.\n2. Guru mengecek kesiapan fisik dan psikologis siswa serta presensi kehadiran.\n3. Apersepsi bermakna mengaitkan materi terdahulu dengan fenomena harian.\n4. Penyampaian tujuan capaian kompetensi dan alur kegiatan pembelajaran.",
                            'kegiatan_inti'            => "1. Stimulasi & Orientasi: Siswa menyimak tayangan permasalahan kontekstual terkait {$mapelName}.\n2. Organisasi Belajar: Siswa dibagi dalam kelompok kerja terarah untuk mendiskusikan lembar eksplorasi.\n3. Penyelidikan Mandiri & Kelompok: Guru membimbing proses pencarian informasi, analisis kritis, dan penyusunan solusi alternatif.\n4. Presentasi & Verifikasi: Setiap kelompok memaparkan hasil telaah dan saling memberikan umpan balik konstruktif.",
                            'kegiatan_penutup'         => "1. Guru bersama peserta didik menyimpulkan poin esensial pembelajaran hari ini.\n2. Refleksi pembelajaran dan apresiasi terhadap keaktifan siswa.\n3. Pemberian penugasan mandiri / pengayaan dan arahan materi untuk pertemuan berikutnya.\n4. Doa kafaratul majelis dan penutup.",
                            'asesmen_awal'             => 'Tes diagnostik kognitif awal dan kuis apersepsi pemantik minat',
                            'asesmen_proses'           => 'Observasi keaktifan diskusi, penilaian rubrik unjuk kerja, dan lembar ceklis sikap',
                            'asesmen_akhir'            => 'Tes formatif tertulis, laporan proyek mandiri/kelompok, dan penilaian portofolio tugas',
                            'rencana_penilaian'        => 'Asesmen Formatif Keaktifan (30%), Uji Capaian Bab (35%), Portofolio Proyek (35%)',
                            'refleksi_guru'            => 'Alhamdulillah pembelajaran berjalan kondusif. Pemahaman konsep dasar telah tercapai oleh sebagian besar santri, perhatian khusus diberikan kepada siswa yang memerlukan pendampingan diferensiasi.',
                            'status'                   => 'published',
                            'versi'                    => '1.0',
                            'deskripsi'                => "Modul ajar digital terpadu {$mapelName} {$kelasName} berbasis nilai-nilai keislaman dan kurikulum SIT terakreditasi.",
                            'created_by'               => $user->id,
                        ]);
                        $count++;
                    }

                    $createdModuls->push($modulAjar);
                }
            }
        }

        // Sinkronisasi materi Guru Test yang sudah ada agar terhubung ke Modul Ajar Guru Test
        $allTeacherMateri = LmsMateri::query()->where('guru_id', $employee->id)->get();
        foreach ($allTeacherMateri as $mat) {
            $matchedModul = LmsModulAjar::query()
                ->where('guru_id', $employee->id)
                ->where('mata_pelajaran_id', $mat->mata_pelajaran_id)
                ->first();

            if (! $matchedModul) {
                $mSubj = Subject::find($mat->mata_pelajaran_id);
                if ($mSubj) {
                    $mName = $mSubj->nama_mapel ?? $mSubj->name ?? '';
                    $matchedModul = LmsModulAjar::query()
                        ->where('guru_id', $employee->id)
                        ->whereHas('subject', function ($sq) use ($mName) {
                            $sq->where('nama_mapel', 'ILIKE', "%{$mName}%")
                               ->orWhere('name', 'ILIKE', "%{$mName}%");
                        })
                        ->first();
                }
            }

            if (! $matchedModul) {
                $matchedModul = LmsModulAjar::query()
                    ->where('guru_id', $employee->id)
                    ->first();
            }

            if ($matchedModul && $mat->modul_ajar_id !== $matchedModul->id) {
                $mat->update([
                    'modul_ajar_id'     => $matchedModul->id,
                    'mata_pelajaran_id' => $matchedModul->mata_pelajaran_id,
                ]);
            }
        }

        // Sinkronisasi penugasan Guru Test yang sudah ada agar terhubung ke Modul Ajar Guru Test
        $allTeacherTugas = LmsPenugasan::query()->where('guru_id', $employee->id)->get();
        foreach ($allTeacherTugas as $tug) {
            $matchedModul = LmsModulAjar::query()
                ->where('guru_id', $employee->id)
                ->where('mata_pelajaran_id', $tug->mata_pelajaran_id)
                ->where('kelas_id', $tug->kelas_id)
                ->first()
                ?? LmsModulAjar::query()
                    ->where('guru_id', $employee->id)
                    ->where('mata_pelajaran_id', $tug->mata_pelajaran_id)
                    ->first()
                ?? LmsModulAjar::query()
                    ->where('guru_id', $employee->id)
                    ->first();

            if ($matchedModul && $tug->modul_ajar_id !== $matchedModul->id) {
                $tug->update(['modul_ajar_id' => $matchedModul->id]);
            }
        }

        $this->command?->info("  ✓ [3] Modul Ajar       : {$count} modul dibuat (lms_modul_ajar)");

        return $createdModuls;
    }

    // ════════════════════════════════════════════════════════════════════════
    // 4. MATERI BELAJAR — 20 materi, 2/minggu sejak Agustus 2026
    // ════════════════════════════════════════════════════════════════════════
    private function seedMateriBelajar(
        Teacher $teacher,
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $subjects,
        $kelasList
    ): void {
        $startDate = Carbon::parse('2026-08-04'); // Senin pertama Agustus
        $count = 0;

        $judulTpl = [
            'Pengantar Materi {mapel} Pekan {n}',
            'Ringkasan Bab {n} — {mapel}',
            'Modul Pembelajaran {mapel} #{n}',
            'Lembar Kerja Siswa {mapel} Pekan {n}',
            'Rangkuman Konsep Utama {mapel} #{n}',
            'Slide Presentasi {mapel} Bab {n}',
            'Catatan Kelas {mapel} Pertemuan {n}',
            'Panduan Belajar Mandiri {mapel} #{n}',
            'Latihan Soal {mapel} Paket {n}',
            'Evaluasi Formatif {mapel} Pekan {n}',
        ];

        for ($i = 1; $i <= 20; $i++) {
            $subject   = $subjects->get(($i - 1) % $subjects->count());
            $kelas     = $kelasList->get(($i - 1) % $kelasList->count());
            $mapelName = $subject->nama_mapel ?? $subject->name ?? 'Umum';
            $tpl       = $judulTpl[($i - 1) % count($judulTpl)];
            $judul     = str_replace(['{mapel}', '{n}'], [$mapelName, $i], $tpl);
            $tipe      = ['pdf', 'video', 'teks', 'link'][($i - 1) % 4];
            $weekOffset = (int) ceil($i / 2) - 1;
            $pubDate    = $startDate->copy()->addWeeks($weekOffset)->startOfWeek();

            if (LmsMateri::query()
                ->where('guru_id', $employee->id)
                ->where('judul', $judul)
                ->exists()) {
                continue;
            }

            // Temukan Modul Ajar milik Guru Test untuk mapel dan kelas ini
            $modulAjar = LmsModulAjar::query()
                ->where('guru_id', $employee->id)
                ->where('mata_pelajaran_id', $subject->id)
                ->where('kelas_id', $kelas->id)
                ->first()
                ?? LmsModulAjar::query()
                    ->where('guru_id', $employee->id)
                    ->where('mata_pelajaran_id', $subject->id)
                    ->first();

            if (! $modulAjar) {
                $unitId = $kelas->unit_pendidikan_id ?? $employee->unit_id;
                $kurikulumId = MasterKurikulum::query()
                    ->where('unit_pendidikan_id', $unitId)
                    ->where('status', true)
                    ->value('id')
                    ?? MasterKurikulum::query()->value('id');

                $modulAjar = LmsModulAjar::query()->create([
                    'unit_pendidikan_id'    => $unitId,
                    'tahun_ajaran_id'       => $ay->id,
                    'semester_id'           => $semester->id,
                    'kurikulum_id'          => $kurikulumId,
                    'mata_pelajaran_id'     => $subject->id,
                    'guru_id'               => $employee->id,
                    'kelas_id'              => $kelas->id,
                    'rombel_id'             => $kelas->id,
                    'kode_modul'            => 'MOD-' . strtoupper(substr(md5($subject->id . $kelas->id . $i), 0, 8)),
                    'judul_modul'           => "Modul Ajar {$mapelName} {$kelas->nama_kelas} TP 2026/2027",
                    'fase'                  => $this->determineFase($kelas->tingkat, $kelas->jenjang),
                    'semester'              => $semester->name ?? 'Ganjil',
                    'alokasi_waktu_jp'      => 16,
                    'tujuan_pembelajaran'   => "Peserta didik memahami konsep materi {$mapelName}.",
                    'status'                => 'published',
                    'deskripsi'             => "Modul ajar kurikulum terpadu {$mapelName}.",
                    'created_by'            => $employee->user_id,
                ]);
            }

            LmsMateri::query()->create([
                'modul_ajar_id'     => $modulAjar->id,
                'mata_pelajaran_id' => $subject->id,
                'guru_id'           => $employee->id,
                'judul'             => $judul,
                'tipe_materi'       => $tipe,
                'tipe'              => $tipe,
                'konten'            => "Konten materi {$mapelName} untuk pertemuan ke-{$i}. "
                    . "Materi ini mencakup pokok bahasan yang sesuai dengan kurikulum semester ini.",
                'isi'               => "Isi lengkap materi {$mapelName} pekan ke-" . ceil($i / 2) . ".",
                'urutan'            => $i,
                'status'            => 'aktif',
                'is_published'      => $i <= 16,
                'tanggal_publish'   => $i <= 16 ? $pubDate->toDateTimeString() : null,
                'catatan'           => $i > 16 ? 'Draft — belum dipublikasikan' : null,
                'created_by'        => $employee->user_id,
            ]);
            $count++;
        }

        $this->command?->info("  ✓ [4] Materi Belajar   : {$count} materi (lms_materi)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // 5. PENUGASAN — 12 tugas dengan deadline bertahap
    // ════════════════════════════════════════════════════════════════════════
    private function seedPenugasan(
        Teacher $teacher,
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $subjects,
        $kelasList
    ): void {
        $startDate = Carbon::parse('2026-08-11'); // Senin ke-2 Agustus
        $count = 0;

        $judulTpl = [
            'Tugas Harian #{n} — {mapel}',
            'PR Latihan Soal {mapel} Pekan {n}',
            'Evaluasi Mingguan {mapel} #{n}',
            'Ulangan Harian {mapel} Bab {n}',
            'Proyek Mini {mapel} Kelompok {n}',
            'Kuis {mapel} Sesi {n}',
            'Latihan Soal PTS {mapel} #{n}',
            'Remedial {mapel} Pertemuan {n}',
            'Tugas Analisis {mapel} #{n}',
            'Diskusi Kelompok {mapel} Pekan {n}',
            'Portofolio {mapel} Semester {n}',
            'Presentasi {mapel} Topik {n}',
        ];

        $instruksiPool = [
            'Kerjakan soal berikut dengan teliti dan tulis jawaban secara lengkap di buku tugas.',
            'Jawab pertanyaan di bawah ini secara mandiri dan jujur. Tidak diperbolehkan menyontek.',
            'Baca materi yang telah dibagikan, lalu kerjakan soal latihan berikut ini.',
            'Diskusikan dengan kelompok dan buat laporan hasil diskusi minimal 2 halaman.',
            'Tulis rangkuman materi minggu ini minimal 300 kata menggunakan bahasa sendiri.',
            'Buat mind-map konsep utama dari bab yang telah dipelajari, minimal 5 cabang utama.',
        ];

        for ($i = 1; $i <= 12; $i++) {
            $subject   = $subjects->get(($i - 1) % $subjects->count());
            $kelas     = $kelasList->get(($i - 1) % $kelasList->count());
            $mapelName = $subject->nama_mapel ?? $subject->name ?? 'Umum';
            $tpl       = $judulTpl[$i - 1];
            $judul     = str_replace(['{mapel}', '{n}'], [$mapelName, $i], $tpl);
            $instruksi = $instruksiPool[($i - 1) % count($instruksiPool)];
            $deadline  = $startDate->copy()->addWeeks($i - 1)->next(Carbon::FRIDAY)->toDateString();
            $jenis     = ['essay', 'objektif', 'campuran'][($i - 1) % 3];
            $bobot     = [100, 80, 60, 100, 90, 70][$i % 6];

            if (LmsPenugasan::query()
                ->where('guru_id', $employee->id)
                ->where('judul_tugas', $judul)
                ->exists()) {
                continue;
            }

            $modulAjar = LmsModulAjar::query()
                ->where('guru_id', $employee->id)
                ->where('mata_pelajaran_id', $subject->id)
                ->where('kelas_id', $kelas->id)
                ->first()
                ?? LmsModulAjar::query()
                    ->where('guru_id', $employee->id)
                    ->where('mata_pelajaran_id', $subject->id)
                    ->first();

            LmsPenugasan::query()->create([
                'mata_pelajaran_id'     => $subject->id,
                'kelas_id'              => $kelas->id,
                'guru_id'               => $employee->id,
                'semester_id'           => $semester->id,
                'tahun_ajaran_id'       => $ay->id,
                'modul_ajar_id'         => $modulAjar?->id,
                'judul_tugas'           => $judul,
                'instruksi'             => $instruksi,
                'deskripsi'             => "Tugas ke-{$i} {$mapelName}. {$instruksi}",
                'tipe_tugas'            => 'both',
                'jenis_tugas'           => $jenis,
                'nilai_maksimal'        => 100,
                'bobot_persen'          => $bobot,
                'deadline'              => $deadline,
                'izin_kumpul_terlambat' => true,
                'is_published'          => $i <= 10,
                'status'                => 'aktif',
                'created_by'            => $employee->user_id,
            ]);
            $count++;
        }

        $this->command?->info("  ✓ [5] Penugasan        : {$count} tugas (lms_penugasan)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // 6. PENILAIAN SISWA — nilai lengkap per siswa, per mapel
    // ════════════════════════════════════════════════════════════════════════
    private function seedPenilaianSiswa(
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $subjects,
        $kelasList,
        $userId
    ): void {
        $count = 0;

        foreach ($kelasList->take(3) as $kelas) {
            $students = Student::query()
                ->where('kelas_id', $kelas->id)
                ->orderBy('full_name')
                ->take(30)
                ->get();

            if ($students->isEmpty()) continue;

            foreach ($subjects->take(5) as $subject) {
                foreach ($students as $sIdx => $student) {
                    $base = 65 + ($sIdx % 8) * 3; // nilai berbeda antar siswa

                    if (StudentGrade::query()
                        ->where('student_id', $student->id)
                        ->where('subject_id', $subject->id)
                        ->where('academic_year_id', $ay->id)
                        ->where('semester_id', $semester->id)
                        ->exists()) {
                        continue;
                    }

                    $scoreAssignment = min(100, $base + rand(0, 15));
                    $scoreQuiz       = min(100, $base + rand(-5, 20));
                    $scoreProject    = min(100, $base + rand(0, 10));
                    $scoreMidterm    = min(100, $base + rand(-10, 15));
                    $scoreFinal      = min(100, $base + rand(-5, 20));
                    $finalScore      = round(
                        ($scoreAssignment * 0.20) +
                        ($scoreQuiz * 0.10) +
                        ($scoreProject * 0.15) +
                        ($scoreMidterm * 0.25) +
                        ($scoreFinal * 0.30),
                        2
                    );
                    $gradeLetter = match (true) {
                        $finalScore >= 90 => 'A',
                        $finalScore >= 80 => 'B',
                        $finalScore >= 70 => 'C',
                        $finalScore >= 60 => 'D',
                        default           => 'E',
                    };

                    StudentGrade::query()->create([
                        'student_id'       => $student->id,
                        'subject_id'       => $subject->id,
                        'academic_year_id' => $ay->id,
                        'semester_id'      => $semester->id,
                        'kelas_id'         => $kelas->id,
                        'score_assignment' => $scoreAssignment,
                        'score_quiz'       => $scoreQuiz,
                        'score_project'    => $scoreProject,
                        'score_midterm'    => $scoreMidterm,
                        'score_final'      => $scoreFinal,
                        'final_score'      => $finalScore,
                        'grade_letter'     => $gradeLetter,
                        'is_passed'        => $finalScore >= 60,
                        'notes'            => $finalScore < 60 ? 'Perlu remedial' : null,
                        'created_by'       => $userId,
                        'metadata'         => ['source' => 'GuruTestWorkspaceSeeder'],
                    ]);
                    $count++;
                }
            }
        }

        $this->command?->info("  ✓ [6] Penilaian Siswa  : {$count} record (student_grades)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // 7 & 8. TAHFIZH AL-QURAN — Ziyadah & Murojaah sejak Agustus 2026
    // ════════════════════════════════════════════════════════════════════════
    private function seedTahfizh(
        Teacher $teacher,
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $kelasList
    ): void {
        $today  = Carbon::now();
        $start  = Carbon::parse('2026-08-01');
        $total  = 0;

        foreach ($kelasList->take(2) as $kelas) {
            $students = Student::query()
                ->where('kelas_id', $kelas->id)
                ->orderBy('full_name')
                ->take(25)
                ->get();

            if ($students->isEmpty()) continue;

            foreach ($students as $sIdx => $student) {
                $numRecords = ($sIdx % 5) + 4; // 4–8 setoran per siswa

                for ($r = 0; $r < $numRecords; $r++) {
                    $surah = self::SURAH_LIST[($sIdx + $r) % count(self::SURAH_LIST)];
                    // Tanggal setoran: mulai Agustus, interval ~2 pekan
                    $depositDate = $start->copy()
                        ->addDays(($r * 14) + ($sIdx % 6))
                        ->toDateString();

                    if (Carbon::parse($depositDate)->gt($today)) break;

                    $ayahStart = ($r % 3 === 0) ? 1 : (($r % 7) + 1);
                    $ayahEnd   = min($surah['ayat'], $ayahStart + ($r % 5) + 2);
                    $type      = $r % 3 === 0 ? 'Ziyadah' : ($r % 3 === 1 ? 'Murojaah' : 'Tasmi');
                    $nilai     = ['Mumtaz', 'Jayyid Jiddan', 'Jayyid', 'Maqbul'][($sIdx + $r) % 4];

                    if (TahfizhRecord::query()
                        ->where('student_id', $student->id)
                        ->where('teacher_id', $teacher->id)
                        ->where('deposit_date', $depositDate)
                        ->where('surah_name', $surah['nama'])
                        ->exists()) {
                        continue;
                    }

                    TahfizhRecord::query()->create([
                        'academic_year_id' => $ay->id,
                        'semester_id'      => $semester->id,
                        'deposit_date'     => $depositDate,
                        'student_id'       => $student->id,
                        'class_id'         => $kelas->id,
                        'teacher_id'       => $teacher->id,
                        'employee_id'      => $employee->id,
                        'surah_name'       => $surah['nama'],
                        'ayah_start'       => $ayahStart,
                        'ayah_end'         => $ayahEnd,
                        'line_count'       => max(1, $ayahEnd - $ayahStart + 1),
                        'status'           => 'approved',
                        'notes'            => "{$nilai} — setoran {$type}",
                        'metadata'         => [
                            'source'       => 'GuruTestWorkspaceSeeder',
                            'surah_number' => $surah['nomor'],
                            'juz'          => $surah['juz'],
                            'type'         => $type,
                            'nilai'        => $nilai,
                        ],
                    ]);
                    $total++;
                }
            }
        }

        $this->command?->info("  ✓ [7] Tahfizh Al-Quran : {$total} setoran (tahfizh_records)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // 9. MUTABAAH YAUMIYYAH — 4 minggu data sejak Agustus 2026
    // ════════════════════════════════════════════════════════════════════════
    private function seedMutabaah(
        Employee $employee,
        AcademicYear $ay,
        Semester $semester,
        $kelasList
    ): void {
        if (! Schema::hasTable('mutabaah_daily_headers')) {
            $this->command?->warn('  ⚠ Tabel mutabaah_daily_headers belum ada, skip.');
            return;
        }

        // Cek apakah ada MutabaahTemplate
        $template = MutabaahTemplate::query()->where('is_active', true)->first()
            ?? MutabaahTemplate::query()->first();

        if (! $template) {
            $this->command?->warn('  ⚠ Tidak ada MutabaahTemplate aktif. Jalankan MutabaahEnterpriseSeeder dulu.');
            return;
        }

        // Ambil item template
        $templateItems = MutabaahTemplateItem::query()
            ->where('template_id', $template->id)
            ->get();

        $totalHeaders = 0;

        // Buat supervisor assignment jika belum ada
        $kelas = $kelasList->first();
        if (! $kelas) return;

        $assignment = MutabaahSupervisorAssignment::query()->updateOrCreate(
            [
                'employee_id'      => $employee->id,
                'rombel_id'        => $kelas->id,
                'academic_year_id' => $ay->id,
                'semester_id'      => $semester->id,
            ],
            [
                'supervisor_type'    => 'wali_kelas',
                'education_unit_id'  => $kelas->unit_pendidikan_id ?? $employee->unit_id,
                'template_id'        => $template->id,
                'start_date'         => '2026-08-01',
                'end_date'           => '2026-12-31',
                'is_primary'         => true,
                'can_input'          => true,
                'can_edit'           => true,
                'can_finalize'       => true,
                'can_view_report'    => true,
                'status'             => 'active',
                'created_by'         => $employee->user_id,
            ]
        );

        $students = Student::query()
            ->where('kelas_id', $kelas->id)
            ->take(20)
            ->get();

        if ($students->isEmpty()) {
            $this->command?->warn('  ⚠ Tidak ada siswa di kelas pertama untuk Mutabaah.');
            return;
        }

        $startDate = Carbon::parse('2026-08-04'); // Senin pertama
        $today     = Carbon::now();

        // 4 minggu data
        for ($week = 0; $week < 8; $week++) {
            for ($dayOff = 0; $dayOff < 6; $dayOff++) { // Senin–Sabtu
                $date = $startDate->copy()->addWeeks($week)->addDays($dayOff);
                if ($date->gt($today)) break 2;

                foreach ($students->take(10) as $sIdx => $student) {
                    if (MutabaahDailyHeader::query()
                        ->where('student_id', $student->id)
                        ->where('rombel_id', $kelas->id)
                        ->whereDate('activity_date', $date->toDateString())
                        ->exists()) {
                        continue;
                    }

                    $goodCount = ($sIdx % 4) + 4; // 4–7
                    $totalItems = max($templateItems->count(), 10);
                    $notDone = $totalItems - $goodCount - 1;

                    $header = MutabaahDailyHeader::query()->create([
                        'student_id'              => $student->id,
                        'template_id'             => $template->id,
                        'supervisor_assignment_id'=> $assignment->id,
                        'education_unit_id'       => $kelas->unit_pendidikan_id ?? $employee->unit_id,
                        'rombel_id'               => $kelas->id,
                        'academic_year_id'        => $ay->id,
                        'semester_id'             => $semester->id,
                        'activity_date'           => $date->toDateString(),
                        'status'                  => 'finalized',
                        'total_items'             => $totalItems,
                        'good_count'              => $goodCount,
                        'less_count'              => 1,
                        'not_done_count'          => max(0, $notDone),
                        'na_count'                => 0,
                        'score'                   => round($goodCount / $totalItems * 100, 2),
                        'supervisor_notes'        => $sIdx % 3 === 0
                            ? 'Alhamdulillah, mutabaah hari ini memuaskan.'
                            : null,
                        'finalized_at'            => $date->copy()->addHours(20)->toDateTimeString(),
                        'finalized_by'            => $employee->user_id,
                        'created_by'              => $employee->user_id,
                    ]);

                    // Buat detail per item template
                    if ($templateItems->isNotEmpty()) {
                        foreach ($templateItems->take(8) as $tIdx => $item) {
                            $statusVal = $tIdx < $goodCount ? 'good' : ($tIdx === $goodCount ? 'less' : 'not_done');
                            MutabaahDailyDetail::query()->updateOrCreate(
                                [
                                    'daily_header_id'  => $header->id,
                                    'template_item_id' => $item->id,
                                ],
                                [
                                    'agenda_item_id'      => $item->agenda_item_id,
                                    'status_value'        => $statusVal,
                                    'numeric_value'       => null,
                                    'notes'               => null,
                                    'input_source'        => 'system',
                                    'input_location'      => 'school',
                                    'verification_status' => 'verified',
                                    'input_by'            => $employee->user_id,
                                    'input_at'            => $date->copy()->addHours(15)->toDateTimeString(),
                                ]
                            );
                        }
                    }

                    $totalHeaders++;
                }
            }
        }

        $this->command?->info("  ✓ [9] Mutabaah Yaumiyyah: {$totalHeaders} header (mutabaah_daily_headers)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // 10. CATATAN SISWA — 3 catatan per siswa, berbeda kategori
    // ════════════════════════════════════════════════════════════════════════
    private function seedCatatanSiswa(
        Teacher $teacher,
        AcademicYear $ay,
        Semester $semester,
        $kelasList
    ): void {
        $categories = ['Akademik', 'Perilaku', 'Kedisiplinan', 'Prestasi', 'Ibadah', 'Kesehatan'];
        $priorities  = ['low', 'medium', 'high'];

        $noteTemplates = [
            ['Akademik',     'Perkembangan belajar semester ini',    'Siswa menunjukkan perkembangan positif dalam memahami materi. Aktif bertanya saat pembelajaran berlangsung.'],
            ['Perilaku',     'Sikap dan perilaku di kelas',          'Siswa berperilaku baik dan sopan. Menghormati guru dan teman-teman sekelas.'],
            ['Kedisiplinan', 'Catatan kedisiplinan pengumpulan tugas','Perlu ditingkatkan kedisiplinan dalam mengumpulkan tugas tepat waktu.'],
            ['Prestasi',     'Prestasi dan pencapaian istimewa',     'Berhasil meraih nilai tertinggi pada ujian harian. Potensi besar untuk dikembangkan.'],
            ['Ibadah',       'Evaluasi ibadah dan karakter islami',  'Rajin melaksanakan shalat berjamaah. Hafalan Al-Quran berkembang dengan baik.'],
            ['Kesehatan',    'Catatan kesehatan siswa',              'Sempat tidak masuk karena sakit selama 2 hari. Perlu diperhatikan kondisi kesehatannya.'],
        ];

        $count = 0;
        $today = Carbon::now();

        foreach ($kelasList->take(2) as $kelas) {
            $students = Student::query()
                ->where('kelas_id', $kelas->id)
                ->take(20)
                ->get();

            foreach ($students as $sIdx => $student) {
                for ($n = 0; $n < 3; $n++) {
                    $tmpl     = $noteTemplates[($sIdx * 3 + $n) % count($noteTemplates)];
                    $category = $tmpl[0];
                    $title    = $tmpl[1];
                    $content  = $tmpl[2];
                    $priority = $priorities[($sIdx + $n) % count($priorities)];
                    $noteDate = $today->copy()->subDays(($sIdx * 5) + ($n * 14))->toDateString();

                    if (StudentNote::query()
                        ->where('student_id', $student->id)
                        ->where('teacher_id', $teacher->id)
                        ->whereDate('date', $noteDate)
                        ->where('title', $title)
                        ->exists()) {
                        continue;
                    }

                    StudentNote::query()->create([
                        'student_id'         => $student->id,
                        'teacher_id'         => $teacher->id,
                        'education_unit_id'  => $student->unit_id ?? $kelas->unit_pendidikan_id,
                        'academic_year_id'   => $ay->id,
                        'semester_id'        => $semester->id,
                        'date'               => $noteDate,
                        'category'           => $category,
                        'title'              => $title,
                        'content'            => $content,
                        'note'               => $content,
                        'priority'           => $priority,
                        'visible_to_parent'  => true,
                        'visible_to_student' => true,
                    ]);
                    $count++;
                }
            }
        }

        $this->command?->info("  ✓ [10] Catatan Siswa   : {$count} catatan (student_notes)");
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════
    private function topicFor(string $mapel, int $meeting): string
    {
        $topics = [
            "Pengantar {$mapel} — Materi Awal Semester",
            "Konsep Dasar {$mapel} Bab 1",
            "Latihan dan Aplikasi {$mapel} Sesi {$meeting}",
            "Diskusi Materi {$mapel} Pekan ke-" . ceil($meeting / 5),
            "Review dan Evaluasi {$mapel} Pertemuan {$meeting}",
            "Pengayaan Materi {$mapel} Lanjutan",
            "Studi Kasus {$mapel} — Praktik Nyata",
            "Presentasi Siswa — {$mapel} Bab " . ceil($meeting / 3),
        ];
        return $topics[$meeting % count($topics)];
    }
}
