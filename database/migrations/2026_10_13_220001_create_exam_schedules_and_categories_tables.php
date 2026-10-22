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
        if (!Schema::hasTable('exam_categories')) {
            Schema::create('exam_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('unit_pendidikan_id')->nullable();
                $table->string('name', 100);
                $table->string('code', 50)->nullable();
                $table->string('color', 30)->default('amber');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('unit_pendidikan_id')->references('id')->on('education_units')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('exam_schedules')) {
            Schema::create('exam_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('academic_year_id');
                $table->uuid('semester_id');
                $table->uuid('exam_category_id');
                $table->uuid('kelas_id');
                $table->uuid('subject_id');
                $table->date('exam_date');
                $table->time('time_start');
                $table->time('time_end');
                $table->integer('duration_minutes')->default(90);
                $table->string('room', 100)->nullable();
                $table->uuid('invigilator_id')->nullable()->comment('FK to employees (Pengawas Ujian)');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
                $table->foreign('semester_id')->references('id')->on('semesters')->cascadeOnDelete();
                $table->foreign('exam_category_id')->references('id')->on('exam_categories')->cascadeOnDelete();
                $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->cascadeOnDelete();
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
                $table->foreign('invigilator_id')->references('id')->on('employees')->nullOnDelete();

                $table->index(['exam_category_id', 'exam_date'], 'exam_sched_cat_date_idx');
                $table->index(['kelas_id', 'exam_date'], 'exam_sched_kelas_date_idx');
                $table->index(['invigilator_id', 'exam_date'], 'exam_sched_invig_date_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exam_categories');
    }
};
