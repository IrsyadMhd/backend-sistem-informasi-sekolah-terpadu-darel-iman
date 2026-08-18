<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memorization_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('class_id');
            $table->uuid('student_id');
            $table->integer('target_lines')->default(0);
            $table->date('target_date');
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['academic_year_id', 'semester_id', 'class_id', 'target_date']);
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        Schema::create('memorization_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('class_id');
            $table->uuid('student_id');
            $table->uuid('teacher_id');
            $table->string('surah_name', 120);
            $table->integer('ayah_start');
            $table->integer('ayah_end');
            $table->integer('line_count')->default(0);
            $table->date('deposit_date');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['academic_year_id', 'semester_id', 'class_id', 'deposit_date']);
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
        });

        Schema::create('memorization_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('class_id')->nullable();
            $table->uuid('student_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_target_lines')->default(0);
            $table->integer('total_deposit_lines')->default(0);
            $table->decimal('achievement_percentage', 5, 2)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['academic_year_id', 'semester_id', 'period_start', 'period_end']);
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
        });

        Schema::create('mutabaah_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mutabaah_id');
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->smallInteger('month');
            $table->string('item_key', 50);
            $table->string('item_label', 100);
            $table->boolean('is_checked')->default(false);
            $table->integer('score')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['mutabaah_id', 'item_key']);
            $table->foreign(['mutabaah_id', 'academic_year_id', 'semester_id', 'month'])
                ->references(['id', 'academic_year_id', 'semester_id', 'month'])
                ->on('mutabaah_records')
                ->cascadeOnDelete();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_bank_id');
            $table->enum('question_type', ['multiple_choice', 'essay']);
            $table->text('question_text');
            $table->jsonb('options')->nullable();
            $table->text('answer_key')->nullable();
            $table->integer('weight')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['question_bank_id', 'question_type']);
            $table->foreign('question_bank_id')->references('id')->on('question_banks')->cascadeOnDelete();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id');
            $table->uuid('student_id');
            $table->decimal('score', 5, 2)->default(0);
            $table->text('teacher_notes')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['exam_id', 'student_id']);
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('mutabaah_details');
        Schema::dropIfExists('memorization_reports');
        Schema::dropIfExists('memorization_deposits');
        Schema::dropIfExists('memorization_targets');
    }
};
