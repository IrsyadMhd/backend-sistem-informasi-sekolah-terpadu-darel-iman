<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCategories();
        $this->createAgendaItems();
        $this->alignTemplates();
        $this->alignTemplateItems();
        $this->alignTemplateAssignments();
        $this->createSupervisorAssignments();
        $this->createDailyHeaders();
        $this->createDailyDetails();
        $this->createParentSignatures();
        $this->createActivityNotes();
    }

    private function ensureCategories(): void
    {
        if (Schema::hasTable('mutabaah_categories')) {
            return;
        }

        Schema::create('mutabaah_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 100)->index();
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);
        });
    }

    private function createAgendaItems(): void
    {
        Schema::create('mutabaah_agenda_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('code', 40)->unique();
            $table->string('name', 180)->index();
            $table->string('input_type', 20)->default('status')->index();
            $table->decimal('weight', 8, 2)->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $this->auditColumns($table);

            $table->foreign('category_id')->references('id')->on('mutabaah_categories')->restrictOnDelete();
            $table->index(['category_id', 'sort_order'], 'mutabaah_agenda_items_category_idx');
        });

        DB::statement("
            ALTER TABLE mutabaah_agenda_items
            ADD CONSTRAINT mutabaah_agenda_items_input_type_check
            CHECK (input_type IN ('status','yes_no','checklist','number','duration','pages','verses','text'))
        ");

        if (Schema::hasTable('mutabaah_agendas') && DB::table('mutabaah_agendas')->exists()) {
            $fallbackId = (string) Str::uuid();
            DB::table('mutabaah_categories')->insertOrIgnore([
                'id' => $fallbackId,
                'code' => 'LEGACY',
                'name' => 'Agenda Lama',
                'sort_order' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $fallbackId = DB::table('mutabaah_categories')->where('code', 'LEGACY')->value('id');
            DB::statement("
                INSERT INTO mutabaah_agenda_items
                    (id, category_id, code, name, input_type, weight, icon, color, description, is_active,
                     created_by, updated_by, deleted_by, created_at, updated_at, deleted_at)
                SELECT
                    a.id,
                    COALESCE(a.category_id, ?::uuid),
                    COALESCE(a.code, 'LEGACY-' || substr(a.id::text, 1, 8)),
                    a.name,
                    CASE a.input_type
                        WHEN 'checklist' THEN 'checklist'
                        WHEN 'ya_tidak' THEN 'yes_no'
                        WHEN 'target' THEN 'number'
                        ELSE 'status'
                    END,
                    COALESCE(a.weight, 1),
                    a.icon,
                    a.color,
                    a.description,
                    a.is_active,
                    a.created_by,
                    a.updated_by,
                    a.deleted_by,
                    a.created_at,
                    a.updated_at,
                    a.deleted_at
                FROM mutabaah_agendas a
                ON CONFLICT (id) DO NOTHING
            ", [$fallbackId]);
        }
    }

    private function alignTemplates(): void
    {
        Schema::table('mutabaah_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('mutabaah_templates', 'education_unit_id')) {
                $table->uuid('education_unit_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('mutabaah_templates', 'education_level')) {
                $table->string('education_level', 50)->nullable()->after('education_unit_id')->index();
            }
            if (! Schema::hasColumn('mutabaah_templates', 'status')) {
                $table->string('status', 20)->default('active')->index();
            }
        });

        DB::statement('UPDATE mutabaah_templates SET education_unit_id = unit_id WHERE education_unit_id IS NULL');
        DB::statement('UPDATE mutabaah_templates SET education_level = level WHERE education_level IS NULL');
        DB::statement("UPDATE mutabaah_templates SET status = CASE WHEN is_active THEN 'active' ELSE 'inactive' END");

        Schema::table('mutabaah_templates', function (Blueprint $table) {
            $table->foreign('education_unit_id', 'mutabaah_templates_education_unit_fk')
                ->references('id')->on('education_units')->nullOnDelete();
            $table->index(['academic_year_id', 'semester_id', 'status'], 'mutabaah_templates_period_status_idx');
        });
    }

    private function alignTemplateItems(): void
    {
        Schema::table('mutabaah_template_items', function (Blueprint $table) {
            $table->uuid('agenda_item_id')->nullable()->after('template_id');
            $table->decimal('target_value', 12, 2)->nullable()->after('weight');
            $table->boolean('requires_parent_signature')->default(false)->after('is_required');
            $table->text('instruction')->nullable()->after('requires_parent_signature');
            $table->boolean('is_active')->default(true)->after('instruction')->index();
        });

        DB::statement('UPDATE mutabaah_template_items SET agenda_item_id = agenda_id WHERE agenda_item_id IS NULL');
        DB::statement('UPDATE mutabaah_template_items SET target_value = target WHERE target_value IS NULL');
        DB::statement('ALTER TABLE mutabaah_template_items ALTER COLUMN agenda_item_id SET NOT NULL');
        DB::statement('ALTER TABLE mutabaah_template_items ALTER COLUMN agenda_id DROP NOT NULL');

        Schema::table('mutabaah_template_items', function (Blueprint $table) {
            $table->foreign('agenda_item_id')->references('id')->on('mutabaah_agenda_items')->restrictOnDelete();
            $table->unique(['template_id', 'agenda_item_id'], 'mutabaah_template_agenda_item_unique');
            $table->index(['template_id', 'sort_order', 'is_active'], 'mutabaah_template_items_order_idx');
        });
    }

    private function alignTemplateAssignments(): void
    {
        Schema::table('mutabaah_template_assignments', function (Blueprint $table) {
            $table->uuid('education_unit_id')->nullable()->after('template_id');
            $table->string('education_level', 50)->nullable()->after('education_unit_id')->index();
            $table->uuid('kelas_id')->nullable()->after('education_level');
            $table->uuid('rombel_id')->nullable()->after('kelas_id');
            $table->uuid('student_id')->nullable()->after('rombel_id');
            $table->uuid('academic_year_id')->nullable()->after('student_id');
            $table->uuid('semester_id')->nullable()->after('academic_year_id');
            $table->unsignedSmallInteger('priority')->default(0)->after('end_date')->index();
            $table->string('status', 20)->default('active')->after('priority')->index();
        });

        DB::statement('UPDATE mutabaah_template_assignments SET education_unit_id = unit_id WHERE education_unit_id IS NULL');
        DB::statement('UPDATE mutabaah_template_assignments SET education_level = level WHERE education_level IS NULL');
        DB::statement('UPDATE mutabaah_template_assignments SET rombel_id = class_id WHERE rombel_id IS NULL');
        DB::statement("UPDATE mutabaah_template_assignments SET status = CASE WHEN is_active THEN 'active' ELSE 'inactive' END");
        DB::statement('
            UPDATE mutabaah_template_assignments
            SET academic_year_id = COALESCE(
                    academic_year_id,
                    (SELECT academic_year_id FROM mutabaah_templates WHERE id = mutabaah_template_assignments.template_id)
                ),
                semester_id = COALESCE(
                    semester_id,
                    (SELECT semester_id FROM mutabaah_templates WHERE id = mutabaah_template_assignments.template_id)
                )
        ');

        Schema::table('mutabaah_template_assignments', function (Blueprint $table) {
            $table->foreign('education_unit_id', 'mutabaah_template_assignments_unit_fk')->references('id')->on('education_units')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('rombel_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->index(['education_unit_id', 'academic_year_id', 'semester_id', 'status'], 'mutabaah_template_assignments_scope_idx');
            $table->index(['kelas_id', 'rombel_id', 'student_id'], 'mutabaah_template_assignments_target_idx');
        });
    }

    private function createSupervisorAssignments(): void
    {
        Schema::create('mutabaah_supervisor_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->string('supervisor_type', 30)->index();
            $table->uuid('education_unit_id');
            $table->uuid('kelas_id')->nullable();
            $table->uuid('rombel_id')->nullable();
            $table->uuid('dormitory_id')->nullable()->comment('TODO: add FK when dormitories table is available.');
            $table->uuid('room_id')->nullable()->comment('TODO: add FK when dormitory_rooms table is available.');
            $table->string('mentoring_group', 100)->nullable()->index();
            $table->uuid('template_id')->nullable();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->date('start_date')->index();
            $table->date('end_date')->nullable();
            $table->boolean('is_primary')->default(false)->index();
            $table->boolean('can_input')->default(true);
            $table->boolean('can_edit')->default(true);
            $table->boolean('can_finalize')->default(false);
            $table->boolean('can_view_report')->default(true);
            $table->string('status', 20)->default('active')->index();
            $this->auditColumns($table);

            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('education_unit_id')->references('id')->on('education_units')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('rombel_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('template_id')->references('id')->on('mutabaah_templates')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->index(['education_unit_id', 'academic_year_id', 'semester_id', 'status'], 'mutabaah_supervisor_scope_idx');
            $table->index(['employee_id', 'start_date', 'end_date'], 'mutabaah_supervisor_employee_period_idx');
        });

        DB::statement("
            ALTER TABLE mutabaah_supervisor_assignments
            ADD CONSTRAINT mutabaah_supervisor_type_check
            CHECK (supervisor_type IN ('pembimbing','wali_kelas','guru_pai','guru_tahfizh','musyrif','musyrifah'))
        ");
    }

    private function createDailyHeaders(): void
    {
        Schema::create('mutabaah_daily_headers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('template_id');
            $table->uuid('supervisor_assignment_id');
            $table->uuid('education_unit_id');
            $table->uuid('kelas_id')->nullable();
            $table->uuid('rombel_id')->nullable();
            $table->uuid('academic_year_id');
            $table->uuid('semester_id');
            $table->date('activity_date')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->unsignedSmallInteger('good_count')->default(0);
            $table->unsignedSmallInteger('less_count')->default(0);
            $table->unsignedSmallInteger('not_done_count')->default(0);
            $table->unsignedSmallInteger('na_count')->default(0);
            $table->decimal('score', 8, 2)->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->uuid('finalized_by')->nullable();
            $this->auditColumns($table);

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('mutabaah_templates')->restrictOnDelete();
            $table->foreign('supervisor_assignment_id')->references('id')->on('mutabaah_supervisor_assignments')->restrictOnDelete();
            $table->foreign('education_unit_id')->references('id')->on('education_units')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('classes')->nullOnDelete();
            $table->foreign('rombel_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('finalized_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['student_id', 'activity_date', 'template_id'], 'mutabaah_daily_student_date_template_unique');
            $table->index(['education_unit_id', 'activity_date', 'status'], 'mutabaah_daily_unit_date_status_idx');
            $table->index(['kelas_id', 'rombel_id', 'activity_date'], 'mutabaah_daily_class_date_idx');
        });

        DB::statement("
            ALTER TABLE mutabaah_daily_headers
            ADD CONSTRAINT mutabaah_daily_header_status_check
            CHECK (status IN ('draft','finalized','parent_reviewed','parent_signed','follow_up'))
        ");
    }

    private function createDailyDetails(): void
    {
        Schema::create('mutabaah_daily_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('daily_header_id');
            $table->uuid('template_item_id');
            $table->uuid('agenda_item_id');
            $table->string('status_value', 20)->nullable()->index();
            $table->decimal('numeric_value', 12, 2)->nullable();
            $table->text('text_value')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('input_by');
            $table->timestampTz('input_at')->useCurrent();
            $table->timestampsTz();

            $table->foreign('daily_header_id')->references('id')->on('mutabaah_daily_headers')->cascadeOnDelete();
            $table->foreign('template_item_id')->references('id')->on('mutabaah_template_items')->restrictOnDelete();
            $table->foreign('agenda_item_id')->references('id')->on('mutabaah_agenda_items')->restrictOnDelete();
            $table->foreign('input_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['daily_header_id', 'template_item_id'], 'mutabaah_daily_template_item_unique');
            $table->index(['daily_header_id', 'status_value'], 'mutabaah_daily_detail_status_idx');
        });

        DB::statement("
            ALTER TABLE mutabaah_daily_details
            ADD CONSTRAINT mutabaah_daily_detail_status_check
            CHECK (status_value IS NULL OR status_value IN ('good','less','not_done','na'))
        ");
    }

    private function createParentSignatures(): void
    {
        Schema::create('mutabaah_parent_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('daily_header_id');
            $table->uuid('parent_user_id');
            $table->string('signature_status', 32)->index();
            $table->text('comment')->nullable();
            $table->timestampTz('signed_at')->useCurrent();
            $table->jsonb('device_info')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampsTz();

            $table->foreign('daily_header_id')->references('id')->on('mutabaah_daily_headers')->cascadeOnDelete();
            $table->foreign('parent_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['daily_header_id', 'parent_user_id'], 'mutabaah_parent_header_user_unique');
        });

        DB::statement("
            ALTER TABLE mutabaah_parent_signatures
            ADD CONSTRAINT mutabaah_parent_signature_status_check
            CHECK (signature_status IN ('approved','clarification_requested','unable_to_verify'))
        ");
    }

    private function createActivityNotes(): void
    {
        Schema::create('mutabaah_activity_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('daily_header_id');
            $table->uuid('user_id');
            $table->string('note_type', 40)->index();
            $table->text('note');
            $table->timestampsTz();

            $table->foreign('daily_header_id')->references('id')->on('mutabaah_daily_headers')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['daily_header_id', 'created_at'], 'mutabaah_activity_notes_header_idx');
        });
    }

    private function auditColumns(Blueprint $table): void
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
        Schema::dropIfExists('mutabaah_activity_notes');
        Schema::dropIfExists('mutabaah_parent_signatures');
        Schema::dropIfExists('mutabaah_daily_details');
        Schema::dropIfExists('mutabaah_daily_headers');
        Schema::dropIfExists('mutabaah_supervisor_assignments');

        Schema::table('mutabaah_template_assignments', function (Blueprint $table) {
            $table->dropForeign('mutabaah_template_assignments_unit_fk');
            $table->dropForeign(['kelas_id']);
            $table->dropForeign(['rombel_id']);
            $table->dropForeign(['student_id']);
            $table->dropForeign(['academic_year_id']);
            $table->dropForeign(['semester_id']);
            $table->dropIndex('mutabaah_template_assignments_scope_idx');
            $table->dropIndex('mutabaah_template_assignments_target_idx');
            $table->dropColumn(['education_unit_id', 'education_level', 'kelas_id', 'rombel_id', 'student_id', 'academic_year_id', 'semester_id', 'priority', 'status']);
        });

        Schema::table('mutabaah_template_items', function (Blueprint $table) {
            $table->dropUnique('mutabaah_template_agenda_item_unique');
            $table->dropIndex('mutabaah_template_items_order_idx');
            $table->dropForeign(['agenda_item_id']);
            $table->dropColumn(['agenda_item_id', 'target_value', 'requires_parent_signature', 'instruction', 'is_active']);
        });
        DB::table('mutabaah_template_items')->whereNull('agenda_id')->delete();
        DB::statement('ALTER TABLE mutabaah_template_items ALTER COLUMN agenda_id SET NOT NULL');

        Schema::table('mutabaah_templates', function (Blueprint $table) {
            $table->dropForeign('mutabaah_templates_education_unit_fk');
            $table->dropIndex('mutabaah_templates_period_status_idx');
            $table->dropColumn(['education_unit_id', 'education_level', 'status']);
        });

        DB::statement('ALTER TABLE mutabaah_agenda_items DROP CONSTRAINT IF EXISTS mutabaah_agenda_items_input_type_check');
        Schema::dropIfExists('mutabaah_agenda_items');
    }
};
