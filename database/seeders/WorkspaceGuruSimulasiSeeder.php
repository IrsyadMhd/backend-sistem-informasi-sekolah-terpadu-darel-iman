<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\LmsMateri;
use App\Models\LmsPenugasan;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\TahfizhRecord;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * WorkspaceGuruSimulasiSeeder
 *
 * Seed data simulasi semua tab Workspace Guru tanpa hardcode:
 *  - Materi     → lms_materi       (10 per guru)
 *  - Penugasan  → lms_penugasan    (8 per guru)
 *  - Catatan    → student_notes    (2 per siswa, maks 15 siswa)
 *  - Tahfizh    → tahfizh_records  (3–6 setoran per siswa)
 *
 * Semua relasi (kelas, siswa, mapel, semester, academic_year)
 * diambil dari database yang sudah ada — tidak ada data statis.
 */
class WorkspaceGuruSimulasiSeeder extends Seeder
{
    private const TIPE_MATERI = ['pdf', 'video', 'teks', 'link'];

    private const JUDUL_MATERI_TPL = [
        'Pengantar Bab {n} — {mapel}',
        'Ringkasan Materi Pekan {n} — {mapel}',
        'Modul Pembelajaran {mapel} Pekan {n}',
        'Rangkuman Penting {mapel} Sesi {n}',
        'Lembar Kerja Siswa {mapel} #{n}',
        'Catatan Kelas {mapel} Pertemuan {n}',
        'Materi Evaluasi {mapel} Pekan {n}',
        'Panduan Belajar Mandiri {mapel} {n}',
        'Slide Presentasi {mapel} Bab {n}',
        'Kisi-Kisi Materi {mapel} Ujian Tengah Semester',
    ];

    private const JUDUL_TUGAS_TPLS = [
        'Tugas Harian {n} — {mapel}',
        'PR Latihan Soal {mapel} #{n}',
        'Evaluasi Mingguan {mapel} Pekan {n}',
        'Ulangan Harian {mapel} Bab {n}',
        'Proyek Mini {mapel} Kelompok {n}',
        'Kuis {mapel} Sesi {n}',
        'Latihan Soal PTS {mapel} #{n}',
        'Remedial {mapel} Pertemuan {n}',
    ];

    private const INSTRUKSI_TUGAS = [
        'Kerjakan soal berikut dengan teliti dan tulis jawaban lengkap.',
        'Jawab pertanyaan di bawah ini secara mandiri dan jujur.',
        'Baca materi terlebih dahulu, lalu kerjakan soal latihan.',
        'Diskusikan dengan kelompok dan presentasikan hasilnya.',
        'Tulis rangkuman materi minggu ini minimal 200 kata.',
    ];

    private const NOTE_CATEGORIES = ['Akademik', 'Perilaku', 'Kedisiplinan', 'Prestasi', 'Ibadah'];
    private const NOTE_PRIORITIES  = ['low', 'medium', 'high'];

    private const NOTE_TITLES = [
        'Perkembangan akademik semester ini',
        'Catatan kedisiplinan di kelas',
        'Prestasi dan potensi siswa',
        'Keaktifan dalam pembelajaran',
        'Evaluasi ibadah dan karakter',
    ];

    private const NOTE_CONTENTS = [
        'Siswa menunjukkan perkembangan positif dalam pemahaman materi.',
        'Perlu peningkatan kedisiplinan dalam mengumpulkan tugas.',
        'Aktif bertanya dan berdiskusi saat proses pembelajaran berlangsung.',
        'Menunjukkan minat besar terhadap mata pelajaran ini.',
        'Perlu pendampingan lebih dalam memahami konsep dasar.',
    ];

    private const SURAH_SIMULASI = [
        ['nomor' => 1,   'nama' => 'Al-Fatihah',  'ayat' => 7,  'juz' => 1],
        ['nomor' => 87,  'nama' => "Al-A'la",     'ayat' => 19, 'juz' => 30],
        ['nomor' => 93,  'nama' => 'Ad-Duha',     'ayat' => 11, 'juz' => 30],
        ['nomor' => 94,  'nama' => 'Al-Insyirah', 'ayat' => 8,  'juz' => 30],
        ['nomor' => 103, 'nama' => 'Al-Asr',      'ayat' => 3,  'juz' => 30],
        ['nomor' => 108, 'nama' => 'Al-Kautsar',  'ayat' => 3,  'juz' => 30],
        ['nomor' => 110, 'nama' => 'An-Nasr',     'ayat' => 3,  'juz' => 30],
        ['nomor' => 112, 'nama' => 'Al-Ikhlas',   'ayat' => 4,  'juz' => 30],
        ['nomor' => 113, 'nama' => 'Al-Falaq',    'ayat' => 5,  'juz' => 30],
        ['nomor' => 114, 'nama' => 'An-Nas',      'ayat' => 6,  'juz' => 30],
        ['nomor' => 78,  'nama' => "An-Naba'",    'ayat' => 40, 'juz' => 30],
    ];

    // ──────────────────────────────────────────────────────────────
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->command?->warn('WorkspaceGuruSimulasiSeeder hanya untuk lokal/development.');
            return;
        }

        $this->command?->info('WorkspaceGuruSimulasiSeeder mulai...');

        $academicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->latest('start_date')->first();

        if (! $academicYear) {
            $this->command?->error('Tidak ada AcademicYear aktif.');
            return;
        }

        $semester = Semester::query()->where('is_active', true)->first()
            ?? Semester::query()->where('academic_year_id', $academicYear->id)->orderBy('sequence')->first();

        foreach (['guru@dareliman.sch.id', 'guru.tahfizh@dareliman.sch.id'] as $email) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) { $this->command?->warn("  User {$email} tidak ditemukan, skip."); continue; }

            $teacher = Teacher::query()->where('user_id', $user->id)->first();
            if (! $teacher) { $this->command?->warn("  Teacher {$email} tidak ditemukan, skip."); continue; }

            $employee = $teacher->employee;
            if (! $employee) { $this->command?->warn("  Employee {$email} tidak ditemukan, skip."); continue; }

            $this->command?->info("  Memproses: {$user->name} ({$email})");

            $schedules = ClassSchedule::query()
                ->where('employee_id', $employee->id)
                ->where('academic_year_id', $academicYear->id)
                ->with(['kelas', 'subject'])
                ->get();

            if ($schedules->isEmpty()) {
                $schedules = ClassSchedule::query()
                    ->where('academic_year_id', $academicYear->id)
                    ->with(['kelas', 'subject'])
                    ->take(30)->get();
            }

            $uniqueKelasIds   = $schedules->pluck('kelas_id')->unique()->values();
            $uniqueSubjectIds = $schedules->pluck('subject_id')->filter()->unique()->values();

            if ($uniqueKelasIds->isEmpty()) {
                $this->command?->warn("  Tidak ada kelas untuk {$email}, skip.");
                continue;
            }

            $firstKelasId  = $uniqueKelasIds->first();
            $firstSubjectId = $uniqueSubjectIds->first();
            $firstSubject   = $firstSubjectId ? Subject::find($firstSubjectId) : null;

            DB::transaction(function () use (
                $teacher, $employee, $academicYear, $semester,
                $uniqueKelasIds, $uniqueSubjectIds,
                $firstKelasId, $firstSubject, $schedules
            ) {
                $this->seedMateri(
                    $teacher, $firstKelasId, $firstSubject,
                    $academicYear, $semester, $uniqueSubjectIds
                );
                $this->seedPenugasan(
                    $teacher, $firstKelasId, $firstSubject,
                    $academicYear, $semester, $uniqueKelasIds, $uniqueSubjectIds
                );
                $this->seedCatatanSiswa($teacher, $firstKelasId, $academicYear, $semester);
                $this->seedTahfizh($teacher, $employee, $uniqueKelasIds, $academicYear, $semester);
            });

            $this->command?->info("  Selesai: {$user->name}");
        }

        $this->command?->info('WorkspaceGuruSimulasiSeeder selesai!');
    }

    // ── Tab Materi ─────────────────────────────────────────────

    private function seedMateri(
        Teacher $teacher,
        $kelasId,
        ?Subject $subject,
        AcademicYear $ay,
        ?Semester $semester,
        $subjectIds
    ): void {
        $mapelName = $subject?->name ?? $subject?->nama_mapel ?? 'Umum';
        $today = Carbon::now();
        $count = 0;

        // Cari modul ajar terkait kelas ini jika ada
        $modulAjarId = DB::table('lms_modul_ajar')->where('kelas_id', $kelasId)->value('id');

        for ($i = 1; $i <= 10; $i++) {
            $judulTpl = self::JUDUL_MATERI_TPL[($i - 1) % count(self::JUDUL_MATERI_TPL)];
            $judul    = str_replace(['{n}', '{mapel}'], [$i, $mapelName], $judulTpl);
            $tipe     = self::TIPE_MATERI[($i - 1) % count(self::TIPE_MATERI)];
            $publishDate = $today->copy()->subWeeks(10 - $i)->startOfWeek();
            $subjectIdThis = $subjectIds->get(($i - 1) % max($subjectIds->count(), 1));

            if (LmsMateri::query()->where('guru_id', $teacher->employee_id)->where('judul', $judul)->exists()) {
                continue;
            }

            $ringkasan = "Materi {$mapelName} pekan ke-{$i} semester {$ay->name}.";

            LmsMateri::query()->create([
                'modul_ajar_id'     => $modulAjarId,
                'mata_pelajaran_id' => $subjectIdThis,
                'guru_id'           => $teacher->employee_id,
                'judul'             => $judul,
                'tipe_materi'       => $tipe,
                'tipe'              => $tipe,
                'konten'            => $ringkasan,
                'isi'               => $ringkasan,
                'urutan'            => $i,
                'status'            => 'aktif',
                'is_published'      => $i <= 8,
                'tanggal_publish'   => $i <= 8 ? $publishDate->toDateTimeString() : null,
                'catatan'           => $i > 8 ? 'Draft — belum dipublikasikan' : null,
                'created_by'        => $teacher->user_id,
            ]);
            $count++;
        }

        $this->command?->line("    Materi: {$count} di-seed");
    }

    // ── Tab Penugasan ─────────────────────────────────────────

    private function seedPenugasan(
        Teacher $teacher,
        $kelasId,
        ?Subject $subject,
        AcademicYear $ay,
        ?Semester $semester,
        $kelasIds,
        $subjectIds
    ): void {
        $mapelName = $subject?->name ?? $subject?->nama_mapel ?? 'Umum';
        $today = Carbon::now();
        $count = 0;

        for ($i = 1; $i <= 8; $i++) {
            $judulTpl   = self::JUDUL_TUGAS_TPLS[($i - 1) % count(self::JUDUL_TUGAS_TPLS)];
            $judul      = str_replace(['{n}', '{mapel}'], [$i, $mapelName], $judulTpl);
            $instruksi  = self::INSTRUKSI_TUGAS[($i - 1) % count(self::INSTRUKSI_TUGAS)];
            $deadline   = $today->copy()->addDays(($i - 4) * 7)->toDateString();
            $subjectIdThis = $subjectIds->get(($i - 1) % max($subjectIds->count(), 1));
            $kelasIdThis   = $kelasIds->get(($i - 1) % max($kelasIds->count(), 1));
            $jenis = ['essay', 'objektif', 'campuran'][($i - 1) % 3];

            if (LmsPenugasan::query()->where('guru_id', $teacher->employee_id)->where('judul_tugas', $judul)->exists()) {
                continue;
            }

            LmsPenugasan::query()->create([
                'mata_pelajaran_id' => $subjectIdThis,
                'kelas_id'          => $kelasIdThis,
                'guru_id'           => $teacher->employee_id,
                'semester_id'       => $semester?->id,
                'tahun_ajaran_id'   => $ay->id,
                'judul_tugas'       => $judul,
                'instruksi'         => $instruksi,
                'deskripsi'         => "Deskripsi tugas {$mapelName} pekan ke-{$i}.",
                'tipe_tugas'        => 'both',
                'jenis_tugas'       => $jenis,
                'deadline'          => $deadline,
                'bobot'             => [100, 80, 50][($i - 1) % 3],
                'is_published'      => true,
                'status'            => 'aktif',
                'created_by'        => $teacher->user_id,
            ]);
            $count++;
        }

        $this->command?->line("    Penugasan: {$count} di-seed");
    }

    // ── Tab Catatan Siswa ─────────────────────────────────────

    private function seedCatatanSiswa(
        Teacher $teacher,
        $kelasId,
        AcademicYear $ay,
        ?Semester $semester
    ): void {
        $students = Student::query()->where('kelas_id', $kelasId)->take(15)->get();
        if ($students->isEmpty()) { $this->command?->line("    Catatan: tidak ada siswa di kelas ini."); return; }

        $today = Carbon::now();
        $count = 0;

        foreach ($students as $idx => $student) {
            for ($n = 0; $n < 2; $n++) {
                $category = self::NOTE_CATEGORIES[($idx + $n) % count(self::NOTE_CATEGORIES)];
                $priority = self::NOTE_PRIORITIES[($idx + $n) % count(self::NOTE_PRIORITIES)];
                $title    = self::NOTE_TITLES[($idx + $n) % count(self::NOTE_TITLES)];
                $noteText = self::NOTE_CONTENTS[($idx + $n) % count(self::NOTE_CONTENTS)];
                $noteDate = $today->copy()->subDays(($idx * 3) + ($n * 7))->toDateString();

                if (StudentNote::query()
                    ->where('student_id', $student->id)
                    ->where('teacher_id', $teacher->id)
                    ->whereDate('date', $noteDate)
                    ->exists()) {
                    continue;
                }

                StudentNote::query()->create([
                    'student_id'        => $student->id,
                    'teacher_id'        => $teacher->id,
                    'education_unit_id' => $student->education_unit_id ?? null,
                    'academic_year_id'  => $ay->id,
                    'semester_id'       => $semester?->id,
                    'date'              => $noteDate,
                    'note'              => $noteText,
                    'metadata'          => json_encode([
                        'source'   => 'WorkspaceGuruSimulasiSeeder',
                        'title'    => $title,
                        'category' => $category,
                        'priority' => $priority,
                    ]),
                ]);
                $count++;
            }
        }

        $this->command?->line("    Catatan Siswa: {$count} di-seed ({$students->count()} siswa)");
    }

    // ── Tab Tahfizh ───────────────────────────────────────────

    private function seedTahfizh(
        Teacher $teacher,
        $employee,
        $kelasIds,
        AcademicYear $ay,
        ?Semester $semester
    ): void {
        $today = Carbon::now();
        $totalRecords = 0;

        foreach ($kelasIds->take(2) as $kelasId) {
            $students = Student::query()->where('kelas_id', $kelasId)->take(20)->get();
            if ($students->isEmpty()) continue;

            foreach ($students as $sIdx => $student) {
                $numRecords = ($sIdx % 4) + 3; // 3–6 setoran per siswa
                $surahPool  = self::SURAH_SIMULASI;

                for ($r = 0; $r < $numRecords; $r++) {
                    $surah       = $surahPool[($sIdx + $r) % count($surahPool)];
                    $depositDate = $today->copy()->subDays(($r * 14) + ($sIdx % 7))->toDateString();
                    $ayahStart   = 1;
                    $ayahEnd     = min($surah['ayat'], (($r + 2) % 5) + 2);

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
                        'semester_id'      => $semester?->id,
                        'deposit_date'     => $depositDate,
                        'student_id'       => $student->id,
                        'class_id'         => $kelasId,
                        'teacher_id'       => $teacher->id,
                        'employee_id'      => $employee->id,
                        'surah_name'       => $surah['nama'],
                        'ayah_start'       => $ayahStart,
                        'ayah_end'         => $ayahEnd,
                        'line_count'       => $ayahEnd - $ayahStart + 1,
                        'status'           => 'approved',
                        'notes'            => null,
                        'metadata'         => json_encode([
                            'source'       => 'WorkspaceGuruSimulasiSeeder',
                            'surah_number' => $surah['nomor'],
                            'juz'          => $surah['juz'],
                            'type'         => $r % 2 === 0 ? 'Ziyadah' : 'Murojaah',
                        ]),
                    ]);
                    $totalRecords++;
                }
            }
        }

        $this->command?->line("    Tahfizh: {$totalRecords} setoran di-seed");
    }
}
