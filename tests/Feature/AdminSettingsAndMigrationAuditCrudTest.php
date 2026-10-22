<?php

namespace Tests\Feature;

use App\Models\BusinessOption;
use App\Models\CodeSequence;
use App\Models\EducationUnit;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Student;
use App\Models\StudentStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSettingsAndMigrationAuditCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin.audit@simsit.test',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->regularUser = User::factory()->create([
            'email' => 'regular.user@simsit.test',
        ]);
    }

    /**
     * 1. Site Settings Mapbox Token & Dynamic Settings Test
     */
    public function test_site_settings_mapbox_token_protection_and_update(): void
    {
        // Setup initial site settings
        $setting = SiteSetting::current();
        $setting->update([
            'mapbox_token' => 'pk.initial-test-token-value',
            'bank_name' => 'Bank Syariah Indonesia (BSI)',
            'bank_account_number' => '777-1234-567',
            'bank_account_name' => 'Yayasan Dar El-Iman Padang',
        ]);

        // Unauthenticated or non-admin cannot view mapbox_token
        $publicRes = $this->getJson('/api/site-settings');
        $publicRes->assertStatus(200);
        $this->assertArrayNotHasKey('mapbox_token', $publicRes->json('data'));

        $regularRes = $this->actingAs($this->regularUser, 'sanctum')->getJson('/api/site-settings');
        $regularRes->assertStatus(200);
        $this->assertArrayNotHasKey('mapbox_token', $regularRes->json('data'));

        // Authorized admin can view mapbox_token
        $adminRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/site-settings');
        $adminRes->assertStatus(200);
        $this->assertEquals('pk.initial-test-token-value', $adminRes->json('data.mapbox_token'));
        $this->assertEquals('Bank Syariah Indonesia (BSI)', $adminRes->json('data.bank_name'));

        // Update site settings including Mapbox token
        $updatePayload = [
            'application_name' => 'SIMSIT Dar El-Iman',
            'school_name' => 'Yayasan Dar El-Iman',
            'logo_text' => 'SIT',
            'header_style' => 'light',
            'header_sticky' => true,
            'sidebar_style' => 'light',
            'sidebar_position' => 'left',
            'sidebar_collapsed' => false,
            'template' => 'modern',
            'sidebar_color' => '#0E5C44',
            'sidebar_accent_color' => '#3FBF75',
            'body_color' => '#F7F9FC',
            'header_color' => '#FFFFFF',
            'mapbox_token' => 'pk.updated-secure-mapbox-token',
            'bank_name' => 'Bank Muamalat Indonesia',
            'bank_account_number' => '123-4567-890',
            'bank_account_name' => 'Yayasan Dar El-Iman Utama',
            'card_rules_student' => '1. Wajib dibawa saat KBM.',
            'card_rules_employee' => '1. Wajib dibawa saat jam kerja.',
            'card_footer_motto' => 'Berakhlak Mulia',
        ];

        $updateRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/site-settings', $updatePayload);
        $updateRes->assertStatus(200);
        $this->assertEquals('pk.updated-secure-mapbox-token', $updateRes->json('data.mapbox_token'));
        $this->assertEquals('Bank Muamalat Indonesia', $updateRes->json('data.bank_name'));

        // Verify database persistence
        $this->assertDatabaseHas('site_settings', [
            'mapbox_token' => 'pk.updated-secure-mapbox-token',
            'bank_name' => 'Bank Muamalat Indonesia',
        ]);
    }

    /**
     * 2. Business Options CRUD & Protection of Referenced Data
     */
    public function test_business_options_crud_and_in_use_deletion_prevention(): void
    {
        // Create an option
        $createRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/master/options', [
            'group' => 'parent_occupation',
            'name' => 'Arsitek Bangunan',
            'code' => 'arsitek_bangunan',
            'order' => 10,
            'is_active' => true,
        ]);
        $createRes->assertStatus(201);
        $optId = $createRes->json('data.id');

        // Read option
        $getRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson("/api/master/options/{$optId}");
        $getRes->assertStatus(200);
        $this->assertEquals('Arsitek Bangunan', $getRes->json('data.name'));

        // Update option
        $updateRes = $this->actingAs($this->superAdmin, 'sanctum')->putJson("/api/master/options/{$optId}", [
            'name' => 'Arsitek & Desainer',
            'code' => 'arsitek_desainer',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('Arsitek & Desainer', $updateRes->json('data.name'));

        // If referenced in active student metadata, deletion should be prevented
        Student::factory()->create([
            'nis' => '1001',
            'full_name' => 'Ahmad Siswa Test',
            'gender' => 'male',
            'is_active' => true,
            'metadata' => [
                'parent_occupation' => 'arsitek_desainer',
            ],
        ]);

        $deleteAttempt = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/master/options/{$optId}");
        $deleteAttempt->assertStatus(422);
        $this->assertStringContainsString('masih direferensikan dalam data aktif', $deleteAttempt->json('message'));

        // Remove the student reference, now deletion succeeds (soft delete)
        Student::where('nis', '1001')->forceDelete();
        $deleteSuccess = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/master/options/{$optId}");
        $deleteSuccess->assertStatus(200);
        $this->assertSoftDeleted('business_options', ['id' => $optId]);
    }

    /**
     * 3. Code Sequences Configs & Controlled Preview
     */
    public function test_code_sequences_configs_and_controlled_preview(): void
    {
        $configsRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/code-generator/configs');
        $configsRes->assertStatus(200);
        $this->assertIsArray($configsRes->json('data'));

        // Save a format configuration
        $saveRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/code-generator/configs', [
            'entity_type' => 'document_number',
            'prefix' => 'SK',
            'separator' => '/',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{PERIOD}{SEPARATOR}{SEQUENCE}',
            'is_active' => true,
        ]);
        $saveRes->assertStatus(200);

        // Preview should show generated code without incrementing sequence
        $previewRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/code-generator/preview?entity_type=document_number');
        $previewRes->assertStatus(200);
        $this->assertNotEmpty($previewRes->json('data.preview_code'));

        // Sequence counter must not be reset or corrupted when updating format config
        $seqRecord = CodeSequence::where('entity_type', 'document_number')->first();
        $this->assertNotNull($seqRecord);
        $seqRecord->update(['current_sequence' => 42]);

        $updateConfigRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/code-generator/configs', [
            'entity_type' => 'document_number',
            'prefix' => 'SK-REVISI',
            'separator' => '/',
            'padding_length' => 4,
            'format_pattern' => '{PREFIX}{SEPARATOR}{SEQUENCE}',
            'is_active' => true,
        ]);
        $updateConfigRes->assertStatus(200);
        $this->assertEquals(42, $seqRecord->fresh()->current_sequence);
    }

    /**
     * 4. Student Statuses Authorization & System Protection
     */
    public function test_student_statuses_authorization_and_system_protection(): void
    {
        // 1. Non-authorized user rejected
        $unauthRes = $this->actingAs($this->regularUser, 'sanctum')->postJson('/api/student-statuses', [
            'code' => 'uji_coba',
            'name' => 'Uji Coba',
        ]);
        $unauthRes->assertStatus(403);

        // 2. Authorized user can create custom student status
        $createRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/student-statuses', [
            'code' => 'pertukaran_pelajar',
            'name' => 'Pertukaran Pelajar',
            'badge_color' => 'blue',
            'order' => 10,
        ]);
        $createRes->assertStatus(201);
        $customStatusId = $createRes->json('data.id');

        // 3. Dropdown endpoint returns active statuses
        $dropdownRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/student-statuses/dropdown');
        $dropdownRes->assertStatus(200);
        $this->assertTrue(collect($dropdownRes->json('data'))->contains('code', 'pertukaran_pelajar'));

        // 4. Update custom status
        $updateRes = $this->actingAs($this->superAdmin, 'sanctum')->putJson("/api/student-statuses/{$customStatusId}", [
            'code' => 'pertukaran_pelajar',
            'name' => 'Pertukaran Pelajar Antar-Negara',
            'badge_color' => 'cyan',
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('Pertukaran Pelajar Antar-Negara', $updateRes->json('data.name'));

        // 5. System status protection against deletion (e.g. 'aktif')
        $systemStatus = StudentStatus::where('code', 'aktif')->first();
        if (! $systemStatus) {
            $systemStatus = StudentStatus::create([
                'code' => 'aktif',
                'name' => 'Aktif',
                'badge_color' => 'emerald',
            ]);
        }

        $deleteSystemRes = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/student-statuses/{$systemStatus->id}");
        $deleteSystemRes->assertStatus(422);
        $this->assertStringContainsString('dilindungi dan tidak dapat dihapus', $deleteSystemRes->json('message'));

        // 6. Delete custom status succeeds
        $deleteCustomRes = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/student-statuses/{$customStatusId}");
        $deleteCustomRes->assertStatus(200);
        $this->assertSoftDeleted('student_statuses', ['id' => $customStatusId]);
    }

    /**
     * 5. Role Levels & Governance Hierarchy Enforcement
     */
    public function test_role_levels_and_governance_hierarchy_enforcement(): void
    {
        // 1. Index roles returns level attribute
        $rolesRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/hak-akses/roles');
        $rolesRes->assertStatus(200);
        $roles = $rolesRes->json('data');
        $this->assertNotEmpty($roles);
        $superAdminRole = collect($roles)->firstWhere('name', 'Super Admin');
        $this->assertNotNull($superAdminRole);
        $this->assertEquals(1, $superAdminRole['level']);

        // 2. Create custom role with level
        $createRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/hak-akses/roles', [
            'name' => 'Koordinator Tahfizh Khusus',
            'level' => 5,
            'permissions' => [],
        ]);
        $createRes->assertStatus(201);
        $this->assertEquals(5, $createRes->json('data.level'));
        $roleId = $createRes->json('data.id');

        // 2b. Role level beyond 1-9 is rejected by validation
        $invalidLevelRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/hak-akses/roles', [
            'name' => 'Role Level Invalid',
            'level' => 10,
            'permissions' => [],
        ]);
        $invalidLevelRes->assertStatus(422);

        // 3. Update custom role level
        $updateRes = $this->actingAs($this->superAdmin, 'sanctum')->putJson("/api/hak-akses/roles/{$roleId}", [
            'name' => 'Koordinator Tahfizh Khusus',
            'level' => 4,
            'permissions' => [],
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals(4, $updateRes->json('data.level'));

        // 4. System canonical role 'Super Admin' cannot be demoted from level 1
        $demoteSuperAdminRes = $this->actingAs($this->superAdmin, 'sanctum')->putJson("/api/hak-akses/roles/{$superAdminRole['id']}", [
            'name' => 'Super Admin',
            'level' => 2,
            'permissions' => [],
        ]);
        $demoteSuperAdminRes->assertStatus(422);
        $this->assertStringContainsString('harus tetap tingkat 1', $demoteSuperAdminRes->json('message'));

        // 5. System canonical role cannot be renamed
        $renameSuperAdminRes = $this->actingAs($this->superAdmin, 'sanctum')->putJson("/api/hak-akses/roles/{$superAdminRole['id']}", [
            'name' => 'Custom Renamed Super Admin',
            'level' => 1,
            'permissions' => [],
        ]);
        $renameSuperAdminRes->assertStatus(422);

        // 6. System canonical role cannot be deleted
        $deleteSuperAdminRes = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/hak-akses/roles/{$superAdminRole['id']}");
        $deleteSuperAdminRes->assertStatus(422);
        $this->assertStringContainsString('dilindungi dan tidak dapat dihapus', $deleteSuperAdminRes->json('message'));

        // 7. Custom role can be deleted
        $deleteCustomRole = $this->actingAs($this->superAdmin, 'sanctum')->deleteJson("/api/hak-akses/roles/{$roleId}");
        $deleteCustomRole->assertStatus(200);
        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }
}
