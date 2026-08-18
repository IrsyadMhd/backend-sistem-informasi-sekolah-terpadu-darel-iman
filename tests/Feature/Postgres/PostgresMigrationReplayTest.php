<?php

namespace Tests\Feature\Postgres;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL Certification — Migration Replay & Seeder Idempotency
 *
 * Memverifikasi seluruh migrasi (partisi, index, FK, boolean) dapat
 * di-replay pada PostgreSQL tanpa pending/drift, dan seeder dapat
 * dijalankan ulang tanpa error (idempotent). Hanya berjalan di pgsql.
 */
class PostgresMigrationReplayTest extends TestCase
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

    public function test_semua_file_migrasi_tercatat_di_pg(): void
    {
        $files = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $file): string => basename($file, '.php'))
            ->sort()
            ->values()
            ->all();

        $ran = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();

        $this->assertCount(count($files), $ran, 'Jumlah migrasi tercatat harus sama dengan file di disk.');
        $this->assertEquals($files, $ran, 'Daftar migrasi di disk harus identik dengan yang tercatat di PG.');
    }

    public function test_migrasi_tidak_meninggalkan_pending_di_pg(): void
    {
        $exitCode = Artisan::call('migrate', ['--pretend' => true]);
        $output = trim(Artisan::output());

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('CREATE TABLE', $output);
        $this->assertStringNotContainsString('ALTER TABLE', $output);
    }

    public function test_seed_ulang_idempotent_tidak_mengubah_jumlah_row(): void
    {
        $countsBefore = [
            'users' => DB::table('users')->count(),
            'students' => DB::table('students')->count(),
            'employees' => DB::table('employees')->count(),
            'tbl_kelas' => DB::table('tbl_kelas')->count(),
            'academic_years' => DB::table('academic_years')->count(),
        ];

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        foreach ($countsBefore as $table => $before) {
            $this->assertSame($before, DB::table($table)->count(), "Seeding ulang harus idempotent untuk {$table}.");
        }
    }

    public function test_partisi_attendances_tetap_12_setelah_seed_ulang(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $months = DB::table('pg_inherits')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_inherits.inhrelid')
            ->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->join('pg_class as parent', 'parent.oid', '=', 'pg_inherits.inhparent')
            ->where('parent.relname', 'attendances')
            ->where('pg_namespace.nspname', 'public')
            ->count();

        $this->assertSame(12, $months, 'Partisi attendances harus tetap 12 bulan.');
    }
}
