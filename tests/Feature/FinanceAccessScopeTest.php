<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\FeeCategory;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unitA;
    protected EducationUnit $unitB;
    protected AcademicYear $academicYear;
    protected Semester $semester;
    protected FeeCategory $feeCategory;
    protected Kelas $kelasA;
    protected Kelas $kelasB;
    protected Student $studentA;
    protected Student $studentB;
    protected StudentBill $billA;
    protected StudentBill $billB;
    protected User $tuUserA;
    protected User $tuUserB;
    protected User $superAdmin;
    protected User $tuEmptyUnit;
    protected User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // 1. Roles
        $tuRole = Role::firstOrCreate(['name' => 'Tata Usaha', 'guard_name' => 'web']);
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $guruRole = Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        // 2. Units
        $this->unitA = EducationUnit::create([
            'code' => 'UNIT-A',
            'name' => 'SDIT 1 Dar el-Iman',
            'is_active' => true,
        ]);

        $this->unitB = EducationUnit::create([
            'code' => 'UNIT-B',
            'name' => 'SMPIT Dar el-Iman',
            'is_active' => true,
        ]);

        // 3. Academic Year, Semester & Fee Category
        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
        ]);

        $this->feeCategory = FeeCategory::create([
            'code' => 'SPP',
            'name' => 'SPP Bulanan',
            'is_recurring' => true,
            'default_amount' => 500000,
        ]);

        // 4. Classes
        $this->kelasA = Kelas::create([
            'kode_kelas' => 'K-SDIT-1A',
            'nama_kelas' => 'Kelas 1A',
            'jenjang' => 'SDIT',
            'tingkat' => '1',
            'unit_pendidikan_id' => $this->unitA->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'status' => 'Aktif',
        ]);

        $this->kelasB = Kelas::create([
            'kode_kelas' => 'K-SMPIT-7A',
            'nama_kelas' => 'Kelas 7A',
            'jenjang' => 'SMPIT',
            'tingkat' => '7',
            'unit_pendidikan_id' => $this->unitB->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'status' => 'Aktif',
        ]);

        // 5. Students
        $this->studentA = Student::create([
            'nis' => 'SDIT-001',
            'full_name' => 'Ahmad Santri A',
            'gender' => 'male',
            'unit_id' => $this->unitA->id,
            'kelas_id' => $this->kelasA->id,
            'is_active' => true,
        ]);

        $this->studentB = Student::create([
            'nis' => 'SMPIT-001',
            'full_name' => 'Budi Santri B',
            'gender' => 'male',
            'unit_id' => $this->unitB->id,
            'kelas_id' => $this->kelasB->id,
            'is_active' => true,
        ]);

        // 6. Bills
        $this->billA = StudentBill::create([
            'student_id' => $this->studentA->id,
            'fee_category_id' => $this->feeCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'title' => 'SPP Juli - Santri A',
            'amount' => 500000,
            'due_date' => '2024-07-10',
            'status' => 'unpaid',
        ]);

        $this->billB = StudentBill::create([
            'student_id' => $this->studentB->id,
            'fee_category_id' => $this->feeCategory->id,
            'academic_year_id' => $this->academicYear->id,
            'title' => 'SPP Juli - Santri B',
            'amount' => 750000,
            'due_date' => '2024-07-10',
            'status' => 'unpaid',
        ]);

        // 7. Users
        // TU Unit A
        $this->tuUserA = User::factory()->create(['email' => 'tu-unit-a@dareliman.test']);
        $this->tuUserA->assignRole($tuRole);
        Employee::create([
            'user_id' => $this->tuUserA->id,
            'unit_id' => $this->unitA->id,
            'nama_lengkap' => 'Staf TU Unit A',
            'niy' => 'TU-A-001',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ]);

        // TU Unit B
        $this->tuUserB = User::factory()->create(['email' => 'tu-unit-b@dareliman.test']);
        $this->tuUserB->assignRole($tuRole);
        Employee::create([
            'user_id' => $this->tuUserB->id,
            'unit_id' => $this->unitB->id,
            'nama_lengkap' => 'Staf TU Unit B',
            'niy' => 'TU-B-001',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ]);

        // TU tanpa unit
        $this->tuEmptyUnit = User::factory()->create(['email' => 'tu-nounit@dareliman.test']);
        $this->tuEmptyUnit->assignRole($tuRole);

        // Super Admin (Global Scope)
        $this->superAdmin = User::factory()->create(['email' => 'superadmin-finance@dareliman.test']);
        $this->superAdmin->assignRole($superAdminRole);

        // Guru (bukan role keuangan)
        $this->guruUser = User::factory()->create(['email' => 'guru-finance@dareliman.test']);
        $this->guruUser->assignRole($guruRole);
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $this->getJson('/api/finance/bills')->assertUnauthorized();
        $this->getJson('/api/finance/stats')->assertUnauthorized();
    }

    public function test_unauthorized_role_is_rejected_with_403(): void
    {
        $this->actingAs($this->guruUser, 'sanctum')
            ->getJson('/api/finance/bills')
            ->assertForbidden();
    }

    public function test_unit_tu_only_sees_bills_from_own_unit_in_index(): void
    {
        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/bills');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertEquals($this->billA->id, $items[0]['id']);
    }

    public function test_unit_tu_requesting_other_unit_in_index_is_forbidden(): void
    {
        // TU Unit A mencoba mengambil tagihan Unit B via parameter unit_id
        $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/bills?unit_id=' . $this->unitB->id)
            ->assertStatus(403);
    }

    public function test_unit_tu_only_calculates_stats_for_own_unit(): void
    {
        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_bills', 1);
        $this->assertEquals(500000, $response->json('data.total_nominal'));
    }

    public function test_unit_tu_requesting_other_unit_in_stats_is_forbidden(): void
    {
        $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/stats?unit_id=' . $this->unitB->id)
            ->assertStatus(403);
    }

    public function test_unit_tu_cannot_view_bill_detail_from_other_unit(): void
    {
        // TU Unit A mengakses detail tagihan Unit B
        $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/bills/' . $this->billB->id)
            ->assertStatus(403);
    }

    public function test_unit_tu_can_view_bill_detail_from_own_unit(): void
    {
        $this->actingAs($this->tuUserA, 'sanctum')
            ->getJson('/api/finance/bills/' . $this->billA->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->billA->id);
    }

    public function test_unit_tu_cannot_create_bill_for_other_unit_student(): void
    {
        $payload = [
            'mode' => 'single',
            'student_id' => $this->studentB->id,
            'fee_category_id' => $this->feeCategory->id,
            'title' => 'Tagihan Ilegal Lintas Unit',
            'amount' => 100000,
            'due_date' => '2024-07-20',
        ];

        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/finance/bills', $payload)
            ->assertStatus(403);
    }

    public function test_unit_tu_cannot_create_bulk_bills_for_other_unit_class(): void
    {
        $payload = [
            'mode' => 'bulk_class',
            'class_id' => $this->kelasB->id,
            'fee_category_id' => $this->feeCategory->id,
            'title' => 'Tagihan Rombel Ilegal Lintas Unit',
            'amount' => 100000,
            'due_date' => '2024-07-20',
        ];

        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/finance/bills', $payload)
            ->assertStatus(403);
    }

    public function test_unit_tu_can_create_bill_for_own_unit_student(): void
    {
        $payload = [
            'mode' => 'single',
            'student_id' => $this->studentA->id,
            'fee_category_id' => $this->feeCategory->id,
            'title' => 'Uang Ujian Unit A',
            'amount' => 150000,
            'due_date' => '2024-08-01',
        ];

        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/finance/bills', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_unit_tu_cannot_update_or_delete_bill_from_other_unit(): void
    {
        // Update tagihan Unit B oleh TU Unit A
        $this->actingAs($this->tuUserA, 'sanctum')
            ->putJson('/api/finance/bills/' . $this->billB->id, ['title' => 'Hacked Title'])
            ->assertStatus(403);

        // Delete tagihan Unit B oleh TU Unit A
        $this->actingAs($this->tuUserA, 'sanctum')
            ->deleteJson('/api/finance/bills/' . $this->billB->id)
            ->assertStatus(403);
    }

    public function test_unit_tu_cannot_record_payment_for_bill_from_other_unit(): void
    {
        $payload = [
            'paid_amount' => 500000,
            'payment_method' => 'TUNAI_KASIR',
            'paid_at' => '2024-07-11',
        ];

        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/finance/bills/' . $this->billB->id . '/payments', $payload)
            ->assertStatus(403);
    }

    public function test_unit_tu_can_record_payment_for_own_unit_bill(): void
    {
        $payload = [
            'paid_amount' => 500000,
            'payment_method' => 'TUNAI_KASIR',
            'paid_at' => '2024-07-11',
            'notes' => 'Pembayaran SPP lunas di kasir',
        ];

        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/finance/bills/' . $this->billA->id . '/payments', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bill_status', 'paid');
    }

    public function test_super_admin_has_global_access_to_all_units(): void
    {
        // Melihat seluruh tagihan lintas unit
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/finance/bills');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(2, $items);

        // Super Admin dapat melihat detail kedua tagihan
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/finance/bills/' . $this->billA->id)
            ->assertOk();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/finance/bills/' . $this->billB->id)
            ->assertOk();

        // Statistik global
        $stats = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/finance/stats');

        $stats->assertOk()
            ->assertJsonPath('data.total_bills', 2);
        $this->assertEquals(1250000, $stats->json('data.total_nominal'));
    }

    public function test_user_without_valid_unit_cannot_access_or_mutate_bills(): void
    {
        // TU yang tidak terafiliasi dengan unit apa pun menerima daftar kosong
        $indexResponse = $this->actingAs($this->tuEmptyUnit, 'sanctum')
            ->getJson('/api/finance/bills');

        $indexResponse->assertOk()
            ->assertJsonPath('data.data', []);

        // Ditolak saat mencoba menerbitkan tagihan
        $payload = [
            'mode' => 'single',
            'student_id' => $this->studentA->id,
            'fee_category_id' => $this->feeCategory->id,
            'title' => 'Tagihan TU Tanpa Unit',
            'amount' => 100000,
            'due_date' => '2024-08-01',
        ];

        $this->actingAs($this->tuEmptyUnit, 'sanctum')
            ->postJson('/api/finance/bills', $payload)
            ->assertStatus(403);
    }
}
