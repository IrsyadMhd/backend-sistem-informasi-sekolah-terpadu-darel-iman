<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahParentSignature;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MutabaahCrudFullExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_and_update_mutabaah_template()
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'MA-TEST',
            'name' => 'MA Al-Azhar Pesantren',
            'level' => 'MA',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // 1. Create Template
        $payload = [
            'code' => 'TMPL-001',
            'name' => 'Template Pembiasaan Harian Santri',
            'education_unit_id' => $unit->id,
            'education_level' => 'MA',
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'status' => 'active',
            'description' => 'Template utama santri MA.',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/templates', $payload);

        $response->assertStatus(201);
        $templateId = $response->json('data.id');

        $this->assertDatabaseHas('mutabaah_templates', [
            'id' => $templateId,
            'code' => 'TMPL-001',
            'name' => 'Template Pembiasaan Harian Santri',
        ]);

        // 2. Update Template
        $updatePayload = array_merge($payload, [
            'name' => 'Template Pembiasaan Harian Santri (Updated)',
        ]);

        $updateResponse = $this->actingAs($user, 'sanctum')
            ->putJson("/api/mutabaah/enterprise/templates/{$templateId}", $updatePayload);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('mutabaah_templates', [
            'id' => $templateId,
            'name' => 'Template Pembiasaan Harian Santri (Updated)',
        ]);
    }

    public function test_used_template_cannot_be_force_deleted()
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'SMA-FD',
            'name' => 'SMA Terpadu FD',
            'level' => 'SMA',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $template = MutabaahTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'TMPL-USED',
            'name' => 'Template Digunakan',
            'education_unit_id' => $unit->id,
            'education_level' => 'SMA',
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'niy' => '19900102',
            'nama_lengkap' => 'Ustadz Pembimbing',
            'status' => 'Aktif',
        ]);

        $assignment = MutabaahSupervisorAssignment::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'supervisor_type' => 'musyrif',
            'education_unit_id' => $unit->id,
            'template_id' => $template->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'active',
        ]);

        $student = Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $unit->id,
            'nis' => '889900',
            'full_name' => 'Santri Test',
            'gender' => 'male',
            'is_active' => true,
        ]);

        MutabaahDailyHeader::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'template_id' => $template->id,
            'supervisor_assignment_id' => $assignment->id,
            'education_unit_id' => $unit->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'activity_date' => '2026-08-01',
            'status' => 'draft',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/mutabaah/enterprise/templates/{$template->id}/force");

        $response->assertStatus(409);
        $this->assertDatabaseHas('mutabaah_templates', ['id' => $template->id]);
    }

    public function test_tu_can_create_template_assignment_and_conflict_is_rejected()
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'SMA-TEST',
            'name' => 'SMA Terpadu',
            'level' => 'SMA',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $template = MutabaahTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'TMPL-SMA',
            'name' => 'Template SMA',
            'education_unit_id' => $unit->id,
            'education_level' => 'SMA',
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'status' => 'active',
        ]);

        // Konvensi skema mutabaah: `kelas_id` mengacu tabel `classes`
        // (SchoolClass/LMS), `rombel_id` mengacu `tbl_kelas`.
        $schoolClass = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'name' => '10-IPA-1',
            'level' => 'SMA',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $assignmentPayload = [
            'template_id' => $template->id,
            'education_unit_id' => $unit->id,
            'education_level' => 'SMA',
            'kelas_id' => $schoolClass->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'status' => 'active',
        ];

        // 1. First Assignment
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/template-assignments', $assignmentPayload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mutabaah_template_assignments', [
            'template_id' => $template->id,
            'kelas_id' => $schoolClass->id,
        ]);

        // 2. Duplicate / Overlapping Assignment Rejected (HTTP 422)
        $conflictResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/template-assignments', $assignmentPayload);

        $conflictResponse->assertStatus(422);
    }

    public function test_assign_supervisor_with_permissions_and_unit_validation()
    {
        $unitPesantren = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'PES-01',
            'name' => 'SMA Pesantren Al-Hikmah',
            'level' => 'SMA',
        ]);

        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unitPesantren->id,
            'niy' => '19900101',
            'nama_lengkap' => 'Ustadz Ahmad',
            'status' => 'Aktif',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $supervisorPayload = [
            'employee_id' => $employee->id,
            'supervisor_type' => 'musyrif',
            'education_unit_id' => $unitPesantren->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'can_input' => true,
            'can_edit' => true,
            'can_finalize' => true,
            'can_view_report' => true,
            'status' => 'active',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/supervisor-assignments', $supervisorPayload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mutabaah_supervisor_assignments', [
            'employee_id' => $employee->id,
            'supervisor_type' => 'musyrif',
            'education_unit_id' => $unitPesantren->id,
        ]);
    }

    public function test_user_can_create_agenda_and_prevent_hard_delete_if_used()
    {
        $category = MutabaahCategory::create([
            'id' => (string) Str::uuid(),
            'code' => 'IBADAH-DAILY',
            'name' => 'Ibadah Harian',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $agendaPayload = [
            'category_id' => $category->id,
            'code' => 'SHALAT-SUBUH',
            'name' => 'Shalat Subuh Berjamaah',
            'input_type' => 'status',
            'weight' => 1,
            'is_active' => true,
        ];

        // 1. Create Agenda Item
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/agendas', $agendaPayload);

        $response->assertStatus(201);
        $agendaId = $response->json('data.id');

        $this->assertDatabaseHas('mutabaah_agenda_items', [
            'id' => $agendaId,
            'code' => 'SHALAT-SUBUH',
        ]);

        // 2. Soft delete when not used
        $deleteResponse = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/mutabaah/enterprise/agendas/{$agendaId}");

        $deleteResponse->assertStatus(200);

        // 3. Restore Agenda Item
        $restoreResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/mutabaah/enterprise/agendas/{$agendaId}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('mutabaah_agenda_items', [
            'id' => $agendaId,
            'deleted_at' => null,
        ]);
    }

    public function test_parent_monitoring_reads_real_signatures()
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'MA-PARENT',
            'name' => 'MA Al-Hikmah',
            'level' => 'MA',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $template = MutabaahTemplate::create([
            'id' => (string) Str::uuid(),
            'code' => 'TMPL-PARENT',
            'name' => 'Template Parent',
            'education_unit_id' => $unit->id,
            'education_level' => 'MA',
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'niy' => '19900103',
            'nama_lengkap' => 'Ustadzah Pembina',
            'status' => 'Aktif',
        ]);

        $assignment = MutabaahSupervisorAssignment::create([
            'id' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'supervisor_type' => 'musyrifah',
            'education_unit_id' => $unit->id,
            'template_id' => $template->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'active',
        ]);

        $student = Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $unit->id,
            'nis' => '112233',
            'full_name' => 'Siswa Anak',
            'gender' => 'male',
            'is_active' => true,
        ]);

        $parentUser = User::factory()->create();
        $parentUser->assignRole('Orang Tua');

        $parent = ParentModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $parentUser->id,
            'full_name' => 'Orang Tua Fulan',
        ]);

        $header = MutabaahDailyHeader::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'template_id' => $template->id,
            'supervisor_assignment_id' => $assignment->id,
            'education_unit_id' => $unit->id,
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'activity_date' => '2026-08-01',
            'status' => 'finalized',
        ]);

        MutabaahParentSignature::create([
            'id' => (string) Str::uuid(),
            'daily_header_id' => $header->id,
            'parent_user_id' => $parentUser->id,
            'signature_status' => 'approved',
            'comment' => 'Semoga istiqamah.',
            'signed_at' => now(),
        ]);

        $adminUser = User::factory()->create();
        $adminUser->assignRole('Super Admin');

        $response = $this->actingAs($adminUser, 'sanctum')
            ->getJson('/api/mutabaah/analytics/recap?signature_status=signed');

        $response->assertStatus(200);
        $this->assertDatabaseHas('mutabaah_parent_signatures', [
            'daily_header_id' => $header->id,
            'signature_status' => 'approved',
        ]);
    }

    public function test_unauthorized_user_cannot_create_mutabaah_template()
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/mutabaah/enterprise/templates', [
                'code' => 'FORBIDDEN-01',
                'name' => 'Template Unauthorized',
                'education_level' => 'SMA',
                'start_date' => '2026-08-01',
                'status' => 'active',
            ]);

        $response->assertStatus(403);
    }
}
