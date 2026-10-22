<?php

namespace Database\Seeders;

use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\LmsModulAjar;
use App\Models\LmsMateri;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KonsolidasiMasterMapelSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $dupGroups = DB::select("
                SELECT unit_pendidikan_id, LOWER(TRIM(nama_mapel)) as clean_name, count(*) as cnt
                FROM subjects
                WHERE deleted_at IS NULL AND status = true
                GROUP BY unit_pendidikan_id, LOWER(TRIM(nama_mapel))
                HAVING count(*) > 1
            ");

            $this->command->info(sprintf('Ditemukan %d grup mapel duplikat untuk dikonsolidasi.', count($dupGroups)));
            $totalDeactivated = 0;
            $totalReferencesMigrated = 0;

            foreach ($dupGroups as $g) {
                $subjs = Subject::where('unit_pendidikan_id', $g->unit_pendidikan_id)
                    ->whereRaw('LOWER(TRIM(nama_mapel)) = ?', [$g->clean_name])
                    ->where('status', true)
                    ->get();

                // Urutkan prioritas: memiliki jadwal terbanyak, modul ajar terbanyak, dan guru pengampu
                $sorted = $subjs->sortByDesc(function ($s) {
                    $sched = ClassSchedule::where('subject_id', $s->id)->count();
                    $modul = LmsModulAjar::where('mata_pelajaran_id', $s->id)->count();
                    $materi = LmsMateri::where('mata_pelajaran_id', $s->id)->count();
                    $hasGuru = $s->guru_pengampu_id ? 10 : 0;
                    return ($sched * 20) + ($modul * 10) + ($materi * 2) + $hasGuru;
                });

                $primary = $sorted->first();
                $duplicates = $sorted->slice(1);

                foreach ($duplicates as $dup) {
                    // Jika primary belum ada guru_pengampu_id tapi duplicate punya, salin ke primary
                    if (! $primary->guru_pengampu_id && $dup->guru_pengampu_id) {
                        $primary->guru_pengampu_id = $dup->guru_pengampu_id;
                        $primary->save();
                    }

                    // Migrasi seluruh relasi dari duplicate ID ke primary ID
                    $totalReferencesMigrated += DB::table('class_schedules')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    $totalReferencesMigrated += DB::table('lms_modul_ajar')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    $totalReferencesMigrated += DB::table('lms_materi')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    
                    if (DB::getSchemaBuilder()->hasTable('lms_capaian_pembelajaran')) {
                        $totalReferencesMigrated += DB::table('lms_capaian_pembelajaran')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('lms_penugasan')) {
                        $totalReferencesMigrated += DB::table('lms_penugasan')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('lms_bank_soal')) {
                        $totalReferencesMigrated += DB::table('lms_bank_soal')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('lms_kisi_kisi')) {
                        $totalReferencesMigrated += DB::table('lms_kisi_kisi')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('student_grades')) {
                        DB::statement("
                            DELETE FROM student_grades sg_dup
                            WHERE sg_dup.subject_id = ?
                              AND EXISTS (
                                  SELECT 1 FROM student_grades sg_pri
                                  WHERE sg_pri.subject_id = ?
                                    AND sg_pri.student_id = sg_dup.student_id
                                    AND sg_pri.academic_year_id = sg_dup.academic_year_id
                                    AND sg_pri.semester_id = sg_dup.semester_id
                              )
                        ", [$dup->id, $primary->id]);
                        $totalReferencesMigrated += DB::table('student_grades')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('exams')) {
                        $totalReferencesMigrated += DB::table('exams')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('assignments')) {
                        $totalReferencesMigrated += DB::table('assignments')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('materials')) {
                        $totalReferencesMigrated += DB::table('materials')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('modul_semesters')) {
                        $totalReferencesMigrated += DB::table('modul_semesters')->where('mata_pelajaran_id', $dup->id)->update(['mata_pelajaran_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('employee_teachings')) {
                        $totalReferencesMigrated += DB::table('employee_teachings')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }
                    if (DB::getSchemaBuilder()->hasTable('mutabaah_activity_logs')) {
                        $totalReferencesMigrated += DB::table('mutabaah_activity_logs')->where('subject_id', $dup->id)->update(['subject_id' => $primary->id]);
                    }

                    // Nonaktifkan dan soft-delete mapel duplikat
                    $dup->status = false;
                    $dup->deleted_at = Carbon::now();
                    $dup->save();
                    $totalDeactivated++;
                }
            }

            DB::commit();
            $this->command->info(sprintf(
                'Berhasil mengonsolidasi master mapel: %d record duplikat dinonaktifkan, %d referensi tabel dimigrasikan ke primary ID.',
                $totalDeactivated,
                $totalReferencesMigrated
            ));
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('Gagal konsolidasi: ' . $e->getMessage());
            throw $e;
        }
    }
}
