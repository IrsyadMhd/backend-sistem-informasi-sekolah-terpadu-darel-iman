<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SESSION 16 — Performance Index Audit Migration
 *
 * Index ditambahkan HANYA setelah EXPLAIN/query pattern membuktikan kebutuhan.
 * Semua index bersifat NON-BREAKING (tidak mengubah skema kolom/constraint).
 *
 * Referensi: docs/ai/POSTGRESQL_INDEX_AUDIT.md
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────
        // 1. students — query filter paling sering: unit_id + is_active
        //    Query pattern: Student::where('unit_id', $x)->where('is_active', true)->count()
        //    Found in: FoundationDashboardService, KepalaSekolahDashboardService,
        //              WaliKelasDashboardService, StudentController
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('students') && !$this->indexExists('students', 'students_unit_active_idx')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index(['unit_id', 'is_active'], 'students_unit_active_idx');
            });
        }

        // students — filter gender + is_active (dashboard gender breakdown)
        //    Query: where('is_active', true)->where('gender', 'male')
        if (Schema::hasTable('students') && !$this->indexExists('students', 'students_active_gender_idx')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index(['is_active', 'gender'], 'students_active_gender_idx');
            });
        }

        // students — tahun_masuk (siswa baru dashboard KPI)
        //    Query: where('tahun_masuk', $year)
        if (Schema::hasTable('students') && !$this->indexExists('students', 'students_tahun_masuk_idx')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index('tahun_masuk', 'students_tahun_masuk_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 2. employees — frequent filter: unit_id + status
        //    Query pattern: Employee::where('unit_id', $x)->where('status', 'aktif')
        //    Found in: FoundationDashboardService, SdmReportService, KepalaSekolahDashboardService
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('employees') && !$this->indexExists('employees', 'employees_unit_status_idx')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['unit_id', 'status'], 'employees_unit_status_idx');
            });
        }

        // employees — status_pegawai (guru vs tendik classification)
        if (Schema::hasTable('employees') && !$this->indexExists('employees', 'employees_status_pegawai_idx')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index('status_pegawai', 'employees_status_pegawai_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 3. notifications — unread count query (called every page load per user)
        //    Query pattern: Notification::byUser($id)->unread()->count()
        //    Current: Likely Seq Scan on large tables
        //    Target: Index Scan on notifiable_id + read_at
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('notifications') && !$this->indexExists('notifications', 'notifications_user_read_idx')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['notifiable_id', 'read_at'], 'notifications_user_read_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 4. attendances — frequent range query per student + date
        //    Query: whereDate('attendance_date', $today)->whereIn('student_id', [...])
        //    Found in: KepalaSekolahDashboardService, WaliKelasDashboardService
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('attendances') && !$this->indexExists('attendances', 'attendances_student_date_idx')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['student_id', 'attendance_date'], 'attendances_student_date_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 5. lms_ujian_sesi — CBT auto-timeout scheduler
        //    Query: where('status', 'berlangsung')->where('waktu_mulai', '<', now())
        //    Command: cbt:auto-timeout runs every minute
        //    Without index: full scan every minute!
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('lms_ujian_sesi') && !$this->indexExists('lms_ujian_sesi', 'lms_ujian_sesi_status_waktu_idx')) {
            Schema::table('lms_ujian_sesi', function (Blueprint $table) {
                $table->index(['status', 'waktu_mulai'], 'lms_ujian_sesi_status_waktu_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 6. lms_penugasan — published assignments list
        //    Query: where('is_published', true)->where('kelas_id', $id)
        //    Found in: LmsPenugasanRepository, TeacherPortalController
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('lms_penugasan') && !$this->indexExists('lms_penugasan', 'lms_penugasan_kelas_published_idx')) {
            Schema::table('lms_penugasan', function (Blueprint $table) {
                $table->index(['kelas_id', 'is_published'], 'lms_penugasan_kelas_published_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 7. pengumuman_sekolah — status_aktif filter (all dashboards show active announcements)
        //    Query: PengumumanSekolah::where('status_aktif', true)->latest()->take(5)
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('pengumuman_sekolahs') && !$this->indexExists('pengumuman_sekolahs', 'pengumuman_status_created_idx')) {
            Schema::table('pengumuman_sekolahs', function (Blueprint $table) {
                $table->index(['status_aktif', 'created_at'], 'pengumuman_status_created_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 8. mutabaah_parent_signatures — parent signature lookup
        //    Query: whereIn('daily_header_id', [...])->orderBy('signed_at')
        //    Found in: MutabaahAnalyticsService, MutabaahPortalService, WaliKelasDashboardService
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('mutabaah_parent_signatures') && !$this->indexExists('mutabaah_parent_signatures', 'mutabaah_parent_sig_header_signed_idx')) {
            Schema::table('mutabaah_parent_signatures', function (Blueprint $table) {
                $table->index(['daily_header_id', 'signed_at'], 'mutabaah_parent_sig_header_signed_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 9. tahfizh_records — daily setoran query
        //    Query: whereDate('record_date', $today)->whereIn('student_id', [...])
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('tahfizh_records') && !$this->indexExists('tahfizh_records', 'tahfizh_records_student_date_idx')) {
            Schema::table('tahfizh_records', function (Blueprint $table) {
                $table->index(['student_id', 'record_date'], 'tahfizh_records_student_date_idx');
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 10. PostgreSQL-ONLY: Partial indexes (not available in SQLite)
        //     These are NO-OP in SQLite testing environment, active only on PG.
        // ─────────────────────────────────────────────────────────────
        if (DB::getDriverName() === 'pgsql') {
            // Partial index: only active students (most queries filter is_active=true)
            $this->safeExec("CREATE INDEX IF NOT EXISTS students_active_partial_idx
                ON students (unit_id, full_name)
                WHERE deleted_at IS NULL AND is_active = true");

            // Partial index: only active employees
            $this->safeExec("CREATE INDEX IF NOT EXISTS employees_active_partial_idx
                ON employees (unit_id)
                WHERE deleted_at IS NULL");

            // Partial index: unread notifications only
            $this->safeExec("CREATE INDEX IF NOT EXISTS notifications_unread_partial_idx
                ON notifications (notifiable_id, created_at)
                WHERE read_at IS NULL");

            // Partial index: CBT berlangsung only (very small subset)
            $this->safeExec("CREATE INDEX IF NOT EXISTS lms_ujian_sesi_berlangsung_idx
                ON lms_ujian_sesi (waktu_mulai)
                WHERE status = 'berlangsung'");
        }
    }

    public function down(): void
    {
        // Drop composite indexes (order matters for SQLite compatibility)
        $drops = [
            'students'               => ['students_unit_active_idx', 'students_active_gender_idx', 'students_tahun_masuk_idx'],
            'employees'              => ['employees_unit_status_idx', 'employees_status_pegawai_idx'],
            'notifications'          => ['notifications_user_read_idx'],
            'attendances'            => ['attendances_student_date_idx'],
            'lms_ujian_sesi'         => ['lms_ujian_sesi_status_waktu_idx'],
            'lms_penugasan'          => ['lms_penugasan_kelas_published_idx'],
            'pengumuman_sekolahs'    => ['pengumuman_status_created_idx'],
            'mutabaah_parent_signatures' => ['mutabaah_parent_sig_header_signed_idx'],
            'tahfizh_records'        => ['tahfizh_records_student_date_idx'],
        ];

        foreach ($drops as $table => $indexes) {
            if (!Schema::hasTable($table)) continue;
            foreach ($indexes as $index) {
                if ($this->indexExists($table, $index)) {
                    Schema::table($table, function (Blueprint $t) use ($index) {
                        $t->dropIndex($index);
                    });
                }
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS students_active_partial_idx');
            DB::statement('DROP INDEX IF EXISTS employees_active_partial_idx');
            DB::statement('DROP INDEX IF EXISTS notifications_unread_partial_idx');
            DB::statement('DROP INDEX IF EXISTS lms_ujian_sesi_berlangsung_idx');
        }
    }

    /**
     * Check if an index exists (SQLite + PostgreSQL compatible).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $result = DB::select(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",
                [$table, $indexName]
            );
            return !empty($result);
        }

        if ($driver === 'pgsql') {
            $result = DB::select(
                "SELECT indexname FROM pg_indexes WHERE tablename=? AND indexname=?",
                [$table, $indexName]
            );
            return !empty($result);
        }

        return false;
    }

    /**
     * Execute raw SQL safely, ignoring duplicate index errors.
     */
    private function safeExec(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Exception $e) {
            // Ignore: index already exists
        }
    }
};
