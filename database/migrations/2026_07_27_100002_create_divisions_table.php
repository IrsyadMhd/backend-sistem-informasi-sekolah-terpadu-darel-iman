<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 02
 *
 * Masalah: Tidak ada tabel master Divisi. `pemantauan_divisis.nama_divisi`
 * adalah string bebas tanpa integritas referensial.
 *
 * Solusi:
 * - Buat tabel `divisions` baru dengan UUID PK, self-referential parent.
 * - Backfill data dari nilai unik `nama_divisi` di `pemantauan_divisis`.
 * - Tambah kolom `division_id` nullable ke `pemantauan_divisis` sebagai bridge.
 *
 * Aturan SAFE REFACTOR:
 * - `pemantauan_divisis` tidak diedit strukturnya yang lama.
 * - `nama_divisi` (string) tetap ada — backward compat.
 * - `division_id` baru bersifat nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buat tabel master divisions
        Schema::create('divisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable(); // self-referential (hierarki divisi)
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['is_active', 'name']);
        });

        // Self-referential FK setelah tabel dibuat
        Schema::table('divisions', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('divisions')
                ->nullOnDelete();
        });

        // Backfill: Ekstrak divisi unik dari pemantauan_divisis
        if (Schema::hasTable('pemantauan_divisis') && Schema::hasColumn('pemantauan_divisis', 'nama_divisi')) {
            $namaUnik = DB::table('pemantauan_divisis')
                ->whereNotNull('nama_divisi')
                ->where('nama_divisi', '!=', '')
                ->distinct()
                ->pluck('nama_divisi');

            foreach ($namaUnik as $index => $nama) {
                $code = 'DIV-'.str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                DB::table('divisions')->insert([
                    'id' => DB::raw('gen_random_uuid()'),
                    'code' => $code,
                    'name' => $nama,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Tambah kolom bridge division_id ke pemantauan_divisis (nullable)
        if (Schema::hasTable('pemantauan_divisis')) {
            Schema::table('pemantauan_divisis', function (Blueprint $table) {
                if (! Schema::hasColumn('pemantauan_divisis', 'division_id')) {
                    $table->uuid('division_id')->nullable()->after('nama_divisi');
                }
            });

            // Tambah FK division_id → divisions
            Schema::table('pemantauan_divisis', function (Blueprint $table) {
                $table->foreign('division_id')
                    ->references('id')
                    ->on('divisions')
                    ->nullOnDelete();
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('
                    UPDATE pemantauan_divisis pd
                    SET division_id = d.id
                    FROM divisions d
                    WHERE pd.nama_divisi = d.name
                      AND pd.division_id IS NULL
                ');
            }
        }
    }

    public function down(): void
    {
        // Hapus bridge di pemantauan_divisis
        if (Schema::hasTable('pemantauan_divisis')) {
            Schema::table('pemantauan_divisis', function (Blueprint $table) {
                if (Schema::hasColumn('pemantauan_divisis', 'division_id')) {
                    $table->dropForeign(['division_id']);
                    $table->dropColumn('division_id');
                }
            });
        }

        Schema::dropIfExists('divisions');
    }
};
