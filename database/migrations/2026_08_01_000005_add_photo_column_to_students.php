<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration: Add photo Column to Students
 *
 * Masalah (Medium):
 * Beberapa komponen frontend (StudentFormModal, CetakKartuSiswaModal) menampilkan
 * foto siswa, tetapi kolom `photo` tidak ada di tabel `students`.
 * Saat ini foto tersimpan di `metadata` JSON atau path URL langsung dari upload.
 *
 * Solusi:
 * - Tambah kolom `photo` (nullable string/text) ke tabel `students`
 * - Ini tidak merusak data lama (nullable)
 * - Model Student sudah memiliki `metadata` cast sebagai array, jadi foto lama
 *   yang ada di `metadata.photo` tidak perlu di-backfill (dibaca via accessor)
 *
 * Accessor backward compat di model Student:
 *   getPhotoAttribute() → cek kolom `photo` dulu, fallback ke metadata['photo']
 *
 * Aturan SAFE REFACTOR:
 * - Nullable, tidak merusak data lama
 * - Tidak mengubah controller atau API endpoint
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'photo')) {
                $table->string('photo', 500)->nullable()->after('address')
                    ->comment('Path atau URL foto profil siswa');
            }

            // Tambah juga photo_thumb untuk thumbnail (opsional, untuk optimasi)
            if (! Schema::hasColumn('students', 'photo_thumb')) {
                $table->string('photo_thumb', 500)->nullable()->after('photo')
                    ->comment('Path atau URL thumbnail foto siswa');
            }
        });

        // Backfill photo dari metadata jika ada
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                UPDATE students
                SET photo = metadata->>'photo'
                WHERE photo IS NULL
                  AND metadata IS NOT NULL
                  AND metadata->>'photo' IS NOT NULL
                  AND metadata->>'photo' != ''
            ");
        } else {
            // SQLite: manual backfill
            $students = DB::table('students')
                ->whereNull('photo')
                ->whereNotNull('metadata')
                ->select('id', 'metadata')
                ->get();

            foreach ($students as $student) {
                if (empty($student->metadata)) {
                    continue;
                }
                $meta = json_decode($student->metadata, true);
                if (! empty($meta['photo'])) {
                    DB::table('students')->where('id', $student->id)->update([
                        'photo' => $meta['photo'],
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('students', 'photo')) {
                $cols[] = 'photo';
            }
            if (Schema::hasColumn('students', 'photo_thumb')) {
                $cols[] = 'photo_thumb';
            }
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
