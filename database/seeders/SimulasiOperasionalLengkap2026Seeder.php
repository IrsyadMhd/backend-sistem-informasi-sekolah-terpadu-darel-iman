<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsModulAjar;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsRapor;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\TahfizhDailyLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulasiOperasionalLengkap2026Seeder extends Seeder
{
    /**
     * Seeder Pelengkap Operasional Terpadu T.P 2026/2027:
     * 1. Melengkapi Setoran Tahfizh untuk 100% siswa/santri (non-hardcode, variatif per jenjang).
     * 2. Melengkapi Mutaba'ah Yaumiyyah untuk 100% siswa/santri (header & details terintegrasi template).
     * 3. Melengkapi Ujian CBT Semester Genap (PTS Genap & PAT Kenaikan Kelas di lms_ujian & sesi).
     * 4. Melengkapi Penugasan & Kuis LMS Semester Genap (Pekan 17 s/d 32 di lms_penugasan).
     * 5. Melengkapi Rekap Nilai Evaluasi Semester Genap (student_grades).
     */
    public function run(): void
    {
        $this->command?->info('=== MEMULAI SEEDER SIMULASI OPERASIONAL LENGKAP 2026/2027 ===');

        DB::disableQueryLog();

        // 1. Dapatkan Tahun Ajaran & Semester
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::where('name', 'like', '%2026/2027%')->first()
            ?? AcademicYear::first();

        $semGanjil = Semester::where('academic_year_id', $academicYear->id)
            ->where(function ($q) {
                $q->where('sequence', 1)->orWhere('name', 'like', '%Ganjil%');
            })->first();

        $semGenap = Semester::where('academic_year_id', $academicYear->id)
            ->where(function ($q) {
                $q->where('sequence', 2)->orWhere('name', 'like', '%Genap%');
            })->first();

        if (! $academicYear || ! $semGanjil || ! $semGenap) {
            $this->command?->error('Tahun ajaran atau semester tidak lengkap!');
            return;
        }

        $this->command?->info("Tahun Ajaran: {$academicYear->name} | Semester: {$semGanjil->name} & {$semGenap->name}");

        // =========================================================================
        // BAGIAN 1: MELENGKAPI SETORAN TAHFIZH SISWA/SANTRI (100% CAKUPAN)
        // =========================================================================
        $this->seedCompleteTahfizh($academicYear, $semGanjil, $semGenap);

        // =========================================================================
        // BAGIAN 2: MELENGKAPI MUTABA'AH YAUMIYYAH SISWA/SANTRI (100% CAKUPAN)
        // =========================================================================
        $this->seedCompleteMutabaah($academicYear, $semGanjil, $semGenap);

        // =========================================================================
        // BAGIAN 3: MELENGKAPI UJIAN CBT SEMESTER GENAP (PTS GENAP & PAT)
        // =========================================================================
        $this->seedSemesterGenapExams($academicYear, $semGenap);

        // =========================================================================
        // BAGIAN 4: MELENGKAPI TUGAS & KUIS LMS SEMESTER GENAP (PEKAN 17 - 32)
        // =========================================================================
        $this->seedSemesterGenapAssignments($academicYear, $semGenap);

        // =========================================================================
        // BAGIAN 5: MELENGKAPI NILAI AKADEMIK EVALUASI SEMESTER GENAP
        // =========================================================================
        $this->seedSemesterGenapGrades($academicYear, $semGenap);

        // =========================================================================
        // BAGIAN 6: MELENGKAPI SESI PENGERJAAN UJIAN CBT SISWA (lms_ujian_sesi)
        // =========================================================================
        $this->seedCompleteExamSessions();

        $this->command?->info('=== [SELESAI] SELURUH DATA SIMULASI TELAH DILENGKAPI DENGAN SUKSES ===');
    }

    /**
     * 1. Melengkapi Setoran Tahfizh untuk Setiap Siswa
     */
    private function seedCompleteTahfizh(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('1. Memeriksa & melengkapi setoran Tahfizh harian untuk seluruh siswa...');

        $existingStudentCount = DB::table('tahfizh_daily_logs')->distinct()->count('student_id');
        $totalStudents = Student::count();
        if ($existingStudentCount >= $totalStudents && $totalStudents > 0) {
            $this->command?->info("✓ Seluruh siswa ({$existingStudentCount} siswa) sudah memiliki catatan tahfizh, melewati pengisian ulang.");
            return;
        }

        // Guru halaqah cache per unit
        $teachersByUnit = Employee::whereNotNull('unit_id')
            ->get(['id', 'unit_id', 'nama_lengkap'])
            ->groupBy('unit_id');

        $defaultTeacher = Employee::first();

        // Bank surah juz 30 & 29 untuk variasi dinamis
        $surahBank = [
            'tk' => [
                ['no' => 114, 'name' => 'An-Nas', 'ayat_max' => 6],
                ['no' => 113, 'name' => 'Al-Falaq', 'ayat_max' => 5],
                ['no' => 112, 'name' => 'Al-Ikhlas', 'ayat_max' => 4],
                ['no' => 111, 'name' => 'Al-Lahab', 'ayat_max' => 5],
                ['no' => 110, 'name' => 'An-Nashr', 'ayat_max' => 3],
                ['no' => 109, 'name' => 'Al-Kafirun', 'ayat_max' => 6],
                ['no' => 108, 'name' => 'Al-Kautsar', 'ayat_max' => 3],
                ['no' => 107, 'name' => 'Al-Ma\'un', 'ayat_max' => 7],
                ['no' => 106, 'name' => 'Quraisy', 'ayat_max' => 4],
                ['no' => 105, 'name' => 'Al-Fil', 'ayat_max' => 5],
                ['no' => 104, 'name' => 'Al-Humazah', 'ayat_max' => 9],
                ['no' => 103, 'name' => 'Al-\'Ashr', 'ayat_max' => 3],
            ],
            'sd' => [
                ['no' => 93, 'name' => 'Adh-Dhuha', 'ayat_max' => 11],
                ['no' => 94, 'name' => 'Asy-Syarh', 'ayat_max' => 8],
                ['no' => 95, 'name' => 'At-Tin', 'ayat_max' => 8],
                ['no' => 96, 'name' => 'Al-\'Alaq', 'ayat_max' => 19],
                ['no' => 97, 'name' => 'Al-Qadr', 'ayat_max' => 5],
                ['no' => 98, 'name' => 'Al-Bayyinah', 'ayat_max' => 8],
                ['no' => 99, 'name' => 'Az-Zalzalah', 'ayat_max' => 8],
                ['no' => 100, 'name' => 'Al-\'Adiyat', 'ayat_max' => 11],
                ['no' => 101, 'name' => 'Al-Qari\'ah', 'ayat_max' => 11],
                ['no' => 102, 'name' => 'At-Takatsur', 'ayat_max' => 8],
                ['no' => 78, 'name' => 'An-Naba\'', 'ayat_max' => 40],
                ['no' => 79, 'name' => 'An-Nazi\'at', 'ayat_max' => 46],
                ['no' => 80, 'name' => '\'Abasa', 'ayat_max' => 42],
            ],
            'smp_sma' => [
                ['no' => 67, 'name' => 'Al-Mulk', 'ayat_max' => 30],
                ['no' => 68, 'name' => 'Al-Qalam', 'ayat_max' => 52],
                ['no' => 69, 'name' => 'Al-Haqqah', 'ayat_max' => 52],
                ['no' => 70, 'name' => 'Al-Ma\'arij', 'ayat_max' => 44],
                ['no' => 71, 'name' => 'Nuh', 'ayat_max' => 28],
                ['no' => 72, 'name' => 'Al-Jinn', 'ayat_max' => 28],
                ['no' => 73, 'name' => 'Al-Muzzammil', 'ayat_max' => 20],
                ['no' => 74, 'name' => 'Al-Muddatstsir', 'ayat_max' => 56],
                ['no' => 56, 'name' => 'Al-Waqi\'ah', 'ayat_max' => 96],
                ['no' => 55, 'name' => 'Ar-Rahman', 'ayat_max' => 78],
                ['no' => 36, 'name' => 'Yasin', 'ayat_max' => 83],
                ['no' => 18, 'name' => 'Al-Kahf', 'ayat_max' => 110],
            ],
        ];

        $teacherNotes = [
            'Masya Allah makhraj huruf dan tajwid sangat fasih, bacaan tartil dan mutqin.',
            'Hafalan lancar tanpa terbata-bata, dengung ghunnah dan panjang mad tepat.',
            'Alhamdulillah setoran selesai dengan baik. Pertahankan kelancaran muraja\'ah mandiri.',
            'Bacaan sudah sangat baik, perhatikan kembali waqaf dan ibtida\' pada ayat-ayat panjang.',
            'Lancar dan percaya diri. Terus tingkatkan istiqamah mengulang hafalan di rumah.',
            'Alhamdulillah ziyadah baru tuntas. Disarankan tikrar minimal 10 kali sebelum tidur.',
        ];

        $parentNotes = [
            'Alhamdulillah anak kami sudah menyetorkan hafalan di rumah sebelum berangkat.',
            'Sudah dimuroja\'ah bersama orang tua ba\'da maghrib, hafalan semakin lancar.',
            'Anak sangat bersemangat menambah hafalan baru, mohon bimbingan ustadz/ustadzah.',
            'Alhamdulillah hafalan sudah diulang mandiri dan disimak oleh ayah/ibu.',
        ];

        $datesToSeed = [
            ['date' => '2026-07-28', 'day' => 'Selasa', 'sem' => $semGanjil],
            ['date' => '2026-08-19', 'day' => 'Rabu', 'sem' => $semGanjil],
            ['date' => '2026-09-09', 'day' => 'Rabu', 'sem' => $semGanjil],
            ['date' => '2026-10-07', 'day' => 'Rabu', 'sem' => $semGanjil],
            ['date' => '2027-01-20', 'day' => 'Rabu', 'sem' => $semGenap],
            ['date' => '2027-02-17', 'day' => 'Rabu', 'sem' => $semGenap],
            ['date' => '2027-04-14', 'day' => 'Rabu', 'sem' => $semGenap],
        ];

        // Ambil daftar siswa yang belum memiliki catatan tahfizh
        $existingStudentIds = DB::table('tahfizh_daily_logs')->distinct()->pluck('student_id')->flip();

        $tahfizhBatch = [];
        $totalCreated = 0;

        Student::query()
            ->with(['kelas.unitPendidikan'])
            ->chunkById(150, function ($students) use (
                &$tahfizhBatch, &$totalCreated, $existingStudentIds, $teachersByUnit,
                $defaultTeacher, $surahBank, $teacherNotes, $parentNotes, $datesToSeed, $ay
            ) {
                foreach ($students as $student) {
                    // Jika siswa ini belum memiliki log tahfizh, atau memiliki kurang dari 3 log, kita tambahkan
                    $alreadyHasLogs = isset($existingStudentIds[$student->id]);
                    $datesForThisStudent = $alreadyHasLogs ? array_slice($datesToSeed, 4) : $datesToSeed;

                    if (empty($datesForThisStudent)) {
                        continue;
                    }

                    $unit = $student->kelas?->unitPendidikan;
                    $level = strtolower($unit?->level ?? '');
                    $unitTeachers = $teachersByUnit->get($student->unit_id ?? $unit?->id, collect());
                    $teacher = $unitTeachers->isNotEmpty() ? $unitTeachers->random() : $defaultTeacher;

                    // Tentukan bank surah berdasarkan jenjang
                    if (str_contains($level, 'tk') || str_contains($level, 'taud') || str_contains($level, 'paud')) {
                        $bank = $surahBank['tk'];
                        $tilawahText = 'Iqro / SaQu Jilid ' . (abs(crc32($student->id)) % 6 + 1) . ' Hal. ' . (abs(crc32($student->id) >> 2) % 25 + 1);
                    } elseif (str_contains($level, 'sd') || str_contains($level, 'mit')) {
                        $bank = $surahBank['sd'];
                        $tilawahText = 'Al-Qur\'an Juz ' . (abs(crc32($student->id)) % 3 + 28) . ' (Tartil)';
                    } else {
                        $bank = $surahBank['smp_sma'];
                        $tilawahText = 'Al-Qur\'an Juz ' . (abs(crc32($student->id)) % 10 + 1) . ' Maqra 1/2 Juz';
                    }

                    foreach ($datesForThisStudent as $dIdx => $dInfo) {
                        $surahInfo = $bank[($dIdx + abs(crc32($student->id))) % count($bank)];
                        $ayatStart = max(1, ($dIdx * 3) % $surahInfo['ayat_max']);
                        $ayatEnd = min($surahInfo['ayat_max'], $ayatStart + 5);

                        $tahfizhBatch[] = [
                            'id' => (string) Str::uuid(),
                            'academic_year_id' => $ay->id,
                            'semester_id' => $dInfo['sem']->id,
                            'class_id' => $student->class_id ?? $student->kelas_id,
                            'student_id' => $student->id,
                            'teacher_id' => $teacher?->id,
                            'record_date' => $dInfo['date'],
                            'day_name' => $dInfo['day'],
                            'tilawah_text' => $tilawahText,
                            'tilawah_baris' => 15,
                            'hafalan_surah_number' => $surahInfo['no'],
                            'hafalan_surah_name' => $surahInfo['name'],
                            'hafalan_ayah_start' => $ayatStart,
                            'hafalan_ayah_end' => $ayatEnd,
                            'hafalan_baris' => max(5, ($ayatEnd - $ayatStart + 1) * 2),
                            'murajaah_text' => 'QS. ' . $surahInfo['name'] . ': 1 - ' . $ayatStart,
                            'murajaah_lembar' => 1.5,
                            'audio_url' => null,
                            'notes_teacher' => $teacherNotes[($dIdx + abs(crc32($student->full_name))) % count($teacherNotes)],
                            'notes_parent' => $parentNotes[($dIdx + abs(crc32($student->id))) % count($parentNotes)],
                            'signature_teacher' => 'verified_teacher',
                            'signature_parent' => 'verified_parent',
                            'status' => 'approved',
                            'metadata' => json_encode([
                                'source' => 'SimulasiOperasionalLengkap2026',
                                'unit' => $unit?->name ?? 'Dar el-Iman',
                                'nis' => $student->nis,
                                'kelancaran' => 'A',
                                'makhraj' => 'A',
                                'tajwid' => 'A',
                            ]),
                            'murajaah_required' => true,
                            'murajaah_required_surah_number' => $surahInfo['no'],
                            'murajaah_required_surah_name' => $surahInfo['name'],
                            'murajaah_required_ayah_start' => 1,
                            'murajaah_required_ayah_end' => $ayatStart,
                            'murajaah_status' => 'Lancar (Mutqin)',
                            'created_at' => $dInfo['date'] . ' 08:30:00',
                            'updated_at' => $dInfo['date'] . ' 17:00:00',
                        ];
                    }

                    if (count($tahfizhBatch) >= 250) {
                        DB::table('tahfizh_daily_logs')->insert($tahfizhBatch);
                        $totalCreated += count($tahfizhBatch);
                        $tahfizhBatch = [];
                    }
                }
            });

        if (! empty($tahfizhBatch)) {
            DB::table('tahfizh_daily_logs')->insert($tahfizhBatch);
            $totalCreated += count($tahfizhBatch);
        }

        $this->command?->info("✓ Berhasil menambahkan {$totalCreated} setoran tahfizh baru. Seluruh siswa kini memiliki catatan tahfizh!");
    }

    /**
     * 2. Melengkapi Mutaba'ah Yaumiyyah untuk Setiap Siswa
     */
    private function seedCompleteMutabaah(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('2. Memeriksa & melengkapi lembar Mutaba\'ah Yaumiyyah untuk seluruh siswa...');

        $existingMutabaahCount = DB::table('mutabaah_daily_headers')->distinct()->count('student_id');
        $totalStudents = Student::count();
        if ($existingMutabaahCount >= $totalStudents && $totalStudents > 0) {
            $this->command?->info("✓ Seluruh siswa ({$existingMutabaahCount} siswa) sudah memiliki lembar mutaba'ah, melewati pengisian ulang.");
            return;
        }

        $templates = MutabaahTemplate::all()->keyBy('id');
        $defaultTemplate = $templates->first();
        if (! $defaultTemplate) {
            $this->command?->warn('Template mutabaah tidak ditemukan!');
            return;
        }

        // Cache item template
        $itemsByTemplate = MutabaahTemplateItem::all()->groupBy('template_id');

        // Supervisor assignment cache per unit
        $supervisors = MutabaahSupervisorAssignment::all()->keyBy('education_unit_id');
        $defaultSupervisor = MutabaahSupervisorAssignment::first();

        // Tanggal evaluasi mutabaah berkala untuk kedua semester
        $mutabaahDates = [
            // Semester Ganjil
            ['date' => '2026-08-12', 'sem' => $semGanjil, 'desc' => 'Evaluasi Amalan Yaumiyyah Pekan 3'],
            ['date' => '2026-09-16', 'sem' => $semGanjil, 'desc' => 'Evaluasi Amalan Yaumiyyah Pasca PTS Ganjil'],
            ['date' => '2026-10-14', 'sem' => $semGanjil, 'desc' => 'Evaluasi Pembiasaan Sholat & Adab Islami'],
            ['date' => '2026-11-18', 'sem' => $semGanjil, 'desc' => 'Monitoring Kedisiplinan Ibadah Semester 1'],
            // Semester Genap & Ramadhan 1448 H
            ['date' => '2027-01-20', 'sem' => $semGenap, 'desc' => 'Evaluasi Awal Semester Genap'],
            ['date' => '2027-02-15', 'sem' => $semGenap, 'desc' => 'Mutaba\'ah Khusus Ramadhan 1448 H (Pekan 1)'],
            ['date' => '2027-02-28', 'sem' => $semGenap, 'desc' => 'Mutaba\'ah Khusus Ramadhan 1448 H (10 Malam Terakhir)'],
            ['date' => '2027-04-21', 'sem' => $semGenap, 'desc' => 'Evaluasi Pembiasaan Pasca Idul Fitri'],
            ['date' => '2027-05-19', 'sem' => $semGenap, 'desc' => 'Monitoring Akhir Tahun Kenaikan Kelas'],
        ];

        // Cek siswa yang sudah memiliki mutabaah
        $existingMutabaahStudentIds = DB::table('mutabaah_daily_headers')
            ->distinct()
            ->pluck('student_id')
            ->flip();

        $headerBatch = [];
        $detailBatch = [];
        $totalHeaders = 0;

        Student::query()
            ->with(['kelas.unitPendidikan'])
            ->chunkById(100, function ($students) use (
                &$headerBatch, &$detailBatch, &$totalHeaders, $existingMutabaahStudentIds,
                $templates, $itemsByTemplate, $supervisors, $defaultSupervisor, $mutabaahDates, $ay
            ) {
                foreach ($students as $student) {
                    $hasExisting = isset($existingMutabaahStudentIds[$student->id]);
                    // Jika sudah punya (seperti 64 siswa sebelumnya), kita hanya tambahkan tanggal genap yang belum ada
                    $datesForStudent = $hasExisting ? array_slice($mutabaahDates, 4) : $mutabaahDates;

                    if (empty($datesForStudent)) {
                        continue;
                    }

                    $unit = $student->kelas?->unitPendidikan;
                    $level = strtolower($unit?->level ?? '');
                    $isBoarding = str_contains(strtolower($student->kelas?->nama_kelas ?? ''), 'asrama')
                        || str_contains(strtolower($unit?->name ?? ''), 'ponpes')
                        || str_contains(strtolower($unit?->name ?? ''), 'mahad');

                    // Pilih template yang paling selaras
                    $targetTemplate = null;
                    if ($isBoarding) {
                        $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'pesantren') || str_contains(strtolower($t->name), 'putra'));
                    } elseif (str_contains($level, 'tk') || str_contains($level, 'taud')) {
                        $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'tk'));
                    } elseif (str_contains($level, 'sd') || str_contains($level, 'mit')) {
                        $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'sd'));
                    } elseif (str_contains($level, 'smp')) {
                        $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'smp'));
                    } elseif (str_contains($level, 'sma')) {
                        $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'sma'));
                    }
                    $targetTemplate ??= $templates->first();

                    $templateItems = $itemsByTemplate->get($targetTemplate->id, collect());
                    if ($templateItems->isEmpty()) {
                        $templateItems = $itemsByTemplate->first();
                    }

                    $supervisor = $supervisors->get($student->unit_id) ?? $defaultSupervisor;

                    foreach ($datesForStudent as $dIdx => $dInfo) {
                        $headerId = (string) Str::uuid();
                        $actDate = $dInfo['date'];
                        $isRamadhan = str_contains($dInfo['desc'], 'Ramadhan');

                        $score = 88.0 + (abs(crc32($student->id . $actDate)) % 12);
                        $goodCount = max(1, count($templateItems) - 2);
                        $lessCount = 2;
                        $notDoneCount = 0;

                        $headerBatch[] = [
                            'id' => $headerId,
                            'student_id' => $student->id,
                            'template_id' => $targetTemplate->id,
                            'supervisor_assignment_id' => $supervisor?->id,
                            'education_unit_id' => $student->unit_id ?? $unit?->id,
                            'kelas_id' => $student->kelas_id,
                            'rombel_id' => $student->kelas_id,
                            'academic_year_id' => $ay->id,
                            'semester_id' => $dInfo['sem']->id,
                            'activity_date' => $actDate,
                            'status' => 'parent_signed',
                            'total_items' => count($templateItems),
                            'good_count' => $goodCount,
                            'less_count' => $lessCount,
                            'not_done_count' => $notDoneCount,
                            'na_count' => 0,
                            'score' => $score,
                            'supervisor_notes' => $isRamadhan
                                ? 'Alhamdulillah ibadah puasa dan tarawih terlaksana dengan khusyuk. Tilawah 1 juz tuntas.'
                                : 'Pembiasaan sholat fardhu berjamaah dan birrul walidain terlaksana istiqamah.',
                            'finalized_at' => $actDate . ' 21:00:00',
                            'finalized_by' => null,
                            'created_by' => $student->user_id ?? null,
                            'created_at' => $actDate . ' 07:00:00',
                            'updated_at' => $actDate . ' 21:05:00',
                        ];

                        // Buat rincian butir amalan (details)
                        foreach ($templateItems as $itemIdx => $tItem) {
                            $isGood = ($itemIdx < $goodCount);
                            $detailBatch[] = [
                                'id' => (string) Str::uuid(),
                                'daily_header_id' => $headerId,
                                'template_item_id' => $tItem->id,
                                'agenda_item_id' => $tItem->agenda_item_id ?? $tItem->agenda_id,
                                'status_value' => $isGood ? 'good' : 'less',
                                'numeric_value' => $isGood ? 1.0 : 0.5,
                                'text_value' => $isGood ? 'Terlaksana dengan baik' : 'Cukup, perlu ditingkatkan',
                                'notes' => null,
                                'input_by' => $student->user_id ?? null,
                                'input_at' => $actDate . ' 20:30:00',
                                'verification_status' => 'verified',
                                'verified_by' => null,
                                'verified_at' => $actDate . ' 21:00:00',
                                'created_at' => $actDate . ' 20:30:00',
                                'updated_at' => $actDate . ' 21:00:00',
                            ];
                        }
                    }

                    if (count($headerBatch) >= 150) {
                        DB::table('mutabaah_daily_headers')->insert($headerBatch);
                        $totalHeaders += count($headerBatch);
                        $headerBatch = [];

                        foreach (array_chunk($detailBatch, 500) as $chunkDetails) {
                            DB::table('mutabaah_daily_details')->insert($chunkDetails);
                        }
                        $detailBatch = [];
                    }
                }
            });

        if (! empty($headerBatch)) {
            DB::table('mutabaah_daily_headers')->insert($headerBatch);
            $totalHeaders += count($headerBatch);
        }
        if (! empty($detailBatch)) {
            foreach (array_chunk($detailBatch, 500) as $chunkDetails) {
                DB::table('mutabaah_daily_details')->insert($chunkDetails);
            }
        }

        $this->command?->info("✓ Berhasil menambahkan {$totalHeaders} lembar mutaba'ah baru. Seluruh siswa kini memiliki lembar mutaba'ah harian!");
    }

    /**
     * 3. Melengkapi Ujian CBT Semester Genap (PTS Genap & PAT Kenaikan Kelas)
     */
    private function seedSemesterGenapExams(AcademicYear $ay, Semester $semGenap): void
    {
        $this->command?->info('3. Membuat jadwal Ujian CBT Semester Genap (PTS Genap & PAT Kenaikan Kelas)...');

        $existingExamsCount = DB::table('lms_ujian')->where('semester_id', $semGenap->id)->count();
        if ($existingExamsCount > 0) {
            $this->command?->info("✓ Sudah terdapat {$existingExamsCount} ujian CBT untuk Semester Genap, melewati pembuatan ulang.");
            return;
        }

        $kisi = LmsKisiKisi::first();
        if (! $kisi) {
            $kurikulumId = DB::table('master_kurikulum')->value('id');
            $sampleSubj = Subject::first();
            $kisi = LmsKisiKisi::create([
                'id' => (string) Str::uuid(),
                'kurikulum_id' => $kurikulumId,
                'mata_pelajaran_id' => $sampleSubj?->id,
                'judul_kisi' => 'Kisi-kisi Penilaian Akhir Tahun (PAT) Genap 2027',
                'jenis_ujian' => 'PAT',
                'jumlah_soal' => 30,
                'alokasi_waktu_menit' => 60,
                'status' => true,
            ]);
        }

        $kelases = Kelas::whereIn('status', ['Aktif', 'aktif'])->get();
        $teachers = Employee::whereNotNull('unit_id')->get()->keyBy('unit_id');
        $defaultTeacher = Employee::first();

        $ujianGenapBatch = [];
        $sesiBatch = [];

        foreach ($kelases as $kls) {
            $guru = $teachers->get($kls->unit_pendidikan_id) ?? $defaultTeacher;

            // 1. PTS Genap CBT
            $ptsId = (string) Str::uuid();
            $ujianGenapBatch[] = [
                'id' => $ptsId,
                'kisi_kisi_id' => $kisi->id,
                'kelas_id' => $kls->id,
                'semester_id' => $semGenap->id,
                'guru_id' => $guru?->id,
                'judul_ujian' => 'Penilaian Tengah Semester (PTS) Genap CBT 2027 — ' . $kls->nama_kelas,
                'instruksi' => 'Kerjakan butir soal PTS Genap dengan teliti dan jujur. Waktu pengerjaan 60 menit.',
                'waktu_mulai' => '2027-03-15 08:00:00',
                'waktu_selesai' => '2027-03-25 17:00:00',
                'durasi_menit' => 60,
                'acak_soal' => true,
                'acak_jawaban' => true,
                'tampilkan_nilai_langsung' => true,
                'nilai_kkm' => 75.0,
                'max_attempt' => 1,
                'status' => 'aktif',
                'created_at' => '2027-03-10 08:00:00',
                'updated_at' => '2027-03-10 08:00:00',
            ];

            // 2. PAT Kenaikan Kelas CBT
            $patId = (string) Str::uuid();
            $ujianGenapBatch[] = [
                'id' => $patId,
                'kisi_kisi_id' => $kisi->id,
                'kelas_id' => $kls->id,
                'semester_id' => $semGenap->id,
                'guru_id' => $guru?->id,
                'judul_ujian' => 'Penilaian Akhir Tahun (PAT) Kenaikan Kelas CBT 2027 — ' . $kls->nama_kelas,
                'instruksi' => 'Ujian Akhir Semester Kenaikan Kelas. Kerjakan secara mandiri dengan teliti.',
                'waktu_mulai' => '2027-06-07 08:00:00',
                'waktu_selesai' => '2027-06-18 17:00:00',
                'durasi_menit' => 90,
                'acak_soal' => true,
                'acak_jawaban' => true,
                'tampilkan_nilai_langsung' => true,
                'nilai_kkm' => 75.0,
                'max_attempt' => 1,
                'status' => 'aktif',
                'created_at' => '2027-06-01 08:00:00',
                'updated_at' => '2027-06-01 08:00:00',
            ];

            // Buat sesi pengerjaan siswa contoh
            $sampleStudents = Student::where('kelas_id', $kls->id)->take(2)->get();
            foreach ($sampleStudents as $sStd) {
                $sesiBatch[] = [
                    'id' => (string) Str::uuid(),
                    'ujian_id' => $ptsId,
                    'siswa_id' => $sStd->id,
                    'waktu_mulai' => '2027-03-15 08:00:00',
                    'waktu_selesai' => '2027-03-15 08:50:00',
                    'durasi_aktual_detik' => 3000,
                    'jumlah_benar' => 24,
                    'jumlah_salah' => 4,
                    'jumlah_kosong' => 2,
                    'nilai_raw' => 24.0,
                    'nilai_final' => 84.50,
                    'status' => 'selesai',
                    'ip_address' => '127.0.0.1',
                    'created_at' => '2027-03-15 08:00:00',
                    'updated_at' => '2027-03-15 08:50:00',
                ];

                $sesiBatch[] = [
                    'id' => (string) Str::uuid(),
                    'ujian_id' => $patId,
                    'siswa_id' => $sStd->id,
                    'waktu_mulai' => '2027-06-07 08:00:00',
                    'waktu_selesai' => '2027-06-07 09:20:00',
                    'durasi_aktual_detik' => 4800,
                    'jumlah_benar' => 27,
                    'jumlah_salah' => 3,
                    'jumlah_kosong' => 0,
                    'nilai_raw' => 27.0,
                    'nilai_final' => 90.00,
                    'status' => 'selesai',
                    'ip_address' => '127.0.0.1',
                    'created_at' => '2027-06-07 08:00:00',
                    'updated_at' => '2027-06-07 09:20:00',
                ];
            }
        }

        foreach (array_chunk($ujianGenapBatch, 200) as $chunkUjian) {
            DB::table('lms_ujian')->insert($chunkUjian);
        }
        foreach (array_chunk($sesiBatch, 200) as $chunkSesi) {
            DB::table('lms_ujian_sesi')->insert($chunkSesi);
        }

        $this->command?->info('✓ Berhasil menambahkan ' . count($ujianGenapBatch) . ' paket ujian CBT Semester Genap (PTS & PAT) beserta sesinya!');
    }

    /**
     * 4. Melengkapi Penugasan & Kuis LMS Semester Genap (Pekan 17 - 32)
     */
    private function seedSemesterGenapAssignments(AcademicYear $ay, Semester $semGenap): void
    {
        $this->command?->info('4. Menerbitkan Penugasan & Kuis LMS Semester Genap (Pekan 17 s/d 32)...');

        $existingAssignmentsCount = DB::table('lms_penugasan')->where('semester_id', $semGenap->id)->count();
        if ($existingAssignmentsCount > 0) {
            $this->command?->info("✓ Sudah terdapat {$existingAssignmentsCount} penugasan LMS untuk Semester Genap, melewati pembuatan ulang.");
            return;
        }

        $kelases = Kelas::whereIn('status', ['Aktif', 'aktif'])->get();
        $subjects = Subject::where('status', true)->get();
        $teachers = Employee::whereNotNull('unit_id')->get()->keyBy('unit_id');
        $defaultTeacher = Employee::first();

        $topicsGenap = [
            17 => 'Pekan 17: Pengenalan Konsep & Tujuan Pembelajaran Semester Genap',
            18 => 'Pekan 18: Pendalaman Teori & Studi Kasus Lapangan',
            19 => 'Pekan 19: Latihan Formatif & Analisis Problem Solving',
            20 => 'Pekan 20: Tugas Proyek Kolaboratif Kelompok (Fase Perencanaan)',
            21 => 'Pekan 21: Kuis Tengah Semester & Review Pemahaman Konsep',
            22 => 'Pekan 22: Praktikum Mandiri & Observasi Terbimbing',
            23 => 'Pekan 23: Penyusunan Laporan Proyek & Refleksi Pembelajaran',
            24 => 'Pekan 24: Kuis Formatif Persiapan Penilaian Tengah Semester',
            25 => 'Pekan 25: Pengayaan Materi & Diskusi Komprehensif',
            26 => 'Pekan 26: Analisis Data & Penerapan Prinsip Pembelajaran',
            27 => 'Pekan 27: Presentasi Proyek Akhir & Peer Assessment',
            28 => 'Pekan 28: Kuis Pemahaman Lanjutan & Pembahasan Soal HOTS',
            29 => 'Pekan 29: Rangkuman Portofolio Belajar Mandiri',
            30 => 'Pekan 30: Simulasi Tryout & Bedah Kisi-kisi Kenaikan Kelas',
            31 => 'Pekan 31: Muroja\'ah Akbar & Refleksi Adab Penuntut Ilmu',
            32 => 'Pekan 32: Asesmen Komprehensif Akhir Tahun Kenaikan Kelas',
        ];

        $tugasBatch = [];
        $totalTugas = 0;

        foreach ($kelases as $kls) {
            $unitSubjs = $subjects->where('unit_pendidikan_id', $kls->unit_pendidikan_id);
            if ($unitSubjs->isEmpty()) {
                $unitSubjs = $subjects->take(3);
            } else {
                $unitSubjs = $unitSubjs->take(4);
            }

            $guru = $teachers->get($kls->unit_pendidikan_id) ?? $defaultTeacher;

            foreach ($unitSubjs as $subj) {
                // Buat 4 penugasan representatif per mapel di semester genap (Tugas, Kuis, Proyek, Portofolio)
                $assignmentConfigs = [
                    ['pekan' => 18, 'tipe' => 'Individu', 'jenis' => 'tugas', 'bobot' => 15, 'tgl' => '2027-01-25', 'dl' => '2027-02-05'],
                    ['pekan' => 21, 'tipe' => 'individu', 'jenis' => 'latihan', 'bobot' => 15, 'tgl' => '2027-02-15', 'dl' => '2027-02-25'],
                    ['pekan' => 24, 'tipe' => 'kelompok', 'jenis' => 'proyek', 'bobot' => 25, 'tgl' => '2027-03-08', 'dl' => '2027-03-26'],
                    ['pekan' => 30, 'tipe' => 'both', 'jenis' => 'campuran', 'bobot' => 20, 'tgl' => '2027-05-10', 'dl' => '2027-05-25'],
                ];

                foreach ($assignmentConfigs as $cfg) {
                    $judul = sprintf('%s — %s (%s)', $topicsGenap[$cfg['pekan']], $subj->name ?? $subj->nama_mapel, $kls->nama_kelas);

                    $tugasBatch[] = [
                        'id' => (string) Str::uuid(),
                        'mata_pelajaran_id' => $subj->id,
                        'kelas_id' => $kls->id,
                        'guru_id' => $guru?->id,
                        'semester_id' => $semGenap->id,
                        'tahun_ajaran_id' => $ay->id,
                        'modul_ajar_id' => null,
                        'judul_tugas' => $judul,
                        'deskripsi' => 'Tugas pembelajaran semester genap untuk memperdalam pemahaman materi dan capaian pembelajaran kurikulum.',
                        'instruksi' => 'Kerjakan tugas dengan teliti dan unggah tepat waktu sebelum batas deadline.',
                        'tipe_tugas' => $cfg['tipe'],
                        'jenis_tugas' => $cfg['jenis'],
                        'nilai_maksimal' => 100,
                        'bobot_persen' => $cfg['bobot'],
                        'tanggal_mulai' => $cfg['tgl'],
                        'deadline' => $cfg['dl'],
                        'izin_kumpul_terlambat' => true,
                        'is_published' => true,
                        'created_at' => $cfg['tgl'] . ' 08:00:00',
                        'updated_at' => $cfg['tgl'] . ' 08:00:00',
                    ];
                }
            }

            if (count($tugasBatch) >= 200) {
                DB::table('lms_penugasan')->insert($tugasBatch);
                $totalTugas += count($tugasBatch);
                $tugasBatch = [];
            }
        }

        if (! empty($tugasBatch)) {
            DB::table('lms_penugasan')->insert($tugasBatch);
            $totalTugas += count($tugasBatch);
        }

        $this->command?->info("✓ Berhasil menerbitkan {$totalTugas} penugasan & kuis baru di Semester Genap!");
    }

    /**
     * 5. Melengkapi Rekap Nilai Evaluasi Semester Genap (student_grades)
     */
    private function seedSemesterGenapGrades(AcademicYear $ay, Semester $semGenap): void
    {
        $this->command?->info('5. Merekapitulasi nilai akademik Semester Genap (Tugas, Kuis, Proyek, PTS, PAT Kenaikan Kelas)...');

        $existingGradesCount = DB::table('student_grades')->where('semester_id', $semGenap->id)->count();
        if ($existingGradesCount > 0) {
            $this->command?->info("✓ Sudah terdapat {$existingGradesCount} rekap nilai akademik untuk Semester Genap, melewati pembuatan ulang.");
            return;
        }

        $actor = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Operator', 'Tata Usaha', 'Super Admin']))->first()
            ?? User::first();

        $subjects = Subject::where('status', true)->get()->groupBy('unit_pendidikan_id');
        $allSubjects = Subject::where('status', true)->take(4);

        $gradesBatch = [];
        $totalGrades = 0;

        Student::query()
            ->with(['kelas.unitPendidikan'])
            ->chunkById(150, function ($students) use (
                &$gradesBatch, &$totalGrades, $subjects, $allSubjects, $ay, $semGenap, $actor
            ) {
                foreach ($students as $student) {
                    $kelas = $student->kelas;
                    $unit = $kelas?->unitPendidikan;
                    if (! $kelas || ! $unit) {
                        continue;
                    }

                    $unitSubjs = $subjects->get($unit->id);
                    if (! $unitSubjs || $unitSubjs->isEmpty()) {
                        $unitSubjs = $allSubjects;
                    } else {
                        $unitSubjs = $unitSubjs->take(3);
                    }

                    foreach ($unitSubjs as $subj) {
                        $seed = abs(crc32($student->id . '|' . $subj->id . '|' . $ay->id . '|' . $semGenap->id));
                        $sTugas = 74 + ($seed % 22);
                        $sKuis = 72 + (($seed >> 2) % 24);
                        $sProyek = 75 + (($seed >> 4) % 22);
                        $sPts = 73 + (($seed >> 6) % 23);
                        $sPat = 75 + (($seed >> 8) % 22);

                        $finalScore = round($sTugas * 0.20 + $sKuis * 0.15 + $sProyek * 0.25 + $sPts * 0.20 + $sPat * 0.20, 2);
                        $gradeLetter = StudentGrade::getGradeLetter($finalScore);
                        $kkm = (float) ($subj->kkm ?? 75.0);

                        $gradesBatch[] = [
                            'id' => (string) Str::uuid(),
                            'student_id' => $student->id,
                            'subject_id' => $subj->id,
                            'academic_year_id' => $ay->id,
                            'semester_id' => $semGenap->id,
                            'kelas_id' => $kelas->id,
                            'class_id' => $student->class_id,
                            'score_assignment' => $sTugas,
                            'score_quiz' => $sKuis,
                            'score_project' => $sProyek,
                            'score_midterm' => $sPts,
                            'score_final' => $sPat,
                            'final_score' => $finalScore,
                            'grade_letter' => $gradeLetter,
                            'is_passed' => $finalScore >= $kkm,
                            'notes' => 'Menunjukkan pemahaman yang sangat memuaskan dalam menguasai seluruh capaian pembelajaran semester genap.',
                            'created_by' => $actor?->id,
                            'updated_by' => $actor?->id,
                            'metadata' => json_encode([
                                'source' => 'SimulasiOperasionalLengkap2026',
                                'evaluasi' => 'PAT_Kenaikan_Kelas',
                                'formula' => 'kurikulum_merdeka_2026',
                            ]),
                            'created_at' => '2027-06-20 10:00:00',
                            'updated_at' => '2027-06-20 10:00:00',
                        ];
                    }

                    if (count($gradesBatch) >= 250) {
                        DB::table('student_grades')->insert($gradesBatch);
                        $totalGrades += count($gradesBatch);
                        $gradesBatch = [];
                    }
                }
            });

        if (! empty($gradesBatch)) {
            DB::table('student_grades')->insert($gradesBatch);
            $totalGrades += count($gradesBatch);
        }

        $this->command?->info("✓ Berhasil merekapitulasi {$totalGrades} nilai akademik Semester Genap!");
    }

    /**
     * 6. Melengkapi Sesi Pengerjaan Ujian CBT Siswa (lms_ujian_sesi)
     */
    private function seedCompleteExamSessions(): void
    {
        $this->command?->info('6. Mengisikan riwayat sesi pengerjaan ujian CBT siswa (lms_ujian_sesi)...');

        $existingSessionsCount = DB::table('lms_ujian_sesi')->count();
        if ($existingSessionsCount > 100) {
            $this->command?->info("✓ Sudah terdapat {$existingSessionsCount} sesi ujian CBT, melewati pembuatan ulang.");
            return;
        }

        $ujians = DB::table('lms_ujian')->whereNotNull('kelas_id')->get();
        $studentsByClass = Student::whereNotNull('kelas_id')->get()->groupBy('kelas_id');

        $sesiBatch = [];
        $totalSessions = 0;

        foreach ($ujians as $ujian) {
            $classStudents = $studentsByClass->get($ujian->kelas_id, collect());
            if ($classStudents->isEmpty()) {
                continue;
            }

            // Ambil 12 sampai 18 siswa per kelas untuk melakukan pengerjaan ujian CBT
            $participants = $classStudents->take(15);
            $durasiMenit = $ujian->durasi_menit ?? 60;
            $baseStart = $ujian->waktu_mulai ? Carbon::parse($ujian->waktu_mulai) : Carbon::parse('2026-10-05 08:00:00');

            foreach ($participants as $pIdx => $std) {
                $seed = abs(crc32($std->id . '|' . $ujian->id));
                $durasiDetik = ($durasiMenit * 60) - (($seed % 15) * 60) + (($seed >> 3) % 45);
                $waktuMulai = $baseStart->copy()->addMinutes(($pIdx % 3) * 5)->addSeconds($seed % 50);
                $waktuSelesai = $waktuMulai->copy()->addSeconds($durasiDetik);

                $benar = min(30, max(18, 20 + ($seed % 11)));
                $salah = min(30 - $benar, ($seed >> 2) % 6);
                $kosong = 30 - $benar - $salah;
                $nilaiRaw = (float) $benar;
                $nilaiFinal = round(($benar / 30) * 100, 2);

                $sesiBatch[] = [
                    'id' => (string) Str::uuid(),
                    'ujian_id' => $ujian->id,
                    'siswa_id' => $std->id,
                    'waktu_mulai' => $waktuMulai->format('Y-m-d H:i:s'),
                    'waktu_selesai' => $waktuSelesai->format('Y-m-d H:i:s'),
                    'durasi_aktual_detik' => $durasiDetik,
                    'jumlah_benar' => $benar,
                    'jumlah_salah' => $salah,
                    'jumlah_kosong' => $kosong,
                    'nilai_raw' => $nilaiRaw,
                    'nilai_final' => $nilaiFinal,
                    'status' => 'selesai',
                    'ip_address' => '192.168.10.' . (($seed % 240) + 10),
                    'created_at' => $waktuMulai->format('Y-m-d H:i:s'),
                    'updated_at' => $waktuSelesai->format('Y-m-d H:i:s'),
                ];

                if (count($sesiBatch) >= 300) {
                    DB::table('lms_ujian_sesi')->insert($sesiBatch);
                    $totalSessions += count($sesiBatch);
                    $sesiBatch = [];
                }
            }
        }

        if (! empty($sesiBatch)) {
            DB::table('lms_ujian_sesi')->insert($sesiBatch);
            $totalSessions += count($sesiBatch);
        }

        $this->command?->info("✓ Berhasil menambahkan {$totalSessions} riwayat pengerjaan sesi ujian CBT siswa!");
    }
}
