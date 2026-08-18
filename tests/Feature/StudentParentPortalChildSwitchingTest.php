<?php

namespace Tests\Feature;

use App\Enums\Mutabaah\RecordStatus;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentParentPortalChildSwitchingTest extends TestCase
{
    use RefreshDatabase;

    private function parentFixture(): array
    {
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Uji']);

        $childA = Student::create(['full_name' => 'Anak Satu', 'nis' => 'A001', 'gender' => 'male', 'is_active' => true]);
        $childB = Student::create(['full_name' => 'Anak Dua', 'nis' => 'A002', 'gender' => 'female', 'is_active' => true]);
        $foreign = Student::create(['full_name' => 'Anak Asing', 'nis' => 'A003', 'gender' => 'male', 'is_active' => true]);
        $childA->forceFill(['created_at' => now()->subMinutes(3)])->save();
        $childB->forceFill(['created_at' => now()->subMinutes(2)])->save();
        $foreign->forceFill(['created_at' => now()->subMinute()])->save();

        StudentParent::create(['student_id' => $childA->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => true]);
        StudentParent::create(['student_id' => $childB->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => false]);

        return [$user, $childA, $childB, $foreign];
    }

    public function test_parent_can_switch_context_between_linked_children(): void
    {
        [$user, $childA, $childB, $foreign] = $this->parentFixture();

        $this->actingAs($user)->getJson('/api/portal/profile?child_id='.$childA->id)
            ->assertOk()
            ->assertJsonPath('data.id', $childA->id);

        $this->actingAs($user)->getJson('/api/portal/profile?child_id='.$childB->id)
            ->assertOk()
            ->assertJsonPath('data.id', $childB->id);

        // X-Child-Id header juga dipakai untuk resolusi konteks anak.
        $this->actingAs($user)->getJson('/api/portal/profile', ['X-Child-Id' => $childB->id])
            ->assertOk()
            ->assertJsonPath('data.id', $childB->id);

        $this->actingAs($user)->getJson('/api/portal/children')
            ->assertOk()
            ->assertJsonPath('data.0.id', $childA->id)
            ->assertJsonPath('data.1.id', $childB->id)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    public function test_child_scoped_endpoints_reject_unlinked_child(): void
    {
        [$user, $childA, $childB, $foreign] = $this->parentFixture();

        $endpoints = [
            '/api/portal/profile',
            '/api/portal/schedules',
            '/api/portal/attendance',
            '/api/portal/grades',
            '/api/portal/materials',
            '/api/portal/assignments',
            '/api/portal/tahfizh',
            '/api/portal/mutabaah',
            '/api/portal/student-notes',
            '/api/portal/reports',
            '/api/portal/dashboard',
            '/api/portal/permissions',
            '/api/portal/exam-grids',
        ];

        foreach ($endpoints as $endpoint) {
            $this->actingAs($user)->getJson($endpoint.'?child_id='.$foreign->id)
                ->assertStatus(404, "Endpoint {$endpoint} harus 404 untuk anak tak terhubung.");
        }
    }

    public function test_submit_permission_scopes_record_to_selected_child(): void
    {
        [$user, $childA, $childB, $foreign] = $this->parentFixture();

        $this->actingAs($user)->postJson('/api/portal/permissions?child_id='.$childA->id, [
            'type' => 'Izin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])->assertOk()
            ->assertJsonPath('data.student_id', $childA->id);

        $this->actingAs($user)->postJson('/api/portal/permissions?child_id='.$childB->id, [
            'type' => 'Sakit',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'Demam',
        ])->assertOk()
            ->assertJsonPath('data.student_id', $childB->id);

        // Riwayat izin ter-scope per anak.
        $this->actingAs($user)->getJson('/api/portal/permissions?child_id='.$childA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.student_id', $childA->id);

        $this->actingAs($user)->getJson('/api/portal/permissions?child_id='.$childB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.student_id', $childB->id);

        $this->actingAs($user)->postJson('/api/portal/permissions?child_id='.$foreign->id, [
            'type' => 'Izin',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'reason' => 'Coba akses',
        ])->assertNotFound();
    }

    public function test_student_cannot_submit_mutabaah_without_active_assignment(): void
    {
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $user->id, 'full_name' => 'Wali Uji Mutabaah']);
        $student = Student::create(['full_name' => 'Siswa Uji', 'nis' => 'S001', 'gender' => 'male', 'parent_id' => $parent->id, 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertStatus(422);

        // Dengan assignment aktif + template beritem, checklist berhasil dibuat.
        $unit = EducationUnit::create(['name' => 'Unit Utama', 'code' => 'UT']);
        $student->update(['unit_id' => $unit->id]);
        $template =         MutabaahTemplate::create([
            'code' => 'TM-1',
            'name' => 'Template Harian',
            'education_unit_id' => $unit->id,
            'status' => RecordStatus::Active,
        ]);

        MutabaahSupervisorAssignment::create([
            'employee_id' => Employee::create(['niy' => 'NIY-001', 'nama_lengkap' => 'Guru Wali', 'jenis_kelamin' => 'L'])->id,
            'supervisor_type' => 'wali_kelas',
            'education_unit_id' => $unit->id,
            'kelas_id' => $student->kelas_id,
            'rombel_id' => $student->rombel_id,
            'template_id' => $template->id,
            'academic_year_id' => $ayId = AcademicYear::create(['name' => '2025/2026', 'is_active' => true])->id,
            'semester_id' => Semester::create(['academic_year_id' => $ayId, 'name' => 'Genap', 'sequence' => 2, 'is_active' => true])->id,
            'start_date' => now()->subDays(1)->toDateString(),
            'end_date' => now()->addDays(1)->toDateString(),
            'status' => RecordStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->postJson('/api/portal/mutabaah?child_id='.$student->id)
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id);
    }

    public function test_parent_signature_detects_content_change_on_note(): void
    {
        [$user, $childA, $childB, $foreign] = $this->parentFixture();

        $teacher = Teacher::create(['full_name' => 'Guru Catatan', 'employee_number' => 'T001']);

        $note = StudentNote::create([
            'student_id' => $childA->id,
            'teacher_id' => $teacher->id,
            'title' => 'Catatan Awal',
            'content' => 'Perilaku baik di kelas.',
            'visible_to_parent' => true,
            'visible_to_student' => true,
            'category' => 'Akademik',
            'priority' => 'medium',
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->postJson('/api/portal/student-notes/'.$note->id.'/sign?child_id='.$childA->id, [
            'follow_up' => 'Akan dibicarakan di rumah.',
        ])->assertOk()
            ->assertJsonPath('data.signature_status', 'signed');

        // Isi diubah oleh guru → tanda tangan menjadi stale.
        $note->update(['content' => 'Perilaku baik di kelas. Mengalami peningkatan.']);

        $this->actingAs($user)->getJson('/api/portal/student-notes?child_id='.$childA->id)
            ->assertOk()
            ->assertJsonPath('data.data.0.signature_status', 'signed_updated');

        // Catatan anak lain tidak boleh diakses/ditandatangani.
        $foreignNote = StudentNote::create([
            'student_id' => $foreign->id,
            'teacher_id' => $teacher->id,
            'title' => 'Catatan Asing',
            'content' => 'Rahasia siswa lain.',
            'visible_to_parent' => true,
            'visible_to_student' => true,
            'category' => 'Akademik',
            'priority' => 'high',
            'date' => now()->toDateString(),
        ]);
        $this->actingAs($user)->postJson('/api/portal/student-notes/'.$foreignNote->id.'/sign?child_id='.$foreign->id)
            ->assertNotFound();
    }

    public function test_notifications_endpoint_is_stable_for_parent_without_records(): void
    {
        [$user, $childA, $childB, $foreign] = $this->parentFixture();

        $this->actingAs($user)->getJson('/api/portal/notifications?child_id='.$childA->id)
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
