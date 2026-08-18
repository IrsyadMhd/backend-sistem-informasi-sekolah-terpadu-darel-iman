<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SESI 10 — Rekonsiliasi skema `student_notes` untuk Portal Orang Tua.
 *
 * Tabel `student_notes` dibuat oleh migration core (2026_07_21_030000) dengan
 * skema legacy (`note` + `metadata`), sehingga kolom yang dipetakan model
 * (title, content, visible_to_parent, dll.) TIDAK ADA di database → query
 * Portal Orang Tua "Catatan Guru" & "Tanda Tangan" error runtime.
 *
 * Migration ini bersifat idempotent: menambah kolom yang hilang, memigrasi
 * isi `note` → `content`, dan menambahkan kolom tanda tangan Orang Tua.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_notes')) {
            return;
        }

        $columns = [
            'education_unit_id' => fn (Blueprint $t) => $t->uuid('education_unit_id')->nullable(),
            'academic_year_id' => fn (Blueprint $t) => $t->uuid('academic_year_id')->nullable(),
            'semester_id' => fn (Blueprint $t) => $t->uuid('semester_id')->nullable(),
            'date' => fn (Blueprint $t) => $t->date('date')->nullable(),
            'category' => fn (Blueprint $t) => $t->string('category', 60)->nullable(),
            'title' => fn (Blueprint $t) => $t->string('title')->nullable(),
            'content' => fn (Blueprint $t) => $t->text('content')->nullable(),
            'priority' => fn (Blueprint $t) => $t->string('priority', 20)->nullable(),
            'follow_up' => fn (Blueprint $t) => $t->text('follow_up')->nullable(),
            'visible_to_parent' => fn (Blueprint $t) => $t->boolean('visible_to_parent')->nullable(),
            'visible_to_student' => fn (Blueprint $t) => $t->boolean('visible_to_student')->nullable(),
            'attachment_path' => fn (Blueprint $t) => $t->string('attachment_path')->nullable(),
            'signed_by_user_id' => fn (Blueprint $t) => $t->uuid('signed_by_user_id')->nullable(),
            'signed_at' => fn (Blueprint $t) => $t->timestampTz('signed_at')->nullable(),
            'signature_content_hash' => fn (Blueprint $t) => $t->string('signature_content_hash', 64)->nullable(),
        ];

        Schema::table('student_notes', function (Blueprint $table) use ($columns) {
            foreach ($columns as $name => $definition) {
                if (! Schema::hasColumn('student_notes', $name)) {
                    $definition($table);
                }
            }
        });

        // Migrasi data: note (legacy) → content bila content kosong.
        if (Schema::hasColumn('student_notes', 'note') && Schema::hasColumn('student_notes', 'content')) {
            DB::table('student_notes')
                ->whereNull('content')
                ->whereNotNull('note')
                ->update(['content' => DB::raw('note')]);
        }

        // Skema legacy menetapkan `note` NOT NULL padahal model kini menulis
        // ke `content`. Kolom lama dilonggarkan agar insert Eloquent tidak
        // gagal di basis data yang menjalankan migration core lebih dulu.
        if (Schema::hasColumn('student_notes', 'note')) {
            Schema::table('student_notes', fn (Blueprint $table) => $table->text('note')->nullable()->change());
        }

        // Backfill default ringan agar UI lama tidak menampilkan kosong.
        DB::table('student_notes')->whereNull('visible_to_parent')->update(['visible_to_parent' => true]);
        DB::table('student_notes')->whereNull('visible_to_student')->update(['visible_to_student' => true]);
        DB::table('student_notes')->whereNull('category')->update(['category' => 'Akademik']);
        DB::table('student_notes')->whereNull('priority')->update(['priority' => 'medium']);
        DB::table('student_notes')->whereNull('date')->update(['date' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Tidak menghapus kolom — migration rekonsiliasi bersifat sekali jalan.
    }
};
