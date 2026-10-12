<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BusinessOption;
use App\Models\CodeSequence;
use App\Models\EducationUnit;
use App\Models\ModaTransportasi;
use App\Models\Semester;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\CodeGeneratorService;
use Database\Seeders\BusinessOptionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalDynamicDataAndCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected EducationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin.dynamic@test.com',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->unit = EducationUnit::create([
            'code' => 'TKIT',
            'name' => 'TKIT Dar el-Iman',
            'level' => 'TK',
            'is_active' => true,
        ]);
    }

    public function test_academic_period_service_resolves_active_and_does_not_use_arbitrary_fallback(): void
    {
        // 1. When no academic year exists at all
        AcademicPeriodService::clearCache();
        $this->assertNull(AcademicPeriodService::getActiveYear());
        $this->assertNull(AcademicPeriodService::getActiveSemester());

        $contextEmpty = AcademicPeriodService::getActivePeriodContext();
        $this->assertFalse($contextEmpty['is_configured']);
        $this->assertEquals('Tahun ajaran aktif belum dikonfigurasi', $contextEmpty['status_message']);

        // 2. When academic years exist but NONE is active
        $inactiveYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => false,
        ]);

        AcademicPeriodService::clearCache();
        $this->assertNull(AcademicPeriodService::getActiveYear());
        $this->assertFalse(AcademicPeriodService::getActivePeriodContext()['is_configured']);

        // 3. When an active year and semester are configured
        $activeYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $activeSemester = Semester::create([
            'academic_year_id' => $activeYear->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'is_active' => true,
        ]);

        AcademicPeriodService::clearCache();
        $resolvedYear = AcademicPeriodService::getActiveYear();
        $resolvedSemester = AcademicPeriodService::getActiveSemester();

        $this->assertNotNull($resolvedYear);
        $this->assertEquals($activeYear->id, $resolvedYear->id);
        $this->assertNotNull($resolvedSemester);
        $this->assertEquals($activeSemester->id, $resolvedSemester->id);

        $contextConfigured = AcademicPeriodService::getActivePeriodContext();
        $this->assertTrue($contextConfigured['is_configured']);
        $this->assertEquals('2026/2027', $contextConfigured['academic_year_name']);
        $this->assertEquals('Semester Ganjil', $contextConfigured['semester_name']);
    }

    public function test_code_generator_service_generates_sequential_codes_and_preview_does_not_increment(): void
    {
        // Setup code sequence rule for class_code
        CodeSequence::create([
            'entity_type' => 'class_code',
            'prefix' => 'KLS',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{EXTRA}{SEPARATOR}{SEQUENCE}',
            'current_sequence' => 0,
            'is_active' => true,
        ]);

        // 1. Preview code should be sequence 001 without incrementing in database
        $preview1 = CodeGeneratorService::preview('class_code', ['extra' => 'SD']);
        $this->assertEquals('KLS-SD-001', $preview1);

        $dbSequence = CodeSequence::where('entity_type', 'class_code')->first();
        $this->assertEquals(0, $dbSequence->current_sequence);

        // 2. Official generate increments sequence to 1
        $code1 = CodeGeneratorService::generate('class_code', ['extra' => 'SD']);
        $this->assertEquals('KLS-SD-001', $code1);

        $dbSequence->refresh();
        $this->assertEquals(1, $dbSequence->current_sequence);

        // 3. Second generate increments sequence to 2
        $code2 = CodeGeneratorService::generate('class_code', ['extra' => 'SD']);
        $this->assertEquals('KLS-SD-002', $code2);

        $dbSequence->refresh();
        $this->assertEquals(2, $dbSequence->current_sequence);

        // 4. Preview now returns sequence 003 without mutating
        $preview2 = CodeGeneratorService::preview('class_code', ['extra' => 'SD']);
        $this->assertEquals('KLS-SD-003', $preview2);
        $dbSequence->refresh();
        $this->assertEquals(2, $dbSequence->current_sequence);

        // 5. Test employee_niy generation
        $niyCode = CodeGeneratorService::generate('employee_niy', $this->unit->id);
        $this->assertStringStartsWith('NIY-', $niyCode);

        // 6. Test student_nis generation
        $nisCode = CodeGeneratorService::generate('student_nis', $this->unit->id);
        $this->assertStringContainsString('TKIT', $nisCode);
    }

    public function test_code_generator_api_endpoints(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/code-generator/preview?entity_type=student_nis&unit_id=' . $this->unit->id);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['entity_type', 'preview_code'],
            ]);

        $configsResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/code-generator/configs');

        $configsResponse->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['entity_type', 'prefix', 'separator', 'padding_length'],
                ],
            ]);
    }

    public function test_business_options_seeder_and_dropdown_api(): void
    {
        $this->seed(BusinessOptionSeeder::class);

        // Check options for parent_occupation
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/options/dropdown/parent_occupation');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'group', 'code', 'name', 'order', 'is_active'],
                ],
            ]);

        $occupations = collect($response->json('data'))->pluck('name');
        $this->assertContains('Pegawai Negeri', $occupations);
        $this->assertContains('Wiraswasta', $occupations);

        // Check options for residence_type
        $residenceResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/options/dropdown/residence_type');

        $residenceResponse->assertOk();
        $residences = collect($residenceResponse->json('data'))->pluck('name');
        $this->assertContains('Bersama Orang Tua', $residences);
        $this->assertContains('Asrama', $residences);
    }

    public function test_business_option_crud_operations(): void
    {
        // 1. Create a new custom business option
        $createResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/master/options', [
                'group' => 'parent_occupation',
                'name' => 'Data Scientist',
                'code' => 'DATA_SCIENTIST',
                'order' => 99,
                'is_active' => true,
                'description' => 'Profesi spesialis data',
            ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Data Scientist')
            ->assertJsonPath('data.code', 'DATA_SCIENTIST');

        $optionId = $createResponse->json('data.id');

        // 2. Retrieve dropdown and confirm new option is available without code change
        $dropdownResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/options/dropdown/parent_occupation');

        $dropdownResponse->assertOk();
        $names = collect($dropdownResponse->json('data'))->pluck('name');
        $this->assertContains('Data Scientist', $names);

        // 3. Update option
        $updateResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson('/api/master/options/' . $optionId, [
                'name' => 'AI Engineer & Data Scientist',
                'description' => 'Updated description',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'AI Engineer & Data Scientist');

        // 4. Toggle active status
        $toggleResponse = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson('/api/master/options/' . $optionId . '/toggle-status');

        $toggleResponse->assertOk()
            ->assertJsonPath('data.is_active', false);

        // 5. Inactive option should not appear in dropdown
        $dropdownAfterToggle = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/options/dropdown/parent_occupation');

        $activeNames = collect($dropdownAfterToggle->json('data'))->pluck('name');
        $this->assertNotContains('AI Engineer & Data Scientist', $activeNames);
    }

    public function test_moda_transportasi_crud_and_dropdown(): void
    {
        $createRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/master/moda-transportasi', [
                'name' => 'Kereta Api / MRT',
                'description' => 'Moda transportasi berbasis rel',
                'is_active' => true,
            ]);

        $createRes->assertCreated()
            ->assertJsonPath('data.name', 'Kereta Api / MRT');

        $id = $createRes->json('data.id');
        $this->assertNotNull(ModaTransportasi::find($id));

        // Check dropdown
        $dropdownRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/moda-transportasi/dropdown');

        $dropdownRes->assertOk();
        $names = collect($dropdownRes->json('data'))->pluck('name');
        $this->assertContains('Kereta Api / MRT', $names);

        // Toggle status
        $toggleRes = $this->actingAs($this->superAdmin, 'sanctum')
            ->patchJson("/api/master/moda-transportasi/{$id}/toggle-status");

        $toggleRes->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Inactive should not be in dropdown
        $dropdownRes2 = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/master/moda-transportasi/dropdown');
        $names2 = collect($dropdownRes2->json('data'))->pluck('name');
        $this->assertNotContains('Kereta Api / MRT', $names2);
    }

    public function test_extended_code_generator_types_and_concurrency_protection(): void
    {
        // 1. Curriculum code generation
        $curriculumCode1 = CodeGeneratorService::generate('curriculum_code', $this->unit->id, null, ['extra' => 'SIT']);
        $curriculumCode2 = CodeGeneratorService::generate('curriculum_code', $this->unit->id, null, ['extra' => 'SIT']);
        $this->assertNotEquals($curriculumCode1, $curriculumCode2);
        $this->assertStringStartsWith('KUR-SIT-', $curriculumCode1);
        $this->assertStringStartsWith('KUR-SIT-', $curriculumCode2);

        // 2. Permit number generation
        $permit1 = CodeGeneratorService::generate('permit_number', $this->unit->id);
        $permit2 = CodeGeneratorService::generate('permit_number', $this->unit->id);
        $this->assertNotEquals($permit1, $permit2);
        $this->assertStringStartsWith('BP-', $permit1);

        // 3. Unit code generation
        $unitCode1 = CodeGeneratorService::generate('unit_code', null, null, ['extra' => 'TKIT']);
        $unitCode2 = CodeGeneratorService::generate('unit_code', null, null, ['extra' => 'TKIT']);
        $this->assertNotEquals($unitCode1, $unitCode2);

        // 4. Concurrency sequence simulation: 5 sequential calls within transaction produce 5 distinct unique sequences
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $codes[] = CodeGeneratorService::generate('position_code');
        }
        $this->assertCount(5, array_unique($codes));
        $this->assertEquals(['JBT-001', 'JBT-002', 'JBT-003', 'JBT-004', 'JBT-005'], $codes);
    }

    public function test_authorization_protection_for_non_admin_users(): void
    {
        // Regular user without permissions
        $regularUser = User::factory()->create([
            'email' => 'regular.user@test.com',
        ]);

        // Attempt to create business option should be forbidden (403)
        $resOption = $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/master/options', [
                'group' => 'parent_occupation',
                'name' => 'Unauthorized Option',
            ]);
        $resOption->assertForbidden();

        // Attempt to save code generator config should be forbidden (403)
        $resConfig = $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/code-generator/configs', [
                'entity_type' => 'student_nis',
                'format_pattern' => '{PREFIX}{SEQUENCE}',
                'padding_length' => 4,
            ]);
        $resConfig->assertForbidden();
    }

    public function test_active_business_option_validation_rule(): void
    {
        $activeOpt = BusinessOption::create([
            'group' => 'parent_occupation',
            'name' => 'Dokter Spesialis',
            'code' => 'DOKTER_SPESIALIS',
            'is_active' => true,
        ]);

        $inactiveOpt = BusinessOption::create([
            'group' => 'parent_occupation',
            'name' => 'Pekerjaan Kedaluwarsa',
            'code' => 'PEKERJAAN_KEDALUWARSA',
            'is_active' => false,
        ]);

        $rule = new \App\Rules\ActiveBusinessOption('parent_occupation');

        // 1. Active option passes
        $validatorActive = \Illuminate\Support\Facades\Validator::make(
            ['job' => 'Dokter Spesialis'],
            ['job' => [$rule]]
        );
        $this->assertTrue($validatorActive->passes());

        // 2. Inactive option fails
        $validatorInactive = \Illuminate\Support\Facades\Validator::make(
            ['job' => 'Pekerjaan Kedaluwarsa'],
            ['job' => [$rule]]
        );
        $this->assertTrue($validatorInactive->fails());

        // 3. Completely bogus option fails
        $validatorBogus = \Illuminate\Support\Facades\Validator::make(
            ['job' => 'Pekerjaan Fiktif 12345'],
            ['job' => [$rule]]
        );
        $this->assertTrue($validatorBogus->fails());
    }

    public function test_all_ten_code_generator_types_and_multi_tenant_unit_isolation(): void
    {
        $unit2 = EducationUnit::create([
            'code' => 'SDIT',
            'name' => 'SDIT Dar el-Iman',
            'level' => 'SD',
            'is_active' => true,
        ]);

        // 1. Verify multi-tenant unit isolation: Unit 1 and Unit 2 have separate independent sequences
        $nisUnit1_A = CodeGeneratorService::generate('student_nis', $this->unit->id);
        $nisUnit2_A = CodeGeneratorService::generate('student_nis', $unit2->id);
        $nisUnit1_B = CodeGeneratorService::generate('student_nis', $this->unit->id);
        $nisUnit2_B = CodeGeneratorService::generate('student_nis', $unit2->id);

        $this->assertStringContainsString('TKIT', $nisUnit1_A);
        $this->assertStringContainsString('SDIT', $nisUnit2_A);
        $this->assertStringEndsWith('0001', $nisUnit1_A);
        $this->assertStringEndsWith('0001', $nisUnit2_A);
        $this->assertStringEndsWith('0002', $nisUnit1_B);
        $this->assertStringEndsWith('0002', $nisUnit2_B);

        // 2. Verify all other 8 types generate valid, non-empty, sequential strings
        $generators = [
            'employee_niy' => CodeGeneratorService::generate('employee_niy', $this->unit->id),
            'class_code' => CodeGeneratorService::generate('class_code', null, null, ['extra' => 'TK']),
            'subject_code' => CodeGeneratorService::generate('subject_code', $this->unit->id),
            'position_code' => CodeGeneratorService::generate('position_code'),
            'modul_code' => CodeGeneratorService::generate('modul_code'),
            'document_number' => CodeGeneratorService::generate('document_number', $this->unit->id),
            'curriculum_code' => CodeGeneratorService::generate('curriculum_code', $this->unit->id, null, ['extra' => 'SIT']),
            'permit_number' => CodeGeneratorService::generate('permit_number', $this->unit->id),
            'unit_code' => CodeGeneratorService::generate('unit_code', null, null, ['extra' => 'UP']),
        ];

        foreach ($generators as $type => $generatedCode) {
            $this->assertNotEmpty($generatedCode, "Generator for {$type} should produce a non-empty string.");
            $this->assertFalse(str_contains($generatedCode, '{'), "Generated code for {$type} should not have unresolved tokens.");
        }
    }

    public function test_empty_database_dropdown_returns_empty_collection_not_static_fallback(): void
    {
        // When business_options is empty for a group
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/master/options/dropdown/non_existent_group');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [],
            ]);

        // When transportation_modes is empty
        $responseTransport = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/master/moda-transportasi/dropdown');

        $responseTransport->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [],
            ]);
    }

    public function test_form_requests_strictly_enforce_active_business_options(): void
    {
        // Seed active options
        BusinessOption::create([
            'group' => 'student_status',
            'name' => 'Aktif',
            'code' => 'aktif',
            'is_active' => true,
        ]);
        BusinessOption::create([
            'group' => 'student_status',
            'name' => 'Nonaktif Permanen',
            'code' => 'nonaktif_permanen',
            'is_active' => false,
        ]);
        BusinessOption::create([
            'group' => 'parent_occupation',
            'name' => 'Arsitek',
            'code' => 'arsitek',
            'is_active' => true,
        ]);
        BusinessOption::create([
            'group' => 'employee_status',
            'name' => 'Guru Tetap',
            'code' => 'guru_tetap',
            'is_active' => true,
        ]);
        BusinessOption::create([
            'group' => 'employee_status',
            'name' => 'Status Kedaluwarsa',
            'code' => 'status_kedaluwarsa',
            'is_active' => false,
        ]);

        // 1. Student request with inactive student_status fails
        $payloadInactiveStatus = [
            'unit_id' => $this->unit->id,
            'nis' => '12345678',
            'full_name' => 'Siswa Uji Coba',
            'gender' => 'male',
            'metadata' => [
                'status_siswa' => 'Nonaktif Permanen',
            ],
        ];
        $resInactive = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/students', $payloadInactiveStatus);
        $resInactive->assertStatus(422)
            ->assertJsonValidationErrors(['metadata.status_siswa']);

        // 2. Student request with inactive parent_occupation fails
        $payloadInactiveJob = [
            'unit_id' => $this->unit->id,
            'nis' => '12345679',
            'full_name' => 'Siswa Uji Coba 2',
            'gender' => 'female',
            'metadata' => [
                'status_siswa' => 'Aktif',
                'pekerjaan_ayah' => 'Pekerjaan Palsu Tak Terdaftar',
            ],
        ];
        $resInactiveJob = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/students', $payloadInactiveJob);
        $resInactiveJob->assertStatus(422)
            ->assertJsonValidationErrors(['metadata.pekerjaan_ayah']);

        // 3. Employee request with inactive status_pegawai fails
        $payloadInactiveEmp = [
            'unit_id' => $this->unit->id,
            'nama_lengkap' => 'Pegawai Uji Coba',
            'jenis_kelamin' => 'L',
            'status_pegawai' => 'Status Kedaluwarsa',
        ];
        $resInactiveEmp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/employees', $payloadInactiveEmp);
        $resInactiveEmp->assertStatus(422)
            ->assertJsonValidationErrors(['status_pegawai']);

        // 4. Employee request with active status_pegawai passes validation
        $payloadActiveEmp = [
            'unit_id' => $this->unit->id,
            'nama_lengkap' => 'Pegawai Uji Coba Valid',
            'jenis_kelamin' => 'L',
            'status_pegawai' => 'Guru Tetap',
        ];
        $resActiveEmp = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/employees', $payloadActiveEmp);
        $resActiveEmp->assertStatus(201);
    }
}
