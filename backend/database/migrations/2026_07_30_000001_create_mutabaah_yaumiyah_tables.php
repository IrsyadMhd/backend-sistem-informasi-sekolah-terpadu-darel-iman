<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutabaah_agendas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jenis_unit_id');
            $table->uuid('unit_id')->nullable();
            $table->string('category', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('jenis_unit_id')->references('uuid')->on('master_jenis_unit_pendidikan')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('education_units')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['jenis_unit_id', 'unit_id', 'is_active'], 'mutabaah_agenda_scope_idx');
        });

        Schema::create('mutabaah_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('agenda_id');
            $table->date('entry_date');
            $table->enum('status', ['baik', 'kurang', 'belum', 'na']);
            $table->uuid('mentor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('agenda_id')->references('id')->on('mutabaah_agendas')->restrictOnDelete();
            $table->foreign('mentor_id')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['student_id', 'agenda_id', 'entry_date'], 'mutabaah_entry_unique');
            $table->index(['student_id', 'entry_date'], 'mutabaah_entry_student_date_idx');
        });

        Schema::create('mutabaah_daily_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->date('entry_date');
            $table->uuid('mentor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestampsTz();

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('mentor_id')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['student_id', 'entry_date'], 'mutabaah_daily_note_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutabaah_daily_notes');
        Schema::dropIfExists('mutabaah_entries');
        Schema::dropIfExists('mutabaah_agendas');
    }
};
