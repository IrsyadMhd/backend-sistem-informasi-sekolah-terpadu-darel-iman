<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahfizh_daily_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id')->nullable();
            $table->uuid('semester_id')->nullable();
            $table->uuid('class_id')->nullable();
            $table->uuid('student_id');
            $table->uuid('teacher_id')->nullable();
            $table->date('record_date');
            $table->string('day_name', 20)->default('Senin');

            // Tilawah (Guru input manual)
            $table->text('tilawah_text')->nullable();
            $table->integer('tilawah_baris')->default(0);

            // Hafalan Baru (Dari Master Al-Qur'an)
            $table->integer('hafalan_surah_number')->nullable();
            $table->string('hafalan_surah_name', 150)->nullable();
            $table->integer('hafalan_ayah_start')->nullable();
            $table->integer('hafalan_ayah_end')->nullable();
            $table->integer('hafalan_baris')->default(0);

            // Murajaah (Input Orang Tua / Sound Recording)
            $table->text('murajaah_text')->nullable();
            $table->decimal('murajaah_lembar', 5, 2)->default(0);
            $table->string('audio_url', 255)->nullable();

            // Catatan & Ttd
            $table->text('notes_teacher')->nullable();
            $table->text('notes_parent')->nullable();
            $table->text('signature_teacher')->nullable();
            $table->text('signature_parent')->nullable();

            $table->string('status', 30)->default('submitted');
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['student_id', 'record_date']);
            $table->index(['student_id', 'class_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahfizh_daily_logs');
    }
};
