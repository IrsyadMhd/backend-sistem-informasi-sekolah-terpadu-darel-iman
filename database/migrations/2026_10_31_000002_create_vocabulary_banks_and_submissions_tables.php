<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('vocabulary_banks')) {
            Schema::create('vocabulary_banks', function (Blueprint $table) {
                $table->id();
                $table->uuid('unit_id')->index();
                $table->uuid('subject_id')->nullable()->index();
                $table->string('language', 20)->default('arab')->index(); // 'arab' | 'inggris'
                $table->string('word');
                $table->string('phonetic')->nullable();
                $table->string('translation');
                $table->text('example_sentence')->nullable();
                $table->string('target_level', 50)->default('Umum'); // TK, SD, SMP, SMA, Ponpes
                $table->string('theme', 100)->nullable(); // e.g. Kelas, Perlengkapan, Peribahasa
                $table->uuid('created_by')->index();
                $table->string('status', 30)->default('pending_principal')->index();
                // 'draft', 'pending_principal', 'pending_divisi', 'approved', 'rejected_principal', 'rejected_divisi'
                
                // Approval Tingkat 1 (Kepala Sekolah Unit)
                $table->uuid('principal_id')->nullable()->index();
                $table->timestamp('principal_approved_at')->nullable();
                $table->text('principal_notes')->nullable();

                // Approval Tingkat 2 (Divisi Pendidikan Yayasan)
                $table->uuid('divisi_id')->nullable()->index();
                $table->timestamp('divisi_approved_at')->nullable();
                $table->text('divisi_notes')->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_vocabulary_submissions')) {
            Schema::create('student_vocabulary_submissions', function (Blueprint $table) {
                $table->id();
                $table->uuid('student_id')->index();
                $table->unsignedBigInteger('vocabulary_id')->index();
                $table->uuid('teacher_id')->index();
                $table->uuid('unit_id')->index();
                $table->date('submission_date')->index();
                $table->string('fluency_rating', 30)->default('lancar'); // 'mutqin', 'lancar', 'mengulang'
                $table->integer('score')->default(85);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_vocabulary_submissions');
        Schema::dropIfExists('vocabulary_banks');
    }
};
