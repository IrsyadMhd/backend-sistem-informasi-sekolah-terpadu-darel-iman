<?php

namespace Tests\Feature\Postgres;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL Certification — Schema Contract
 *
 * Verifikasi bahwa schema runtime PostgreSQL sesuai dengan ekspektasi model
 * dan kode aplikasi. Test group ini HANYA lulus pada PostgreSQL
 * (di-skip otomatis bila driver bukan pgsql).
 */
class PostgresSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL certification suite hanya berjalan di driver pgsql.');
        }

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_driver_adalah_pgsql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_runtime_database_adalah_postgresql(): void
    {
        $version = DB::selectOne('SELECT version()')->version;
        $this->assertStringContainsString('PostgreSQL', $version);
    }

    public function test_partisi_attendances_memiliki_12_bulan(): void
    {
        $months = DB::table('pg_inherits')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_inherits.inhrelid')
            ->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->join('pg_class as parent', 'parent.oid', '=', 'pg_inherits.inhparent')
            ->where('parent.relname', 'attendances')
            ->where('pg_namespace.nspname', 'public')
            ->count();

        $this->assertSame(12, $months, 'attendances harus memiliki 12 partisi bulan.');
    }

    public function test_attendances_partisi_memiliki_kolom_month_not_null(): void
    {
        $columns = DB::select("
            SELECT column_name, is_nullable
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'attendances' AND column_name = 'month'
        ");

        $this->assertNotEmpty($columns);
        $this->assertSame('NO', $columns[0]->is_nullable);
    }

    public function test_kolom_boolean_adalah_native_boolean(): void
    {
        $booleanColumns = [
            'students' => 'is_active',
            'lms_kisi_kisi' => 'status',
            'lms_penugasan' => 'is_published',
        ];

        foreach ($booleanColumns as $table => $column) {
            $dataType = DB::selectOne("
                SELECT data_type
                FROM information_schema.columns
                WHERE table_schema = 'public' AND table_name = ? AND column_name = ?
            ", [$table, $column]);

            $this->assertNotNull($dataType, "{$table}.{$column} tidak ditemukan.");
            $this->assertSame('boolean', $dataType->data_type, "{$table}.{$column} harus boolean native.");
        }
    }

    public function test_tbl_kelas_menggunakan_status_varchar_bukan_boolean(): void
    {
        // tbl_kelas tidak punya kolom is_active; status kelas berupa string
        // 'Aktif'/'Nonaktif' (bukan boolean). Kolom legacy `is_active` TIDAK ada.
        $missing = DB::selectOne("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'tbl_kelas' AND column_name = 'is_active'
        ");
        $this->assertNull($missing, 'tbl_kelas.is_active tidak boleh ada (gunakan kolom status).');

        $status = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = 'tbl_kelas' AND column_name = 'status'
        ");
        $this->assertNotNull($status, 'tbl_kelas.status harus ada.');
        $this->assertSame('character varying', $status->data_type, 'tbl_kelas.status harus varchar.');
    }

    public function test_partial_unique_index_satu_akademik_tahun_aktif(): void
    {
        $index = DB::selectOne("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'academic_years' AND indexname = 'uniq_one_active_academic_year'
        ");

        $this->assertNotNull($index, 'Partial unique index uniq_one_active_academic_year harus ada di PG.');
    }

    public function test_fk_partisi_parent_sama_dengan_child(): void
    {
        $constraints = DB::select("
            SELECT conname, contype
            FROM pg_constraint
            WHERE conrelid = 'attendances'::regclass
              AND contype = 'f'
        ");

        $this->assertNotEmpty($constraints);
    }

    public function test_migrasi_kunci_berstatus_ran(): void
    {
        $keys = [
            '2026_07_21_030100_create_partitioned_operational_tables',
            '2026_08_07_000001_reconcile_employee_attendance_partition',
            '2026_08_07_100000_add_performance_indexes',
        ];

        $ran = DB::table('migrations')->pluck('migration')->all();

        foreach ($keys as $key) {
            $this->assertContains($key, $ran, "Migration {$key} harus tercatat sudah berjalan di PG.");
        }
    }

    public function test_seeder_menghasilkan_data_runtime(): void
    {
        $counts = [
            'users' => 1,
            'education_units' => 1,
            'employees' => 1,
            'students' => 1,
            'tbl_kelas' => 1,
            'subjects' => 1,
            'academic_years' => 1,
        ];

        foreach ($counts as $table => $min) {
            $count = DB::table($table)->count();
            $this->assertGreaterThanOrEqual($min, $count, "Tabel {$table} harus punya minimal {$min} row setelah seed.");
        }
    }

    public function test_pengguna_superadmin_ada_dengan_email_kontrak(): void
    {
        $user = DB::table('users')->where('email', 'superadmin@school-erp.local')->first();
        $this->assertNotNull($user, 'User superadmin@school-erp.local wajib ada.');
    }
}
