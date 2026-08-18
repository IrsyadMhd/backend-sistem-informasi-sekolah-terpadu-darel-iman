<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        }

        Schema::create('academic_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 32)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false)->index();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id');
            $table->string('name', 32);
            $table->tinyInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false)->index();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'sequence']);
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
        });

        Schema::create('school_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('setting_key')->unique();
            $table->jsonb('setting_value')->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('full_name');
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('occupation')->nullable();
            $table->string('address')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->unique();
            $table->string('employee_number', 50)->unique();
            $table->string('full_name');
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->date('join_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->smallInteger('capacity')->default(0);
            $table->string('location')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->uuid('classroom_id')->nullable();
            $table->uuid('homeroom_teacher_id')->nullable();
            $table->string('name', 50);
            $table->string('level', 20);
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'semester_id', 'name']);
            $table->index(['level', 'name']);
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
            $table->foreign('homeroom_teacher_id')->references('id')->on('teachers')->nullOnDelete();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->unique();
            $table->uuid('parent_id')->nullable();
            $table->uuid('class_id')->nullable();
            $table->string('nis', 50)->unique();
            $table->string('full_name');
            $table->enum('gender', ['male', 'female']);
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['class_id', 'is_active']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('parents')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX students_fts_idx ON students USING GIN (to_tsvector('simple', coalesce(nis,'') || ' ' || coalesce(full_name,'')))");
        }

        Schema::create('subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->uuid('class_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('video_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['teacher_id', 'subject_id', 'class_id']);
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX materials_fts_idx ON materials USING GIN (to_tsvector('simple', coalesce(title,'') || ' ' || coalesce(description,'')))");
        }

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->uuid('class_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['class_id', 'due_at']);
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('assignment_id');
            $table->uuid('student_id');
            $table->text('submission_text')->nullable();
            $table->string('file_path')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->unique(['assignment_id', 'student_id']);
            $table->foreign('assignment_id')->references('id')->on('assignments')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        Schema::create('question_banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('teacher_id');
            $table->uuid('subject_id');
            $table->string('title');
            $table->jsonb('questions');
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['teacher_id', 'subject_id']);
            $table->foreign('teacher_id')->references('id')->on('teachers')->restrictOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subject_id');
            $table->uuid('class_id');
            $table->uuid('question_bank_id')->nullable();
            $table->string('title');
            $table->timestampTz('start_at')->nullable();
            $table->timestampTz('end_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['class_id', 'start_at']);
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->restrictOnDelete();
            $table->foreign('question_bank_id')->references('id')->on('question_banks')->nullOnDelete();
        });

        Schema::create('student_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('teacher_id');
            $table->text('note');
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['student_id', 'teacher_id']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->enum('achievement_type', ['academic', 'non_academic']);
            $table->string('title');
            $table->string('level')->nullable();
            $table->date('achievement_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['student_id', 'achievement_type']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        Schema::create('graduates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id')->unique();
            $table->uuid('academic_year_id');
            $table->date('graduation_date');
            $table->string('graduation_number')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('graduate_id')->unique();
            $table->string('destination_school')->nullable();
            $table->string('major')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('graduate_id')->references('id')->on('graduates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS students_fts_idx');
        DB::statement('DROP INDEX IF EXISTS materials_fts_idx');

        Schema::dropIfExists('alumni');
        Schema::dropIfExists('graduates');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('student_notes');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('students');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('parents');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
