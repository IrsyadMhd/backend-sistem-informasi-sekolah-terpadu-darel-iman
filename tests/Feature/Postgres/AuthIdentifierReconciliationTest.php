<?php

namespace Tests\Feature\Postgres;

use App\Models\LoginEvent;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PostgreSQL Multi-Identifier Auth Reconciliation (requirements #19-#25)
 *
 * Employee  : NIY / No. HP (raw, 62, +62) / Email  -> satu akun, role dari DB.
 * Parent    : No. HP / NIK / father_nik / mother_nik / NIS anak -> rumah tangga penuh.
 * Student   : NIS -> hanya dirinya sendiri (self scope), sibling = 404.
 *
 * Hanya berjalan di driver pgsql (konvensi suite Postgres).
 */
class AuthIdentifierReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL certification suite hanya berjalan di driver pgsql.');
        }

        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    private function loginEmployee(string $identifier, string $password = 'Password123!'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v2/auth/login/employee', [
            'identifier' => $identifier,
            'password' => $password,
        ]);
    }

    private function loginParentStudent(string $portalType, string $identifier, string $password = 'Password123!'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v2/auth/login/parent-student', [
            'portal_type' => $portalType,
            'identifier' => $identifier,
            'password' => $password,
        ]);
    }

    /** #19 Employee login via NIY, Email, dan No. HP (3 format). Role dari DB. */
    public function test_employee_login_niy_email_phone_variants(): void
    {
        foreach (['NIY-201101001', 'elvisyam@dareliman.sch.id', '08116601001', '628116601001', '+628116601001'] as $identifier) {
            $response = $this->loginEmployee($identifier);

            $response->assertStatus(200);
            $data = $response->json();

            $this->assertSame('employee', $data['portal']);
            $this->assertNotEmpty($data['token']);
            $this->assertSame('elvisyam@dareliman.sch.id', $data['user']['email']);
            $this->assertContains('Yayasan', $data['user']['roles'], "role harus berasal dari DB untuk identifier {$identifier}");
        }
    }

    /** #20a Pegawai tanpa linked User ditolak; alasan internal tidak bocor ke client. */
    public function test_employee_account_not_linked_rejected_generic(): void
    {
        $employee = \App\Models\Employee::where('niy', 'NIY-201101001')->first();
        $this->assertNotNull($employee);
        $employee->update(['user_id' => null]);

        $response = $this->loginEmployee('NIY-201101001');

        $response->assertStatus(401);
        $this->assertGenericMessageOnly($response->json('message'));
        $this->assertSame('ACCOUNT_NOT_LINKED', $this->latestFailureReason('employee'));
    }

    /** #20b Password salah ditolak; alasan internal tidak bocor. */
    public function test_employee_wrong_password_rejected_generic(): void
    {
        $response = $this->loginEmployee('NIY-201101001', 'wrong-password');

        $response->assertStatus(401);
        $this->assertGenericMessageOnly($response->json('message'));
        $this->assertSame('PASSWORD_INVALID', $this->latestFailureReason('employee'));
    }

    /** #20c Pegawai tidak aktif ditolak. */
    public function test_employee_inactive_rejected(): void
    {
        $response = $this->loginEmployee('NIY-202306115');

        $response->assertStatus(401);
        $this->assertGenericMessageOnly($response->json('message'));
        $this->assertSame('ACCOUNT_INACTIVE', $this->latestFailureReason('employee'));
    }

    /** #21 Parent login via No. HP, NIK, father_nik, mother_nik, dan NIS anak -> rumah tangga yang sama. */
    public function test_parent_login_all_identifiers_resolve_same_household(): void
    {
        // father_nik difixture bernilai unik agar cabang resolusi benar-benar teruji.
        $parent = ParentModel::where('full_name', 'Ahmad Fauzi')->first();
        $this->assertNotNull($parent);
        $parent->update(['father_nik' => '1201099999999']);

        $identifiers = ['081200010001', '1201010101010001', '1201099999999', '1201010101010002', '23001'];

        foreach ($identifiers as $identifier) {
            $response = $this->loginParentStudent('parent', $identifier);

            $response->assertStatus(200);
            $data = $response->json();

            $this->assertSame('parent', $data['portal']);
            $this->assertSame('Ahmad Fauzi', $data['parent']['name'], "parent dari identifier {$identifier}");
            $this->assertSame('Ahmad Fauzi', $data['user']['name']);

            $children = collect($data['children'])->pluck('nis')->sort()->values()->all();
            $this->assertSame(['23001', '23004', '23005'], $children, "anak-anak dari identifier {$identifier}");
        }
    }

    /** #22 Parent multi-child: daftar anak + child switcher dihormati (child_id & X-Child-Id). */
    public function test_parent_multi_child_switcher_honored(): void
    {
        $login = $this->loginParentStudent('parent', '23001');
        $token = $login->json('token');

        $children = $this->withToken($token)->getJson('/api/portal/children');
        $children->assertStatus(200);
        $this->assertCount(3, $children->json('data'));

        $childA = Student::where('nis', '23001')->first();
        $childB = Student::where('nis', '23004')->first();

        $this->assertNotNull($childA);
        $this->assertNotNull($childB);

        $gradesA = $this->withToken($token)->getJson('/api/portal/grades?child_id=' . $childA->id);
        $gradesA->assertStatus(200);
        $this->assertNotEmpty($gradesA->json('data'));
        $this->assertSame($childA->id, $gradesA->json('data.0.student_id'), 'child_id memilih anak A');

        $gradesB = $this->withToken($token)->getJson('/api/portal/grades?child_id=' . $childB->id);
        $gradesB->assertStatus(200);
        $this->assertNotEmpty($gradesB->json('data'));
        $this->assertSame($childB->id, $gradesB->json('data.0.student_id'), 'child_id memilih anak B');

        $gradesHeader = $this->withToken($token)->withHeader('X-Child-Id', $childB->id)->getJson('/api/portal/grades');
        $gradesHeader->assertStatus(200);
        $this->assertSame($childB->id, $gradesHeader->json('data.0.student_id'), 'header X-Child-Id memilih anak B');
    }

    /** #23 Student login via NIS -> hanya dirinya (self scope), portal student. */
    public function test_student_login_self_scope(): void
    {
        $response = $this->loginParentStudent('student', '23001');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertSame('student', $data['portal']);
        $this->assertSame('23001', $data['student']['nis']);
        $this->assertContains('Siswa', $data['user']['roles']);

        $children = $this->withToken($data['token'])->getJson('/api/portal/children');
        $children->assertStatus(200);
        $this->assertCount(1, $children->json('data'));
        $this->assertSame('23001', $children->json('data.0.nis'), 'student hanya melihat dirinya sendiri');
    }

    /** #24 Student tidak dapat mengakses sibling / scope rumah tangga (fail-closed 404). */
    public function test_student_cannot_access_sibling_fail_closed(): void
    {
        $student = Student::where('nis', '23001')->first();
        $sibling = Student::where('nis', '23004')->first();

        $login = $this->loginParentStudent('student', '23001');
        $token = $login->json('token');

        $siblingAccess = $this->withToken($token)->getJson('/api/portal/grades?child_id=' . $sibling->id);
        $siblingAccess->assertStatus(404);
        $this->assertSame(false, $siblingAccess->json('success'));

        $ownAccess = $this->withToken($token)->getJson('/api/portal/grades?child_id=' . $student->id);
        $ownAccess->assertStatus(200);
        $this->assertNotEmpty($ownAccess->json('data'));
    }

    /** #25 Normalisasi nomor HP: 0812xxx / 62812xxx / +62812xxx -> akun yang sama. */
    public function test_phone_normalization_variants_same_account(): void
    {
        $parentVariants = ['081200010001', '6281200010001', '+6281200010001'];
        $parentUserIds = [];

        foreach ($parentVariants as $variant) {
            $response = $this->loginParentStudent('parent', $variant);
            $response->assertStatus(200);
            $parentUserIds[] = $response->json('user.id');
        }

        $this->assertCount(1, array_unique($parentUserIds), 'varian HP parent harus menunjuk satu akun');

        $employeeVariants = ['08116601001', '628116601001', '+628116601001'];
        $employeeUserIds = [];

        foreach ($employeeVariants as $variant) {
            $response = $this->loginEmployee($variant);
            $response->assertStatus(200);
            $employeeUserIds[] = $response->json('user.id');
        }

        $this->assertCount(1, array_unique($employeeUserIds), 'varian HP pegawai harus menunjuk satu akun');
    }

    private function latestFailureReason(string $portalType): ?string
    {
        return LoginEvent::query()
            ->where('portal_type', $portalType)
            ->where('status', 'failed')
            ->latest('created_at')
            ->value('failure_reason');
    }

    private function assertGenericMessageOnly(?string $message): void
    {
        $this->assertNotEmpty($message, 'pesan error wajib ada');
        foreach (['IDENTIFIER_NOT_FOUND', 'PASSWORD_INVALID', 'ACCOUNT_NOT_LINKED', 'ACCOUNT_INACTIVE', 'ROLE_NOT_ASSIGNED', 'PARENT_NOT_LINKED', 'STUDENT_NOT_LINKED', 'STUDENT_NOT_ACTIVE', 'PORTAL_ACCESS_DENIED'] as $reason) {
            $this->assertStringNotContainsString($reason, (string) $message, 'alasan internal tidak boleh bocor ke client');
        }
    }
}
