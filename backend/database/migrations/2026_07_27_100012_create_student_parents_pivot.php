<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 12
 *
 * Masalah: students.parent_id hanya mendukung 1 orang tua per siswa.
 * Tidak bisa menyimpan data ayah + ibu + wali secara terpisah.
 *
 * Solusi: Buat tabel pivot student_parents untuk relasi many-to-many.
 * students.parent_id TETAP ADA untuk backward compat dengan API lama.
 *
 * Backfill: Migrasikan data students.parent_id yang ada ke tabel pivot.
 *
 * Aturan SAFE REFACTOR:
 * - students.parent_id TIDAK dihapus — backward compat.
 * - Tabel pivot baru bersifat additive.
 * - API lama yang menggunakan parent_id tetap berfungsi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('parent_id');
            $table->enum('relationship_type', ['father', 'mother', 'guardian'])
                ->default('father')
                ->comment('Hubungan: ayah, ibu, atau wali');
            $table->boolean('is_primary')->default(false)
                ->comment('Apakah ini kontak utama/wali resmi');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            // Unique: satu siswa hanya bisa punya satu entry per parent
            $table->unique(['student_id', 'parent_id'], 'uniq_student_parent');

            // Index
            $table->index(['student_id', 'relationship_type'], 'student_parents_lookup_idx');

            // FK
            $table->foreign('student_id')
                ->references('id')
                ->on('students')
                ->cascadeOnDelete();
            $table->foreign('parent_id')
                ->references('id')
                ->on('parents')
                ->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('students', 'parent_id')) {
            DB::statement("
                INSERT INTO student_parents (id, student_id, parent_id, relationship_type, is_primary, created_at, updated_at)
                SELECT
                    gen_random_uuid(),
                    s.id AS student_id,
                    s.parent_id,
                    'father' AS relationship_type,
                    true AS is_primary,
                    NOW(),
                    NOW()
                FROM students s
                WHERE s.parent_id IS NOT NULL
                  AND s.deleted_at IS NULL
                ON CONFLICT (student_id, parent_id) DO NOTHING
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_parents');
    }
};
