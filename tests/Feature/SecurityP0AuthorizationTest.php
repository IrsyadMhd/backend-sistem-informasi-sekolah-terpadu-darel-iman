<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DeleteRequest;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\AccessScopeService;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityP0AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    /**
     * 1. Unauthenticated request to protected endpoints must be rejected with 401.
     */
    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $this->getJson('/api/v1/navigation/modules')->assertUnauthorized();
        $this->getJson('/api/hak-akses/users')->assertUnauthorized();
        $this->getJson('/api/v2/approval/delete-requests')->assertUnauthorized();
        $this->getJson('/api/portal/children')->assertUnauthorized();
    }

    /**
     * 2. Authenticated user without hak_akses permission cannot access hak_akses endpoints (403).
     */
    public function test_user_without_hak_akses_permission_is_forbidden(): void
    {
        $siswa = User::where('email', 'siswa@dareliman.sch.id')->firstOrFail();
        Sanctum::actingAs($siswa);

        $this->getJson('/api/hak-akses/users')->assertForbidden();
        $this->getJson('/api/hak-akses/roles')->assertForbidden();
        $this->getJson('/api/hak-akses/permissions')->assertForbidden();
    }

    /**
     * 3. Role Guru with master.view permission alone is strictly forbidden from hak_akses endpoints.
     */
    public function test_user_with_master_view_alone_cannot_access_hak_akses_endpoints(): void
    {
        $guru = User::where('email', 'guru@dareliman.sch.id')->firstOrFail();
        Sanctum::actingAs($guru);

        $this->assertTrue($guru->can('master.view'));
        $this->assertFalse($guru->can('sistem.hak_akses'));

        $this->getJson('/api/hak-akses/users')->assertForbidden();
        $this->getJson('/api/hak-akses/roles')->assertForbidden();
        $this->getJson('/api/hak-akses/stats')->assertForbidden();
    }

    /**
     * 4. Unit Manager (Kepala Sekolah) cannot access or update users in a different unit (Cross-Unit Prevention).
     */
    public function test_unit_manager_cannot_update_user_in_different_unit(): void
    {
        $unitA = EducationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = EducationUnit::factory()->create(['name' => 'Unit B']);

        $kepsek = User::where('email', 'kepsek@dareliman.sch.id')->firstOrFail();
        $kepsek->employee()->update(['unit_id' => $unitA->id]);

        $targetUserInUnitB = User::where('email', 'guru@dareliman.sch.id')->firstOrFail();
        $targetUserInUnitB->employee()->update(['unit_id' => $unitB->id]);

        Sanctum::actingAs($kepsek);

        // Attempting to update a user in Unit B by Kepsek Unit A must be forbidden
        $this->putJson("/api/hak-akses/users/{$targetUserInUnitB->id}", [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.test',
            'role' => 'Guru',
        ])->assertForbidden();

        // Attempting to reset password for a user in Unit B must be forbidden
        $this->putJson("/api/hak-akses/users/{$targetUserInUnitB->id}/password", [
            'password' => 'HackedPass@2026!',
            'password_confirmation' => 'HackedPass@2026!',
        ])->assertForbidden();
    }

    /**
     * 5. Unit Manager cannot assign global roles like Super Admin (Privilege Escalation Prevention).
     */
    public function test_unit_manager_cannot_assign_global_role(): void
    {
        $unit = EducationUnit::factory()->create(['name' => 'Unit Test']);
        $kepsek = User::where('email', 'kepsek@dareliman.sch.id')->firstOrFail();
        $kepsek->employee()->update(['unit_id' => $unit->id]);

        $guru = User::where('email', 'guru@dareliman.sch.id')->firstOrFail();
        $guru->employee()->update(['unit_id' => $unit->id]);

        Sanctum::actingAs($kepsek);

        $this->putJson("/api/hak-akses/users/{$guru->id}", [
            'name' => $guru->name,
            'email' => $guru->email,
            'role' => 'Super Admin',
        ])->assertForbidden();
    }

    /**
     * 6. IDOR Prevention: Parent cannot access foreign child data via foreign UUID.
     */
    public function test_parent_cannot_access_foreign_child_data_via_foreign_uuid(): void
    {
        $unit = EducationUnit::first() ?? EducationUnit::factory()->create();

        $userParentA = User::where('email', 'orangtua@dareliman.sch.id')->firstOrFail();
        $parentA = ParentModel::where('user_id', $userParentA->id)->firstOrFail();

        $childA = Student::firstOrCreate(
            ['nis' => 'CHILD-A-001'],
            [
                'full_name' => 'Anak Kandung Parent A',
                'parent_id' => $parentA->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'gender' => 'male',
            ]
        );

        $otherUser = User::factory()->create(['email' => 'other.parent@example.test']);
        $parentB = ParentModel::create([
            'user_id' => $otherUser->id,
            'full_name' => 'Orang Tua B',
            'nik' => '1371999999999999',
            'phone' => '081299998888',
            'email' => 'other.parent@example.test',
        ]);

        $childB = Student::create([
            'nis' => 'CHILD-B-999',
            'full_name' => 'Anak Orang Lain',
            'parent_id' => $parentB->id,
            'unit_id' => $unit->id,
            'is_active' => true,
            'gender' => 'female',
        ]);

        Sanctum::actingAs($userParentA);

        // Accessing childA (own child) should succeed
        $responseOwn = $this->withHeader('X-Child-Id', $childA->id)
            ->getJson('/api/portal/schedules');
        $responseOwn->assertOk();

        // Accessing childB (foreign child UUID) must be rejected with 404/denied
        $responseForeign = $this->withHeader('X-Child-Id', $childB->id)
            ->getJson('/api/portal/schedules');
        $this->assertEquals(404, $responseForeign->status());
    }

    /**
     * 7. Unauthorized user cannot approve or reject delete requests.
     */
    public function test_unauthorized_user_cannot_approve_or_reject_delete_requests(): void
    {
        $guru = User::where('email', 'guru@dareliman.sch.id')->firstOrFail();
        Sanctum::actingAs($guru);

        $fakeId = (string) Str::uuid();

        $this->postJson("/api/v2/approval/delete-requests/{$fakeId}/approve")
            ->assertForbidden();

        $this->postJson("/api/v2/approval/delete-requests/{$fakeId}/reject", [
            'rejection_reason' => 'Alasan tolak sembarangan',
        ])->assertForbidden();
    }

    /**
     * 8. student.view_all permission does not grant visibility over all delete requests.
     */
    public function test_student_view_all_permission_does_not_grant_delete_requests_approval_visibility(): void
    {
        $superAdmin = User::where('email', 'superadmin@dareliman.sch.id')->firstOrFail();

        // Create a test delete request requested by Super Admin
        $req = DeleteRequest::create([
            'target_table' => 'students',
            'target_id' => (string) Str::uuid(),
            'target_label' => 'Test Siswa',
            'requested_by' => $superAdmin->id,
            'reason' => 'Testing deletion request',
            'status' => 'pending',
        ]);

        // User with ONLY student.view_all (not Super Admin, not superadmin.delete.approve)
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('student.view_all');

        Sanctum::actingAs($viewer);

        $response = $this->getJson('/api/v2/approval/delete-requests')->assertOk();
        $items = $response->json('data.data');

        // Viewer should NOT see Super Admin's request because viewer cannot approve all
        $itemIds = collect($items)->pluck('id');
        $this->assertFalse($itemIds->contains($req->id));
    }

    /**
     * 9. Portal users (Siswa/Orang Tua) cannot submit system data deletion requests.
     */
    public function test_portal_users_cannot_submit_system_delete_requests(): void
    {
        $siswa = User::where('email', 'siswa@dareliman.sch.id')->firstOrFail();
        Sanctum::actingAs($siswa);

        $this->postJson('/api/v2/approval/delete-requests', [
            'target_table' => 'students',
            'target_id' => (string) Str::uuid(),
            'reason' => 'Siswa minta hapus data',
        ])->assertForbidden();
    }

    /**
     * 10. AccessScopeService applyStrictUnitScope blocks foreign unit parameters.
     */
    public function test_access_scope_blocks_foreign_unit_parameters(): void
    {
        $unitA = EducationUnit::factory()->create();
        $unitB = EducationUnit::factory()->create();

        $kepsek = User::where('email', 'kepsek@dareliman.sch.id')->firstOrFail();
        $kepsek->employee()->update(['unit_id' => $unitA->id]);

        $accessScope = app(AccessScopeService::class);

        // Accessing accessible unit succeeds
        $queryA = Student::query();
        $resultQueryA = $accessScope->applyStrictUnitScope($queryA, $kepsek, $unitA->id);
        $this->assertNotNull($resultQueryA);

        // Accessing foreign unit throws 403 HttpException
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $queryB = Student::query();
        $accessScope->applyStrictUnitScope($queryB, $kepsek, $unitB->id);
    }

    /**
     * 11. Requester cannot approve their own delete request (Separation of Duties).
     */
    public function test_requester_cannot_approve_own_delete_request_enforces_sod(): void
    {
        $superAdmin = User::where('email', 'superadmin@dareliman.sch.id')->firstOrFail();

        $req = DeleteRequest::create([
            'target_table' => 'students',
            'target_id' => (string) Str::uuid(),
            'target_label' => 'Test SoD',
            'requested_by' => $superAdmin->id,
            'reason' => 'Pengujian SoD pemohon tidak boleh menyetujui',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->postJson("/api/v2/approval/delete-requests/{$req->id}/approve");
        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Pemohon tidak dapat menyetujui permintaannya sendiri.',
            ]);
    }
}
