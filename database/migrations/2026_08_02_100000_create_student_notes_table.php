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
        if (! Schema::hasTable('student_notes')) {
            Schema::create('student_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('student_id');
                $table->uuid('teacher_id')->nullable();
                $table->uuid('education_unit_id')->nullable();
                $table->uuid('academic_year_id')->nullable();
                $table->uuid('semester_id')->nullable();
                $table->date('date')->default(now()->toDateString());
                $table->string('category')->default('Akademik'); // Akademik, Perilaku, Kedisiplinan, Prestasi, Konseling, Tahfizh, Ibadah, Kesehatan
                $table->string('title');
                $table->text('content');
                $table->string('priority')->default('medium'); // low, medium, high, urgent
                $table->text('follow_up')->nullable();
                $table->boolean('visible_to_parent')->default(true);
                $table->boolean('visible_to_student')->default(true);
                $table->string('attachment_path')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
