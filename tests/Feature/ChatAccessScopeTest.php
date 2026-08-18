<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\PortalMessage;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 12 — SCOPE & SECURITY CHAT.
 *
 * Memastikan seluruh kanal chat (portal, teacher, employee) aman terhadap
 * akses silang: role middleware pada alias /api/chat/* dan /api/employee/chat/*,
 * penerima pesan portal harus guru yang terhubung dengan anak, guru hanya boleh
 * chat siswa yang diampu, direktori pegawai dibatasi unit kerja, dan payload
 * pesan divalidasi.
 */
class ChatAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $ay;

    private Semester $sem;

    private EducationUnit $unitA;

    private EducationUnit $unitB;

    private Employee $guruEmp;

    private User $guruUser;

    private Kelas $kelas;

    private Student $anak;

    private User $ortuUser;

    private ParentModel $ortu;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Alumni', 'guard_name' => 'web']);

        $this->ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $this->sem = Semester::create(['academic_year_id' => $this->ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);

        $this->unitA = EducationUnit::create(['name' => 'Unit A', 'code' => 'UA', 'is_active' => true]);
        $this->unitB = EducationUnit::create(['name' => 'Unit B', 'code' => 'UB', 'is_active' => true]);

        $this->guruUser = User::factory()->create(['name' => 'Guru Wali', 'email' => 'guru.chat@school.id']);
        $this->guruUser->assignRole('Guru');
        $this->guruEmp = Employee::create([
            'user_id' => $this->guruUser->id,
            'unit_id' => $this->unitA->id,
            'education_unit_id' => $this->unitA->id,
            'nama_lengkap' => 'Guru Wali M.Pd',
            'niy' => 'CH-0001',
            'status' => 'Aktif',
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unitA->id,
            'tahun_ajaran_id' => $this->ay->id,
            'semester_id' => $this->sem->id,
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'kode_kelas' => 'CH-7A',
            'nama_kelas' => '7A Chat',
            'wali_kelas_id' => $this->guruEmp->id,
            'status' => 'Aktif',
        ]);

        $this->anak = Student::create([
            'education_unit_id' => $this->unitA->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Anak Chat',
            'nis' => 'CH-1001',
            'gender' => 'male',
            'is_active' => true,
        ]);

        $this->ortuUser = User::factory()->create(['name' => 'Wali Chat', 'email' => 'ortu.chat@school.id']);
        $this->ortuUser->assignRole('Orang Tua');
        $this->ortu = ParentModel::create(['user_id' => $this->ortuUser->id, 'full_name' => 'Wali Chat']);
        StudentParent::create(['student_id' => $this->anak->id, 'parent_id' => $this->ortu->id, 'relationship_type' => 'guardian', 'is_primary' => true]);
    }

    public function test_chat_alias_routes_require_allowed_roles(): void
    {
        $outsider = User::factory()->create(['name' => 'Alumni Chat']);
        $outsider->assignRole('Alumni');

        $this->actingAs($outsider)->getJson('/api/chat/contacts')
            ->assertForbidden();

        $this->actingAs($outsider)->postJson('/api/chat/messages/'.$this->guruUser->id, [
            'message' => 'Coba akses',
        ])->assertForbidden();
    }

    public function test_employee_chat_routes_require_staff_roles(): void
    {
        $this->actingAs($this->ortuUser)->getJson('/api/employee/chat/contacts')
            ->assertForbidden();

        $this->actingAs($this->ortuUser)->postJson('/api/employee/chat/messages/'.$this->guruUser->id, [
            'message' => 'Coba akses',
        ])->assertForbidden();
    }

    public function test_student_cannot_read_other_student_conversation(): void
    {
        $anakLain = Student::create([
            'education_unit_id' => $this->unitA->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Anak Lain',
            'nis' => 'CH-1002',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $ortuLainUser = User::factory()->create(['email' => 'ortu.lain@school.id']);
        $ortuLainUser->assignRole('Orang Tua');
        $ortuLain = ParentModel::create(['user_id' => $ortuLainUser->id, 'full_name' => 'Wali Lain']);
        StudentParent::create(['student_id' => $anakLain->id, 'parent_id' => $ortuLain->id, 'relationship_type' => 'guardian', 'is_primary' => true]);

        PortalMessage::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'student_id' => $anakLain->id,
            'sender_user_id' => $ortuLainUser->id,
            'recipient_user_id' => $this->guruUser->id,
            'message' => 'Pesan rahasia anak lain.',
        ]);

        $siswaAUser = User::factory()->create(['email' => 'siswa.a@school.id']);
        $siswaAUser->assignRole('Siswa');
        Student::create([
            'user_id' => $siswaAUser->id,
            'education_unit_id' => $this->unitA->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Siswa A',
            'nis' => 'CH-1003',
            'gender' => 'male',
            'is_active' => true,
        ]);

        $this->actingAs($siswaAUser)
            ->getJson('/api/portal/chat/'.$this->guruUser->id)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['message' => 'Pesan rahasia anak lain.']);
    }

    public function test_parent_cannot_contact_unrelated_teacher(): void
    {
        $teacherLain = User::factory()->create(['email' => 'guru.lain@school.id']);
        $teacherLain->assignRole('Guru');

        $this->actingAs($this->ortuUser)
            ->postJson('/api/portal/chat/'.$teacherLain->id.'?child_id='.$this->anak->id, [
                'message' => 'Kepada guru tak terkait.',
            ])
            ->assertForbidden();

        $this->actingAs($this->ortuUser)
            ->getJson('/api/portal/chat/'.$teacherLain->id.'?child_id='.$this->anak->id)
            ->assertForbidden();
    }

    public function test_teacher_cannot_contact_unassigned_student(): void
    {
        $anakBedaKelas = Student::create([
            'education_unit_id' => $this->unitB->id,
            'full_name' => 'Anak Kelas Lain',
            'nis' => 'CH-2001',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $ortuBedaUser = User::factory()->create(['email' => 'ortu.beda@school.id']);
        $ortuBedaUser->assignRole('Orang Tua');
        $ortuBeda = ParentModel::create(['user_id' => $ortuBedaUser->id, 'full_name' => 'Wali Beda']);
        StudentParent::create(['student_id' => $anakBedaKelas->id, 'parent_id' => $ortuBeda->id, 'relationship_type' => 'guardian', 'is_primary' => true]);

        $this->actingAs($this->guruUser)
            ->postJson('/api/teacher/chat/parent/'.$ortuBedaUser->id.'/student/'.$anakBedaKelas->id, [
                'message' => 'Kepada siswa tak diampu.',
            ])
            ->assertForbidden();

        $this->actingAs($this->guruUser)
            ->getJson('/api/teacher/chat/parent/'.$ortuBedaUser->id.'/student/'.$anakBedaKelas->id)
            ->assertForbidden();
    }

    public function test_employee_directory_is_scoped_to_own_unit(): void
    {
        $pegawaiB = User::factory()->create(['name' => 'Pegawai B', 'email' => 'pegawai.b@school.id']);
        $pegawaiB->assignRole('Guru');
        Employee::create([
            'user_id' => $pegawaiB->id,
            'unit_id' => $this->unitB->id,
            'education_unit_id' => $this->unitB->id,
            'nama_lengkap' => 'Pegawai Unit B',
            'niy' => 'CH-9002',
            'status' => 'Aktif',
            'is_active' => true,
        ]);

        $pegawaiA = User::factory()->create(['name' => 'Pegawai A', 'email' => 'pegawai.a@school.id']);
        $pegawaiA->assignRole('Guru');
        Employee::create([
            'user_id' => $pegawaiA->id,
            'unit_id' => $this->unitA->id,
            'education_unit_id' => $this->unitA->id,
            'nama_lengkap' => 'Pegawai Unit A',
            'niy' => 'CH-9001',
            'status' => 'Aktif',
            'is_active' => true,
        ]);

        $this->actingAs($pegawaiA)
            ->getJson('/api/employee/chat/contacts')
            ->assertOk()
            ->assertJsonFragment(['nip_niy' => 'CH-0001'])
            ->assertJsonMissing(['nip_niy' => 'CH-9002']);
    }

    public function test_employee_cannot_message_non_employee_user(): void
    {
        $this->actingAs($this->guruUser)
            ->postJson('/api/employee/chat/messages/'.$this->ortuUser->id, [
                'message' => 'Ke akun non-pegawai.',
            ])
            ->assertForbidden();
    }

    public function test_chat_message_payload_is_validated(): void
    {
        $this->actingAs($this->ortuUser)
            ->postJson('/api/portal/chat/'.$this->guruUser->id.'?child_id='.$this->anak->id, [
                'message' => '   ',
            ])
            ->assertUnprocessable();

        $this->actingAs($this->ortuUser)
            ->postJson('/api/portal/chat/'.$this->guruUser->id.'?child_id='.$this->anak->id, [
                'message' => str_repeat('A', 5001),
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('portal_messages', 0);
    }
}
