<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutabaah_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->default('#0E5C44');
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $this->audit($table);
        });

        Schema::table('mutabaah_agendas', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('unit_id');
            $table->string('code', 40)->nullable()->unique()->after('category');
            $table->string('input_type', 30)->default('baik_kurang')->after('name');
            $table->decimal('target', 10, 2)->nullable()->after('input_type');
            $table->decimal('weight', 8, 2)->default(1)->after('target');
            $table->string('icon', 60)->nullable()->after('weight');
            $table->string('color', 20)->nullable()->after('icon');
            $table->string('level', 50)->nullable()->index()->after('color');
            $table->uuid('deleted_by')->nullable()->after('updated_by');
            $table->foreign('category_id')->references('id')->on('mutabaah_categories')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('mutabaah_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->uuid('unit_id')->nullable();
            $table->string('level', 50)->nullable()->index();
            $table->uuid('semester_id')->nullable();
            $table->uuid('academic_year_id')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $this->audit($table);
            $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
        });

        Schema::create('mutabaah_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('agenda_id');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('weight', 8, 2)->default(1);
            $table->decimal('target', 10, 2)->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestampsTz();
            $table->foreign('template_id')->references('id')->on('mutabaah_templates')->cascadeOnDelete();
            $table->foreign('agenda_id')->references('id')->on('mutabaah_agendas')->restrictOnDelete();
            $table->unique(['template_id', 'agenda_id']);
        });

        Schema::create('mutabaah_template_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->uuid('unit_id')->nullable();
            $table->string('level', 50)->nullable()->index();
            $table->uuid('class_id')->nullable();
            $table->string('group_name', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $this->audit($table);
            $table->foreign('template_id')->references('id')->on('mutabaah_templates')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('tbl_kelas')->nullOnDelete();
        });

        Schema::create('mutabaah_mentor_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('mentor_type', 30);
            $table->uuid('unit_id')->nullable();
            $table->string('level', 50)->nullable()->index();
            $table->uuid('class_id')->nullable();
            $table->string('dormitory', 100)->nullable();
            $table->string('room', 100)->nullable();
            $table->string('group_name', 100)->nullable();
            $table->uuid('template_id')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->audit($table);
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('class_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('mutabaah_templates')->nullOnDelete();
        });

        Schema::create('mutabaah_parent_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->date('entry_date');
            $table->uuid('parent_user_id');
            $table->text('comment')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('parent_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['student_id', 'entry_date', 'parent_user_id'], 'mutabaah_parent_signature_unique');
        });

        Schema::create('mutabaah_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('subject_type');
            $table->uuid('subject_id')->nullable();
            $table->string('event', 30)->index();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    private function audit(Blueprint $table): void
    {
        $table->uuid('created_by')->nullable();
        $table->uuid('updated_by')->nullable();
        $table->uuid('deleted_by')->nullable();
        $table->timestampsTz();
        $table->softDeletesTz();
        $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
    }

    public function down(): void
    {
        Schema::dropIfExists('mutabaah_activity_logs');
        Schema::dropIfExists('mutabaah_parent_confirmations');
        Schema::dropIfExists('mutabaah_mentor_assignments');
        Schema::dropIfExists('mutabaah_template_assignments');
        Schema::dropIfExists('mutabaah_template_items');
        Schema::dropIfExists('mutabaah_templates');
        Schema::table('mutabaah_agendas', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['category_id', 'code', 'input_type', 'target', 'weight', 'icon', 'color', 'level', 'deleted_by']);
        });
        Schema::dropIfExists('mutabaah_categories');
    }
};
