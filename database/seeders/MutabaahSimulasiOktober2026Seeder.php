<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahParentSignature;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MutabaahSimulasiOktober2026Seeder extends Seeder
{
    /**
     * Run the database seeds.
     * Mengisi data mutaba'ah harian untuk seluruh siswa di seluruh unit pendidikan
     * dari awal semester ganjil (13 Juli 2026) hingga 17 Oktober 2026 secara dinamis tanpa hardcode.
     */
    public function run(): void
    {
        $this->command?->info('========================================================================');
        $this->command?->info('  SEEKER MUTABA\'AH YAUMIYYAH TERPADU - HINGGA 17 OKTOBER 2026');
        $this->command?->info('========================================================================');

        // 1. Ambil Tahun Ajaran Aktif & Semester Ganjil secara dinamis
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::latest('start_date')->first();

        if (! $academicYear) {
            $this->command?->error('Tahun ajaran tidak ditemukan!');
            return;
        }

        $semesterGanjil = Semester::where('academic_year_id', $academicYear->id)
            ->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere('name', 'ilike', '%ganjil%')
                    ->orWhere('sequence', 1);
            })
            ->first() ?? Semester::first();

        if (! $semesterGanjil) {
            $this->command?->error('Semester ganjil tidak ditemukan!');
            return;
        }

        $this->command?->info("✓ Tahun Ajaran: {$academicYear->name} (ID: {$academicYear->id})");
        $this->command?->info("✓ Semester: {$semesterGanjil->name} (ID: {$semesterGanjil->id})");

        // 2. Tentukan Rentang Tanggal Pengisian (13 Juli 2026 hingga 17 Oktober 2026)
        $startDate = Carbon::create(2026, 7, 13)->startOfDay();
        $targetEndDate = Carbon::create(2026, 10, 17)->startOfDay();

        // Kumpulkan daftar tanggal hari sekolah (Senin–Jumat) + Sabtu 17 Oktober 2026
        $targetDates = [];
        $curr = $startDate->copy();
        while ($curr->lte($targetEndDate)) {
            if ($curr->isWeekday() || $curr->isSameDay($targetEndDate)) {
                $targetDates[] = $curr->toDateString();
            }
            $curr->addDay();
        }

        $totalDays = count($targetDates);
        $this->command?->info("✓ Rentang Tanggal: {$targetDates[0]} s/d {$targetDates[$totalDays - 1]} ({$totalDays} hari aktivitas)");

        // 3. Cache Seluruh Template Mutabaah & Item-Item Aktif
        $templates = MutabaahTemplate::all();
        if ($templates->isEmpty()) {
            $this->command?->error('Tidak ada MutabaahTemplate yang tersedia!');
            return;
        }

        $itemsByTemplate = MutabaahTemplateItem::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('template_id');

        // Fallback user ID untuk referensi created_by / parent signature
        $defaultParentUser = User::whereHas('roles', fn ($r) => $r->where('name', 'ilike', '%wali%'))
            ->value('id')
            ?? User::where('email', 'superadmin@dareliman.sch.id')->value('id')
            ?? User::value('id');

        // 4. Pastikan Setiap Unit Pendidikan Memiliki Supervisor Assignment & Template Selaras
        $units = EducationUnit::all();
        $supervisorsByUnit = [];
        $templateByUnit = [];

        foreach ($units as $unit) {
            $unitName = strtolower($unit->name);
            $level = strtolower($unit->level ?? '');
            $isBoarding = str_contains($unitName, 'ponpes')
                || str_contains($unitName, 'pesantren')
                || str_contains($unitName, 'mahad')
                || str_contains($level, 'ponpes')
                || str_contains($level, 'mahad');

            // Pemilihan template paling selaras secara dinamis
            $targetTemplate = null;
            if ($isBoarding) {
                if (str_contains($unitName, 'putri')) {
                    $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'putri'));
                } else {
                    $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'putra') || str_contains(strtolower($t->name), 'pesantren'));
                }
            } elseif (str_contains($level, 'tk') || str_contains($level, 'taud')) {
                $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'tk'));
            } elseif (str_contains($level, 'sd') || str_contains($level, 'mit')) {
                $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'sd'));
            } elseif (str_contains($level, 'smp')) {
                $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'smp'));
            } elseif (str_contains($level, 'sma')) {
                $targetTemplate = $templates->first(fn ($t) => str_contains(strtolower($t->name), 'sma'));
            }
            $targetTemplate ??= $templates->first(fn ($t) => str_contains(strtolower($t->name), 'terpadu')) ?? $templates->first();
            $templateByUnit[$unit->id] = $targetTemplate;

            // Cari atau buat supervisor assignment aktif untuk unit ini
            $assignment = MutabaahSupervisorAssignment::where('education_unit_id', $unit->id)
                ->where('academic_year_id', $academicYear->id)
                ->where('semester_id', $semesterGanjil->id)
                ->first();

            if (! $assignment) {
                // Ambil pegawai di unit ini, fallback ke pegawai manapun
                $employee = Employee::where('unit_id', $unit->id)->first()
                    ?? Employee::first();

                if ($employee) {
                    $assignment = MutabaahSupervisorAssignment::create([
                        'employee_id' => $employee->id,
                        'supervisor_type' => $isBoarding ? 'musyrif' : 'wali_kelas',
                        'education_unit_id' => $unit->id,
                        'kelas_id' => null,
                        'rombel_id' => null,
                        'template_id' => $targetTemplate->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semesterGanjil->id,
                        'start_date' => $academicYear->start_date ? Carbon::parse($academicYear->start_date)->toDateString() : '2026-07-01',
                        'end_date' => $academicYear->end_date ? Carbon::parse($academicYear->end_date)->toDateString() : '2027-06-30',
                        'is_primary' => true,
                        'can_input' => true,
                        'can_edit' => true,
                        'can_finalize' => true,
                        'can_view_report' => true,
                        'status' => 'active',
                    ]);
                }
            }

            if ($assignment) {
                $supervisorsByUnit[$unit->id] = $assignment;
            }
        }

        $defaultSupervisor = MutabaahSupervisorAssignment::first();
        $this->command?->info("✓ Konfigurasi " . count($supervisorsByUnit) . " supervisor assignment & template unit berhasil dipetakan.");

        // 5. Query Seluruh Siswa Aktif dari Seluruh Unit
        $totalStudents = Student::count();
        $this->command?->info("✓ Memproses data mutaba'ah untuk seluruh {$totalStudents} siswa...");

        $supervisorNotesPool = [
            'Alhamdulillah ibadah harian terlaksana dengan istiqomah dan tertib.',
            'Sholat fardhu berjamaah di masjid dan birrul walidain terjaga sangat baik.',
            'Pembiasaan adab Islami dan tilawah Al-Qur\'an berjalan lancar.',
            'Kedisiplinan ibadah mandiri sangat memuaskan, terus pertahankan.',
            'Alhamdulillah ananda tertib melaksanakan sholat tepat waktu dan membantu orang tua.',
            'Muroja\'ah hafalan dan amalan yaumiyyah terlaksana dengan khusyuk.',
            'Catatan pekanan: ibadah fardhu tuntas, pembiasaan dzikir pagi petang meningkat.',
        ];

        $parentNotesPool = [
            'Alhamdulillah ananda sholat tepat waktu dan mandiri di rumah.',
            'Ananda rajin mengaji dan membantu pekerjaan orang tua setiap hari.',
            'Ibadah di rumah terlaksana dengan khusyuk, alhamdulillah.',
            'Sudah kami periksa dan bimbing, ibadah hari ini terlaksana tertib.',
            'Alhamdulillah ananda istiqomah dzikir dan sholat berjamaah bersama keluarga.',
        ];

        // Cache existing (student_id + date) untuk mencegah duplikasi
        $existingHeaders = DB::table('mutabaah_daily_headers')
            ->whereBetween('activity_date', [$startDate->toDateString(), $targetEndDate->toDateString()])
            ->select('student_id', 'activity_date')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('activity_date')->flip()->toArray())
            ->toArray();

        $headerBatch = [];
        $detailBatch = [];
        $signatureBatch = [];
        $totalInsertedHeaders = 0;
        $processedStudentCount = 0;

        // Chunking per 50 siswa untuk efisiensi memori
        Student::query()
            ->with(['kelas.unitPendidikan', 'parent'])
            ->chunkById(50, function ($students) use (
                &$headerBatch, &$detailBatch, &$signatureBatch, &$totalInsertedHeaders, &$processedStudentCount,
                $existingHeaders, $targetDates, $academicYear, $semesterGanjil, $supervisorsByUnit, $defaultSupervisor,
                $templateByUnit, $itemsByTemplate, $defaultParentUser, $supervisorNotesPool, $parentNotesPool, $totalStudents
            ) {
                foreach ($students as $student) {
                    $processedStudentCount++;
                    $unitId = $student->unit_id ?? $student->kelas?->unit_pendidikan_id ?? $student->kelas?->unitPendidikan?->id;
                    $targetTemplate = $templateByUnit[$unitId] ?? $itemsByTemplate->keys()->first();
                    $supervisor = $supervisorsByUnit[$unitId] ?? $defaultSupervisor;

                    $templateItems = $itemsByTemplate->get($targetTemplate->id, collect());
                    if ($templateItems->isEmpty()) {
                        $templateItems = $itemsByTemplate->first();
                    }

                    $totalItemsCount = $templateItems->count();
                    $studentParentUserId = $student->parent?->user_id ?? $defaultParentUser;

                    // Cek tanggal yang sudah pernah ada untuk siswa ini
                    $studentExistingDates = $existingHeaders[$student->id] ?? [];

                    foreach ($targetDates as $dIdx => $actDate) {
                        if (isset($studentExistingDates[$actDate])) {
                            continue; // Skip jika tanggal sudah ada
                        }

                        $headerId = (string) Str::uuid();
                        $seedHash = abs(crc32($student->id . $actDate));

                        // Variasi status: 94% parent_signed, 4% finalized, 2% draft
                        $statusRoll = $seedHash % 100;
                        if ($statusRoll < 94) {
                            $status = 'parent_signed';
                        } elseif ($statusRoll < 98) {
                            $status = 'finalized';
                        } else {
                            $status = 'draft';
                        }

                        // Perhitungan score & butir pencapaian
                        $score = 88.0 + ($seedHash % 12); // Range 88.00 - 99.00
                        $lessCount = ($seedHash % 3 == 0) ? 1 : (($seedHash % 7 == 0) ? 2 : 0);
                        $goodCount = max(1, $totalItemsCount - $lessCount);
                        $notDoneCount = max(0, $totalItemsCount - $goodCount - $lessCount);

                        $supNote = $supervisorNotesPool[$seedHash % count($supervisorNotesPool)];
                        $parNote = $parentNotesPool[$seedHash % count($parentNotesPool)];

                        $headerBatch[] = [
                            'id' => $headerId,
                            'student_id' => $student->id,
                            'template_id' => $targetTemplate->id,
                            'supervisor_assignment_id' => $supervisor?->id,
                            'education_unit_id' => $unitId,
                            'kelas_id' => $student->kelas_id,
                            'rombel_id' => $student->kelas_id,
                            'academic_year_id' => $academicYear->id,
                            'semester_id' => $semesterGanjil->id,
                            'activity_date' => $actDate,
                            'status' => $status,
                            'total_items' => $totalItemsCount,
                            'good_count' => $goodCount,
                            'less_count' => $lessCount,
                            'not_done_count' => $notDoneCount,
                            'na_count' => 0,
                            'score' => $score,
                            'supervisor_notes' => $supNote,
                            'finalized_at' => $actDate . ' 21:00:00',
                            'finalized_by' => null,
                            'created_by' => $student->user_id,
                            'updated_by' => $student->user_id,
                            'created_at' => $actDate . ' 07:00:00',
                            'updated_at' => $actDate . ' 21:10:00',
                        ];

                        // Tanda tangan orang tua jika status parent_signed
                        if ($status === 'parent_signed' && $studentParentUserId) {
                            $signatureBatch[] = [
                                'id' => (string) Str::uuid(),
                                'daily_header_id' => $headerId,
                                'parent_user_id' => $studentParentUserId,
                                'signature_status' => 'approved',
                                'comment' => $parNote,
                                'signed_at' => $actDate . ' 21:15:00',
                                'created_at' => $actDate . ' 21:15:00',
                                'updated_at' => $actDate . ' 21:15:00',
                            ];
                        }

                        // Rincian butir amalan (details)
                        // Untuk efisiensi ruang disk & performa:
                        // - Pekan aktif berjalan (Oktober) atau template TK: buat semua butir
                        // - Bulan Juli-September: buat 7 butir amalan fardhu & adab inti
                        $isRecent = ($dIdx >= count($targetDates) - 15) || ($totalItemsCount <= 8);
                        $itemsToProcess = $isRecent ? $templateItems : $templateItems->take(7);

                        foreach ($itemsToProcess as $iIdx => $tItem) {
                            $isGood = ($iIdx < $goodCount);
                            $isLess = ! $isGood && ($iIdx < $goodCount + $lessCount);

                            $statusVal = $isGood ? 'good' : ($isLess ? 'less' : 'not_done');
                            $numVal = $isGood ? 1.0 : ($isLess ? 0.5 : 0.0);
                            $textVal = $isGood ? 'Terlaksana dengan baik' : ($isLess ? 'Cukup, perlu ditingkatkan' : 'Belum terlaksana');

                            $detailBatch[] = [
                                'id' => (string) Str::uuid(),
                                'daily_header_id' => $headerId,
                                'template_item_id' => $tItem->id,
                                'agenda_item_id' => $tItem->agenda_item_id ?? $tItem->agenda_id,
                                'status_value' => $statusVal,
                                'numeric_value' => $numVal,
                                'text_value' => $textVal,
                                'notes' => null,
                                'input_by' => $student->user_id,
                                'input_at' => $actDate . ' 20:30:00',
                                'input_source' => ($iIdx < 3) ? 'teacher' : 'parent',
                                'input_location' => ($iIdx < 3) ? 'school' : 'home',
                                'verification_status' => 'verified',
                                'verified_by' => null,
                                'verified_at' => $actDate . ' 21:00:00',
                                'created_at' => $actDate . ' 20:30:00',
                                'updated_at' => $actDate . ' 21:00:00',
                            ];
                        }
                    }

                    // Flush batch jika mencapai kapasitas optimal
                    if (count($headerBatch) >= 1000) {
                        DB::table('mutabaah_daily_headers')->insertOrIgnore($headerBatch);
                        $totalInsertedHeaders += count($headerBatch);
                        $headerBatch = [];

                        if (! empty($signatureBatch)) {
                            foreach (array_chunk($signatureBatch, 500) as $chunkSig) {
                                DB::table('mutabaah_parent_signatures')->insertOrIgnore($chunkSig);
                            }
                            $signatureBatch = [];
                        }

                        if (! empty($detailBatch)) {
                            foreach (array_chunk($detailBatch, 1000) as $chunkDetails) {
                                DB::table('mutabaah_daily_details')->insertOrIgnore($chunkDetails);
                            }
                            $detailBatch = [];
                        }
                    }
                }

                $this->command?->line("  → Siswa diproses: {$processedStudentCount} / {$totalStudents}...");
            });

        // Flush sisa batch terakhir
        if (! empty($headerBatch)) {
            DB::table('mutabaah_daily_headers')->insertOrIgnore($headerBatch);
            $totalInsertedHeaders += count($headerBatch);
        }
        if (! empty($signatureBatch)) {
            foreach (array_chunk($signatureBatch, 500) as $chunkSig) {
                DB::table('mutabaah_parent_signatures')->insertOrIgnore($chunkSig);
            }
        }
        if (! empty($detailBatch)) {
            foreach (array_chunk($detailBatch, 1000) as $chunkDetails) {
                DB::table('mutabaah_daily_details')->insertOrIgnore($chunkDetails);
            }
        }

        $this->command?->info('========================================================================');
        $this->command?->info("✓ SEEDING MUTABA'AH SELESAI!");
        $this->command?->info("✓ Total Siswa: {$processedStudentCount} siswa di " . count($supervisorsByUnit) . " unit pendidikan.");
        $this->command?->info("✓ Lembar Mutaba'ah Baru: {$totalInsertedHeaders} headers.");
        $this->command?->info("✓ Total Header di Database: " . DB::table('mutabaah_daily_headers')->count() . " rows.");
        $this->command?->info("✓ Total Rincian Amalan (Details): " . DB::table('mutabaah_daily_details')->count() . " rows.");
        $this->command?->info("✓ Total Tanda Tangan Wali: " . DB::table('mutabaah_parent_signatures')->count() . " rows.");
        $this->command?->info('========================================================================');
    }
}
