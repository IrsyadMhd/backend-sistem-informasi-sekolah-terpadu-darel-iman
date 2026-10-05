<?php

namespace App\Console\Commands;

use App\Models\ClassSchedule;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveScheduleConflictsCommand extends Command
{
    protected $signature = 'schedule:resolve-conflicts {--dry-run : Only show what will be resolved without changing data}';
    protected $description = 'Audit, clean up, and resolve all schedule conflicts (teacher, class, room, duplicate) in the database';

    public function handle(): int
    {
        $this->info("=======================================================");
        $this->info("   RESOLVING SCHEDULE CONFLICTS (T.P. 2026/2027)       ");
        $this->info("=======================================================\n");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("MODUS DRY-RUN: Data tidak akan diubah di database.\n");
        }

        DB::beginTransaction();
        try {
            // ── LANGKAH 1: SELESAIKAN DUPLIKAT PERSIS ──
            $this->info("1. Mengatasi Duplikat Persis (Exact Duplicate Schedules)...");
            $dupes = DB::select("
                SELECT 
                    kelas_id, day_of_week, time_start, academic_year_id, semester_id,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    count(*) as total
                FROM class_schedules
                GROUP BY kelas_id, day_of_week, time_start, academic_year_id, semester_id
                HAVING count(*) > 1
            ");

            $exactDupesRemoved = 0;
            foreach ($dupes as $d) {
                // Parse postgres array string "{id1,id2}"
                $rawIds = trim($d->ids, '{}');
                $ids = explode(',', $rawIds);
                $survivorId = $ids[0];
                $toRemove = array_slice($ids, 1);

                foreach ($toRemove as $remId) {
                    $this->relinkForeignKeys($remId, $survivorId);
                        DB::table('class_schedules')->where('id', $remId)->delete();
                    $exactDupesRemoved++;
                }
            }
            $this->info("   ✓ Berhasil membersihkan {$exactDupesRemoved} jadwal duplikat persis.\n");

            // ── LANGKAH 2: BERSIHKAN JADWAL BLANKET SDIT KELAS 1 MAKKAH ──
            // Roster resmi SDIT 19-slot (RosterResmiSDIT_FullDay) adalah roster resmi.
            // Jadwal blanket dari JadwalPelajaranSeeder pada kelas ini bertabrakan dan harus dihapus.
            $this->info("2. Mengatasi Bentrok SDIT Kelas 1 Makkah (Full Day)...");
            $sditRombel = Kelas::where('nama_kelas', 'like', '%1 Makkah%')
                ->orWhere('kode_kelas', 'SD2-1A-MAKKAH')
                ->first();

            $sditRemoved = 0;
            if ($sditRombel) {
                $redundantSdit = ClassSchedule::where('kelas_id', $sditRombel->id)
                    ->where('metadata->source', 'JadwalPelajaranSeeder')
                    ->get();

                foreach ($redundantSdit as $sched) {
                    $survivor = ClassSchedule::where('kelas_id', $sditRombel->id)
                        ->where('day_of_week', $sched->day_of_week)
                        ->where('metadata->source', 'RosterResmiSDIT_FullDay')
                        ->orderByRaw("ABS(EXTRACT(EPOCH FROM (time_start::time - '{$sched->time_start}'::time))) ASC")
                        ->first();

                    if ($survivor) {
                        $this->relinkForeignKeys($sched->id, $survivor->id);
                    }
                    $sched->forceDelete();
                    $sditRemoved++;
                }
            }
            $this->info("   ✓ Berhasil membersihkan {$sditRemoved} jadwal blanket bentrok di Kelas 1 Makkah.\n");

            // ── LANGKAH 2B: REASSIGN GURU SDIT KELAS 1 MAKKAH (USTZH AISYAH HUMAIRA → GURU SDIT) ──
            // Ustzh Aisyah Humaira adalah guru tetap TK. Pada roster SDIT Kelas 1 Makkah, tugaskan guru SDIT.
            if ($sditRombel) {
                $aisyahTkId = '750eb7a0-ca89-4e5f-950d-7806f0a785cb';
                $khadijahSdit = Employee::where('nama_lengkap', 'like', '%Khadijah Azzahra%')
                    ->where('unit_id', $sditRombel->unit_pendidikan_id)
                    ->first()
                    ?? Employee::where('unit_id', $sditRombel->unit_pendidikan_id)
                        ->where('jenis_kelamin', 'P')
                        ->where('status', 'Aktif')
                        ->first();

                if ($khadijahSdit) {
                    $reassignedCount = ClassSchedule::where('kelas_id', $sditRombel->id)
                        ->where('employee_id', $aisyahTkId)
                        ->update(['employee_id' => $khadijahSdit->id]);
                    $this->info("   ✓ Dialihkan {$reassignedCount} slot SDIT dari Ustzh Aisyah (TK) ke {$khadijahSdit->nama_lengkap} (SDIT).\n");
                }
            }

            // ── LANGKAH 3: BERSIHKAN JADWAL BLANKET SMA DIBS (10A, 11A, 12A) ──
            // Roster resmi SMA DIBS adalah RosterResmiDIBS_2026.
            // Jadwal generic JadwalPelajaranSeeder pada rombel SMA DIBS bertabrakan dan harus dihapus.
            $this->info("3. Mengatasi Bentrok SMA DIBS (RosterResmiDIBS_2026)...");
            $smaRombels = ClassSchedule::where('metadata->source', 'RosterResmiDIBS_2026')
                ->whereNull('deleted_at')
                ->distinct('kelas_id')
                ->pluck('kelas_id');

            $smaRemoved = 0;
            if ($smaRombels->isNotEmpty()) {
                $redundantSma = ClassSchedule::whereIn('kelas_id', $smaRombels)
                    ->where('metadata->source', 'JadwalPelajaranSeeder')
                    ->get();

                foreach ($redundantSma as $sched) {
                    $survivor = ClassSchedule::where('kelas_id', $sched->kelas_id)
                        ->where('day_of_week', $sched->day_of_week)
                        ->where('metadata->source', 'RosterResmiDIBS_2026')
                        ->orderByRaw("ABS(EXTRACT(EPOCH FROM (time_start::time - '{$sched->time_start}'::time))) ASC")
                        ->first();

                    $this->relinkForeignKeys($sched->id, $survivor?->id);
                    $sched->forceDelete();
                    $smaRemoved++;
                }
            }
            $this->info("   ✓ Berhasil membersihkan {$smaRemoved} jadwal blanket bentrok di SMA DIBS.\n");

            // ── LANGKAH 4: BERSIHKAN JADWAL STRAY TEST (PresensiPembelajaranSeeder) ──
            $this->info("4. Mengatasi Jadwal Stray Test (PresensiPembelajaranSeeder)...");
            $stray = ClassSchedule::where('metadata->source', 'PresensiPembelajaranSeeder')->get();
            $strayCount = 0;
            foreach ($stray as $s) {
                $survivor = ClassSchedule::where('kelas_id', $s->kelas_id)
                    ->where('day_of_week', $s->day_of_week)
                    ->where('id', '!=', $s->id)
                    ->first();
                $this->relinkForeignKeys($s->id, $survivor?->id);
                $s->forceDelete();
                $strayCount++;
            }
            $this->info("   ✓ Berhasil membersihkan {$strayCount} jadwal stray testing.\n");

            // ── LANGKAH 5: REKONSILIASI JADWAL GURU TEST (GuruTestWorkspaceSeeder) ──
            // Guru Test harus menjadi pengampu pada slot unik tanpa menumpuk jadwal tandingan di kelas yang sama
            $this->info("5. Merekonsiliasi Jadwal Guru Test (GuruTestWorkspaceSeeder)...");
            $guruTestUser = DB::table('users')->where('email', 'guru@dareliman.sch.id')->first();
            $guruTestEmployee = $guruTestUser ? DB::table('employees')->where('user_id', $guruTestUser->id)->first() : null;

            if ($guruTestEmployee) {
                // Hapus jadwal blanket JadwalPelajaranSeeder yang menugaskan Guru Test di luar GuruTestWorkspaceSeeder
                $redundantGt = ClassSchedule::where('employee_id', $guruTestEmployee->id)
                    ->where('metadata->source', 'JadwalPelajaranSeeder')
                    ->get();
                foreach ($redundantGt as $rGt) {
                    $survivor = ClassSchedule::where('employee_id', $guruTestEmployee->id)
                        ->where('metadata->source', 'GuruTestWorkspaceSeeder')
                        ->where('day_of_week', $rGt->day_of_week)
                        ->first();
                    $this->relinkForeignKeys($rGt->id, $survivor?->id);
                    $rGt->forceDelete();
                }

                // Cari jadwal lain di kelas & waktu yang sama dengan jadwal resmi Guru Test
                $gtSchedules = ClassSchedule::where('employee_id', $guruTestEmployee->id)
                    ->where('metadata->source', 'GuruTestWorkspaceSeeder')
                    ->get();

                foreach ($gtSchedules as $gt) {
                    $conflicting = ClassSchedule::where('kelas_id', $gt->kelas_id)
                        ->where('day_of_week', $gt->day_of_week)
                        ->where('academic_year_id', $gt->academic_year_id)
                        ->where('semester_id', $gt->semester_id)
                        ->where('id', '!=', $gt->id)
                        ->where('time_start', '<', $gt->time_end)
                        ->where('time_end', '>', $gt->time_start)
                        ->get();

                    foreach ($conflicting as $c) {
                        $this->relinkForeignKeys($c->id, $gt->id);
                        $c->forceDelete();
                    }
                }
            }
            $this->info("   ✓ Jadwal Guru Test berhasil direkonsiliasi.\n");

            // ── LANGKAH 6: REKONSILIASI SISA BENTROK GURU & KELAS TERSISA ──
            $this->info("6. Melakukan scanning & resolusi akhir bentrok guru & kelas tersisa...");
            
            // Loop resolusi bentrok kelas (jika masih ada 2 jadwal di kelas & jam sama)
            $remainingClassClashes = DB::select("
                SELECT s1.id as id1, s2.id as id2, s1.kelas_id
                FROM class_schedules s1
                JOIN class_schedules s2 ON s1.id < s2.id
                    AND s1.day_of_week = s2.day_of_week
                    AND s1.academic_year_id = s2.academic_year_id
                    AND s1.semester_id = s2.semester_id
                    AND s1.kelas_id = s2.kelas_id
                    AND s1.time_start < s2.time_end
                    AND s1.time_end > s2.time_start
                    AND s1.deleted_at IS NULL
                    AND s2.deleted_at IS NULL
            ");

            $resolvedClass = 0;
            foreach ($remainingClassClashes as $c) {
                $sched1 = ClassSchedule::find($c->id1);
                $sched2 = ClassSchedule::find($c->id2);
                if (! $sched1 || ! $sched2) continue;

                $attCount1 = DB::table('lms_presensi')->where('jadwal_pelajaran_id', $sched1->id)->count();
                $attCount2 = DB::table('lms_presensi')->where('jadwal_pelajaran_id', $sched2->id)->count();

                if ($attCount1 >= $attCount2) {
                    $this->relinkForeignKeys($sched2->id, $sched1->id);
                    $sched2->forceDelete();
                } else {
                    $this->relinkForeignKeys($sched1->id, $sched2->id);
                    $sched1->forceDelete();
                }
                $resolvedClass++;
            }
            $this->info("   ✓ Menyelesaikan {$resolvedClass} sisa bentrok kelas.\n");

            // Loop resolusi bentrok guru (jika guru yang sama masih ada di 2 kelas pada jam sama)
            $remainingTeacherClashes = DB::select("
                SELECT s1.id as id1, s2.id as id2, s1.employee_id, s1.day_of_week, s1.time_start, s1.time_end, s2.kelas_id as kelas2_id
                FROM class_schedules s1
                JOIN class_schedules s2 ON s1.id < s2.id
                    AND s1.day_of_week = s2.day_of_week
                    AND s1.academic_year_id = s2.academic_year_id
                    AND s1.semester_id = s2.semester_id
                    AND s1.employee_id = s2.employee_id
                    AND s1.kelas_id != s2.kelas_id
                    AND s1.time_start < s2.time_end
                    AND s1.time_end > s2.time_start
                    AND s1.deleted_at IS NULL
                    AND s2.deleted_at IS NULL
            ");

            $resolvedTeachers = 0;
            foreach ($remainingTeacherClashes as $tc) {
                $sched2 = ClassSchedule::find($tc->id2);
                if (! $sched2) continue;

                $kelas2 = Kelas::find($sched2->kelas_id);
                if (! $kelas2) continue;

                $occupiedTeacherIds = ClassSchedule::where('day_of_week', $sched2->day_of_week)
                    ->where('academic_year_id', $sched2->academic_year_id)
                    ->where('semester_id', $sched2->semester_id)
                    ->where('time_start', '<', $sched2->time_end)
                    ->where('time_end', '>', $sched2->time_start)
                    ->whereNotNull('employee_id')
                    ->whereNull('deleted_at')
                    ->pluck('employee_id');

                $freeTeacher = Employee::where('unit_id', $kelas2->unit_pendidikan_id)
                    ->where('status', 'Aktif')
                    ->whereNotIn('id', $occupiedTeacherIds)
                    ->first();

                if (! $freeTeacher) {
                    $freeTeacher = Employee::where('unit_id', $kelas2->unit_pendidikan_id)
                        ->where('status', 'Aktif')
                        ->first();
                }

                if ($freeTeacher) {
                    $sched2->update(['employee_id' => $freeTeacher->id]);
                    $resolvedTeachers++;
                }
            }
            $this->info("   ✓ Menugaskan kembali {$resolvedTeachers} jadwal guru agar bebas bentrok.\n");

            // ── VERIFIKASI AKHIR SEBELUM COMMIT / ROLLBACK ──
            $finalTeacherClashes = DB::select("
                SELECT count(*) as count
                FROM class_schedules s1
                JOIN class_schedules s2 ON s1.id < s2.id
                    AND s1.day_of_week = s2.day_of_week
                    AND s1.academic_year_id = s2.academic_year_id
                    AND s1.semester_id = s2.semester_id
                    AND s1.employee_id = s2.employee_id
                    AND s1.time_start < s2.time_end
                    AND s1.time_end > s2.time_start
                    AND s1.deleted_at IS NULL
                    AND s2.deleted_at IS NULL
            ")[0]->count;

            $finalClassClashes = DB::select("
                SELECT count(*) as count
                FROM class_schedules s1
                JOIN class_schedules s2 ON s1.id < s2.id
                    AND s1.day_of_week = s2.day_of_week
                    AND s1.academic_year_id = s2.academic_year_id
                    AND s1.semester_id = s2.semester_id
                    AND s1.kelas_id = s2.kelas_id
                    AND s1.time_start < s2.time_end
                    AND s1.time_end > s2.time_start
                    AND s1.deleted_at IS NULL
                    AND s2.deleted_at IS NULL
            ")[0]->count;

            if (! $isDryRun) {
                DB::commit();
                $this->info("✅ Semua transaksi perubahan jadwal berhasil di-commit ke database.");
            } else {
                DB::rollBack();
                $this->info("ℹ Transaksi simulasi berhasil. Dilakukan ROLLBACK (database tetap aman & tidak berubah).");
            }

            $this->info("\n=======================================================");
            $this->info("   HASIL VERIFIKASI AKHIR:                             ");
            $this->info("   - Bentrok Guru  : {$finalTeacherClashes} bentrok");
            $this->info("   - Bentrok Kelas : {$finalClassClashes} bentrok");
            $this->info("=======================================================");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Gagal menyelesaikan bentrok jadwal: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function relinkForeignKeys(string $oldId, ?string $newId = null): void
    {
        if (! $newId) {
            // Jika tidak ada jadwal pengganti, bersihkan relasi agar tidak ada foreign key violation
            DB::table('lms_presensi')->where('jadwal_pelajaran_id', $oldId)->delete();
            DB::table('lesson_attendance_sessions')->where('schedule_id', $oldId)->delete();
            DB::table('teaching_attendances')->where('schedule_id', $oldId)->delete();
            DB::table('attendance_scan_logs')->where('class_schedule_id', $oldId)->delete();
            return;
        }

        // 1. lms_presensi: Hapus record siswa & tanggal pada oldId yang sudah ada di newId agar tidak melanggar unique constraint
        DB::statement("
            DELETE FROM lms_presensi 
            WHERE jadwal_pelajaran_id = '{$oldId}'
              AND EXISTS (
                  SELECT 1 FROM lms_presensi existing 
                  WHERE existing.jadwal_pelajaran_id = '{$newId}'
                    AND existing.siswa_id = lms_presensi.siswa_id
                    AND existing.tanggal = lms_presensi.tanggal
              )
        ");
        DB::table('lms_presensi')->where('jadwal_pelajaran_id', $oldId)->update(['jadwal_pelajaran_id' => $newId]);

        // 2. lesson_attendance_sessions
        DB::statement("
            DELETE FROM lesson_attendance_sessions
            WHERE schedule_id = '{$oldId}'
              AND EXISTS (
                  SELECT 1 FROM lesson_attendance_sessions existing
                  WHERE existing.schedule_id = '{$newId}'
                    AND existing.attendance_date = lesson_attendance_sessions.attendance_date
              )
        ");
        DB::table('lesson_attendance_sessions')->where('schedule_id', $oldId)->update(['schedule_id' => $newId]);

        // 3. teaching_attendances
        DB::statement("
            DELETE FROM teaching_attendances
            WHERE schedule_id = '{$oldId}'
              AND EXISTS (
                  SELECT 1 FROM teaching_attendances existing
                  WHERE existing.schedule_id = '{$newId}'
                    AND existing.attendance_date = teaching_attendances.attendance_date
              )
        ");
        DB::table('teaching_attendances')->where('schedule_id', $oldId)->update(['schedule_id' => $newId]);

        // 4. attendance_scan_logs
        DB::table('attendance_scan_logs')->where('class_schedule_id', $oldId)->update(['class_schedule_id' => $newId]);
    }
}
