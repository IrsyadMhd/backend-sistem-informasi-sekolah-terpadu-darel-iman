<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\MasterKurikulum;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherPortalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Helper to set up an isolated teaching environment with schedules and kurikulum.
     */
    private function createTeachingContext(array $overrides = []): array
    {
        $rand = Str::lower(Str::random(6));

        $unit = EducationUnit::create([
            'name' => ($overrides['unit_name'] ?? 'SD IT Al-Hikmah') . " {$rand}",
            'code' => ($overrides['unit_code'] ?? 'SDIT') . "-{$rand}",
            'level' => $overrides['unit_level'] ?? 'SD',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::create([
            'name' => ($overrides['academic_year_name'] ?? '2026/2027') . " {$rand}",
            'code' => ($overrides['academic_year_code'] ?? '2026-2027') . "-{$rand}",
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'tahun' => '2026/2027',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => ($overrides['kurikulum_code'] ?? 'KM-SD') . "-{$rand}",
            'nama_kurikulum' => ($overrides['kurikulum_name'] ?? 'Kurikulum Merdeka SD') . " {$rand}",
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $overrides['kurikulum_unit_id'] ?? $unit->id,
            'jenjang' => $overrides['kurikulum_jenjang'] ?? ($overrides['unit_level'] ?? 'SD'),
            'tahun_ajaran_id' => $academicYear->id,
            'tanggal_mulai' => '2026-07-01',
            'status' => true,
        ]);

        $subject = Subject::create([
            'name' => ($overrides['subject_name'] ?? 'Tahfizh Al-Qur\'an') . " {$rand}",
            'code' => ($overrides['subject_code'] ?? 'TFZ') . "-{$rand}",
            'education_unit_id' => $unit->id,
            'kurikulum_id' => $kurikulum->id,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'nama_kelas' => ($overrides['class_name'] ?? '1-A Usman') . " {$rand}",
            'kode_kelas' => ($overrides['class_code'] ?? '1A') . "-{$rand}",
            'jenjang' => $overrides['unit_level'] ?? 'SD',
            'tingkat' => 1,
            'status' => 'Aktif',
        ]);

        $user = User::factory()->create([
            'name' => $overrides['teacher_name'] ?? 'Ustadz Pengajar',
            'email' => $overrides['teacher_email'] ?? ('guru.' . Str::random(8) . '@school.test'),
        ]);
        $user->assignRole('Guru');

        $employee = Employee::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'nama_lengkap' => $user->name,
            'niy' => 'NIY-' . substr((string) Str::uuid(), 0, 8),
            'status_pegawai' => 'Tetap',
            'status' => 'Aktif',
            'status_aktivitas' => 'Aktif',
        ]);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => 'EMP-' . Str::upper(Str::random(12)),
            'full_name' => $user->name,
            'education_unit_id' => $unit->id,
        ]);

        $schedule = null;
        if (! ($overrides['skip_schedule'] ?? false)) {
            $schedule = ClassSchedule::create([
                'kelas_id' => $kelas->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id,
                'employee_id' => $employee->id,
                'teacher_id' => $teacher->id,
                'day_of_week' => 1,
                'time_start' => '07:30',
                'time_end' => '09:00',
            ]);
        }

        return compact('unit', 'academicYear', 'semester', 'kurikulum', 'subject', 'kelas', 'user', 'employee', 'teacher', 'schedule');
    }

    public function test_authenticated_user_can_access_teacher_dashboard(): void
    {
        $ctx = $this->createTeachingContext();

        $response = $this->actingAs($ctx['user'], 'sanctum')
            ->getJson('/api/teacher/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'teacher',
                    'academic_context',
                    'kpi',
                    'schedules_today',
                    'announcements',
                ],
            ]);
    }

    public function test_authenticated_user_can_access_teacher_schedules(): void
    {
        $ctx = $this->createTeachingContext();

        $response = $this->actingAs($ctx['user'], 'sanctum')
            ->getJson('/api/teacher/schedules');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_authenticated_user_can_access_teacher_classes(): void
    {
        $ctx = $this->createTeachingContext();

        $response = $this->actingAs($ctx['user'], 'sanctum')
            ->getJson('/api/teacher/classes');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_authorized_teacher_can_create_material_and_assignment(): void
    {
        $ctx = $this->createTeachingContext();

        // 1. Create Material
        $matResp = $this->actingAs($ctx['user'], 'sanctum')
            ->postJson('/api/teacher/materials', [
                'judul' => 'Materi Uji Coba',
                'subject_id' => $ctx['subject']->id,
                'class_id' => $ctx['kelas']->id,
                'ringkasan' => 'Ringkasan uji coba',
                'isi' => 'Isi materi uji coba',
                'status' => 'published',
            ]);

        $matResp->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Materi berhasil disimpan.');

        $this->assertDatabaseHas('lms_materi', [
            'judul' => 'Materi Uji Coba',
            'mata_pelajaran_id' => $ctx['subject']->id,
        ]);

        // 2. Create Assignment
        $assignResp = $this->actingAs($ctx['user'], 'sanctum')
            ->postJson('/api/teacher/assignments', [
                'judul' => 'Tugas Uji Coba',
                'subject_id' => $ctx['subject']->id,
                'class_id' => $ctx['kelas']->id,
                'instruksi' => 'Kerjakan sesuai petunjuk.',
                'deadline' => '2026-08-10',
                'bobot' => 100,
            ]);

        $assignResp->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Penugasan berhasil dibuat.');

        $this->assertDatabaseHas('lms_penugasan', [
            'judul_tugas' => 'Tugas Uji Coba',
            'kelas_id' => $ctx['kelas']->id,
            'mata_pelajaran_id' => $ctx['subject']->id,
        ]);
    }

    public function test_authorized_teacher_can_update_and_delete_own_material(): void
    {
        $ctx = $this->createTeachingContext();

        $created = $this->actingAs($ctx['user'], 'sanctum')->postJson('/api/teacher/materials', [
            'judul' => 'Materi Awal',
            'subject_id' => $ctx['subject']->id,
            'class_id' => $ctx['kelas']->id,
            'ringkasan' => 'Ringkasan awal',
            'isi' => 'Isi awal',
            'status' => 'draft',
        ])->assertOk();

        $materialId = $created->json('data.id');

        $this->actingAs($ctx['user'], 'sanctum')
            ->getJson('/api/teacher/materials')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $materialId);

        $this->actingAs($ctx['user'], 'sanctum')->putJson("/api/teacher/materials/{$materialId}", [
            'judul' => 'Materi Diperbarui',
            'ringkasan' => 'Ringkasan baru',
            'isi' => 'Isi baru',
            'status' => 'published',
        ])->assertOk()->assertJsonPath('data.judul', 'Materi Diperbarui');

        $this->actingAs($ctx['user'], 'sanctum')
            ->deleteJson("/api/teacher/materials/{$materialId}")
            ->assertOk();

        $this->assertSoftDeleted('lms_materi', ['id' => $materialId]);
    }

    public function test_authorized_teacher_can_create_and_publish_assignment(): void
    {
        $ctx = $this->createTeachingContext();

        // Create assignment as draft
        $resp = $this->actingAs($ctx['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Hafalan Surah',
            'subject_id' => $ctx['subject']->id,
            'class_id' => $ctx['kelas']->id,
            'instruksi' => 'Setor hafalan juz 30.',
            'deadline' => '2026-09-01',
            'bobot' => 100,
            'status' => 'draft',
        ])->assertOk();

        $assignmentId = $resp->json('data.id');
        $this->assertDatabaseHas('lms_penugasan', [
            'id' => $assignmentId,
            'is_published' => false,
        ]);

        // Publish assignment via updateAssignment
        $updateResp = $this->actingAs($ctx['user'], 'sanctum')->postJson("/api/teacher/assignments/{$assignmentId}", [
            'judul' => 'Tugas Hafalan Surah (Diterbitkan)',
            'instruksi' => 'Setor hafalan juz 30 segera.',
            'deadline' => '2026-09-01',
            'status' => 'published',
        ])->assertOk();

        $this->assertDatabaseHas('lms_penugasan', [
            'id' => $assignmentId,
            'judul_tugas' => 'Tugas Hafalan Surah (Diterbitkan)',
            'is_published' => true,
        ]);
    }

    /**
     * Bug A Verification: Request dari guru yang tidak memiliki kewenangan harus ditolak (HTTP 403),
     * dan server TIDAK membuat ClassSchedule baru secara otomatis.
     */
    public function test_unauthorized_teacher_cannot_create_assignment_for_unassigned_class_or_subject(): void
    {
        $ctxTeacher1 = $this->createTeachingContext([
            'class_name' => '1-A Usman',
            'class_code' => '1A',
        ]);

        $ctxTeacher2 = $this->createTeachingContext([
            'class_name' => '2-B Ali',
            'class_code' => '2B',
            'teacher_name' => 'Guru Kedua',
            'teacher_email' => 'guru.kedua.' . Str::random(6) . '@school.test',
        ]);

        $initialScheduleCount = ClassSchedule::query()->count();

        // Teacher 2 attempts to create assignment for Teacher 1's class & subject
        $response = $this->actingAs($ctxTeacher2['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Percobaan Tugas Ilegal',
            'subject_id' => $ctxTeacher1['subject']->id,
            'class_id' => $ctxTeacher1['kelas']->id,
            'instruksi' => 'Instruksi ilegal',
            'deadline' => '2026-08-20',
            'bobot' => 100,
        ]);

        // Must be rejected with HTTP 403
        $response->assertStatus(403);
        $this->assertStringContainsString('tidak memiliki wewenang atau jadwal mengajar', (string) $response->json('message'));

        // Verify NO ClassSchedule was automatically created
        $this->assertEquals($initialScheduleCount, ClassSchedule::query()->count());
        $this->assertDatabaseMissing('lms_penugasan', [
            'judul_tugas' => 'Percobaan Tugas Ilegal',
        ]);
    }

    public function test_unauthorized_teacher_cannot_update_or_delete_another_teachers_assignment(): void
    {
        $ctxTeacher1 = $this->createTeachingContext();
        $ctxTeacher2 = $this->createTeachingContext([
            'teacher_name' => 'Guru Lain',
            'teacher_email' => 'guru.lain.' . Str::random(6) . '@school.test',
        ]);

        // Teacher 1 creates assignment
        $assignResp = $this->actingAs($ctxTeacher1['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Guru 1',
            'subject_id' => $ctxTeacher1['subject']->id,
            'class_id' => $ctxTeacher1['kelas']->id,
            'instruksi' => 'Petunjuk tugas',
            'deadline' => '2026-08-30',
        ])->assertOk();

        $assignmentId = $assignResp->json('data.id');

        // Teacher 2 attempts to update Teacher 1's assignment
        $updateAttempt = $this->actingAs($ctxTeacher2['user'], 'sanctum')->postJson("/api/teacher/assignments/{$assignmentId}", [
            'judul' => 'Tugas Dibajak',
            'instruksi' => 'Instruksi bajakan',
            'deadline' => '2026-08-30',
        ]);
        $updateAttempt->assertStatus(403);

        // Teacher 2 attempts to delete Teacher 1's assignment
        $deleteAttempt = $this->actingAs($ctxTeacher2['user'], 'sanctum')->deleteJson("/api/teacher/assignments/{$assignmentId}");
        $deleteAttempt->assertStatus(403);

        // Verify assignment remains untouched in DB
        $this->assertDatabaseHas('lms_penugasan', [
            'id' => $assignmentId,
            'judul_tugas' => 'Tugas Guru 1',
        ]);
    }

    public function test_unauthorized_teacher_cannot_grade_another_teachers_submission(): void
    {
        $ctxTeacher1 = $this->createTeachingContext();
        $ctxTeacher2 = $this->createTeachingContext([
            'teacher_name' => 'Guru Penilai Liar',
            'teacher_email' => 'guru.liar.' . Str::random(6) . '@school.test',
        ]);

        // Teacher 1 creates assignment
        $assignResp = $this->actingAs($ctxTeacher1['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Setoran',
            'subject_id' => $ctxTeacher1['subject']->id,
            'class_id' => $ctxTeacher1['kelas']->id,
            'instruksi' => 'Setoran hafalan',
            'deadline' => '2026-08-30',
        ])->assertOk();

        $assignmentId = $assignResp->json('data.id');

        // Student submits
        $studentUser = User::factory()->create(['name' => 'Santri Test']);
        $studentUser->assignRole('Siswa');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'education_unit_id' => $ctxTeacher1['unit']->id,
            'kelas_id' => $ctxTeacher1['kelas']->id,
            'full_name' => 'Santri Test',
            'gender' => 'L',
            'nis' => '12345',
            'is_active' => true,
        ]);

        $submission = LmsPengumpulanTugas::create([
            'penugasan_id' => $assignmentId,
            'siswa_id' => $student->id,
            'jawaban_teks' => 'Teks hafalan surat An-Naba',
            'status' => 'dikumpulkan',
            'waktu_pengumpulan' => now(),
        ]);

        // Teacher 2 attempts to grade submission
        $gradeAttempt = $this->actingAs($ctxTeacher2['user'], 'sanctum')->postJson("/api/teacher/submissions/{$submission->id}/grade", [
            'nilai' => 100,
            'catatan_guru' => 'Nilai palsu dari guru tidak berwenang',
        ]);
        $gradeAttempt->assertStatus(403);

        // Verify submission is still pending grading
        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'id' => $submission->id,
            'status' => 'dikumpulkan',
            'nilai_guru' => null,
        ]);
    }

    /**
     * Bug B Verification: Pembuatan tugas guru Ponpes berhasil ketika konfigurasi kurikulum yang sah tersedia
     * (tidak melempar HTTP 404 dari pencarian kaku MasterKurikulum).
     */
    public function test_ponpes_teacher_can_create_assignment_with_valid_kurikulum_resolution(): void
    {
        $rand = Str::lower(Str::random(6));

        // Ponpes PA Unit
        $ponpesUnit = EducationUnit::create([
            'name' => "Pondok Pesantren Putra {$rand}",
            'code' => "PONPES-PA-{$rand}",
            'level' => 'PONPES',
            'is_active' => true,
        ]);

        // SMP Unit that owns Kurikulum Merdeka SMP SIT
        $smpUnit = EducationUnit::create([
            'name' => "SMP IT Putra {$rand}",
            'code' => "SMPIT-01-{$rand}",
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::create([
            'name' => "2026/2027 {$rand}",
            'code' => "2026-2027-PONPES-{$rand}",
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'tahun' => '2026/2027',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        // Kurikulum Merdeka SMP SIT owned by SMP unit
        $kurikulumSMP = MasterKurikulum::create([
            'kode_kurikulum' => "KUR-SMP-MERDEKA-SIT-{$rand}",
            'nama_kurikulum' => "Kurikulum Merdeka SMP Islam Terpadu {$rand}",
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $smpUnit->id, // belongs to SMP unit!
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $academicYear->id,
            'tanggal_mulai' => '2026-07-01',
            'status' => true,
        ]);

        // Ponpes subject pointing to this kurikulum
        $ponpesSubject = Subject::create([
            'name' => "Tahfizh & Tahsin Al-Qur'an {$rand}",
            'code' => "TFZ-PONPES-{$rand}",
            'education_unit_id' => $ponpesUnit->id,
            'kurikulum_id' => $kurikulumSMP->id,
            'is_active' => true,
        ]);

        $ponpesClass = Kelas::create([
            'unit_pendidikan_id' => $ponpesUnit->id,
            'tahun_ajaran_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'nama_kelas' => "VII-A (Asrama Al-Farabi) {$rand}",
            'kode_kelas' => "VII-A-{$rand}",
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => 'Aktif',
        ]);

        $guruUser = User::factory()->create([
            'name' => 'Ustadz Tahfizh Ponpes',
            'email' => "ustadz.ponpes.{$rand}@school.test",
        ]);
        $guruUser->assignRole('Guru');

        $employee = Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $ponpesUnit->id,
            'nama_lengkap' => $guruUser->name,
            'niy' => "NIY-PONPES-{$rand}",
            'status_pegawai' => 'Tetap',
            'status' => 'Aktif',
            'status_aktivitas' => 'Aktif',
        ]);

        $teacher = Teacher::create([
            'user_id' => $guruUser->id,
            'employee_id' => $employee->id,
            'employee_number' => "EMP-PONPES-{$rand}",
            'full_name' => $guruUser->name,
            'education_unit_id' => $ponpesUnit->id,
        ]);

        ClassSchedule::create([
            'kelas_id' => $ponpesClass->id,
            'subject_id' => $ponpesSubject->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'employee_id' => $employee->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 2,
            'time_start' => '08:00',
            'time_end' => '09:30',
        ]);

        // Ponpes teacher creates assignment
        $response = $this->actingAs($guruUser, 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Setoran Hafalan Juz 29 Surat Al-Mulk',
            'subject_id' => $ponpesSubject->id,
            'class_id' => $ponpesClass->id,
            'instruksi' => 'Rekam audio tilawah surat Al-Mulk ayat 1-15.',
            'deadline' => '2026-08-25',
            'bobot' => 100,
            'status' => 'published',
        ]);

        // MUST succeed with HTTP 200 (NOT 404!)
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('message', 'Penugasan berhasil dibuat.');

        $this->assertDatabaseHas('lms_penugasan', [
            'judul_tugas' => 'Setoran Hafalan Juz 29 Surat Al-Mulk',
            'kelas_id' => $ponpesClass->id,
            'mata_pelajaran_id' => $ponpesSubject->id,
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('lms_modul_ajar', [
            'kurikulum_id' => $kurikulumSMP->id,
            'unit_pendidikan_id' => $ponpesUnit->id,
            'mata_pelajaran_id' => $ponpesSubject->id,
        ]);
    }

    /**
     * End-to-end regression: Guru creates & grades -> Siswa submits -> Orang tua views.
     */
    public function test_end_to_end_assignment_student_submit_teacher_grade_and_parent_view_flow(): void
    {
        $ctx = $this->createTeachingContext();

        // 1. Guru creates and publishes assignment
        $assignResp = $this->actingAs($ctx['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Matematika Bab 1',
            'subject_id' => $ctx['subject']->id,
            'class_id' => $ctx['kelas']->id,
            'instruksi' => 'Kerjakan soal no 1-10',
            'deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'bobot' => 100,
            'status' => 'published',
        ])->assertOk();

        $assignmentId = $assignResp->json('data.id');

        // Setup Siswa & Orang Tua
        $parentUser = User::factory()->create(['name' => 'Bapak Siswa']);
        $parentUser->assignRole('Orang Tua');
        $parent = ParentModel::create([
            'user_id' => $parentUser->id,
            'full_name' => 'Bapak Siswa',
            'father_name' => 'Bapak Siswa',
            'is_active' => true,
        ]);

        $studentUser = User::factory()->create(['name' => 'Ahmad Santri']);
        $studentUser->assignRole('Siswa');
        $student = Student::create([
            'user_id' => $studentUser->id,
            'parent_id' => $parent->id,
            'education_unit_id' => $ctx['unit']->id,
            'kelas_id' => $ctx['kelas']->id,
            'full_name' => 'Ahmad Santri',
            'gender' => 'L',
            'nis' => '1001',
            'is_active' => true,
        ]);

        // 2. Siswa submits assignment
        $submitResp = $this->actingAs($studentUser, 'sanctum')->postJson("/api/portal/assignments/{$assignmentId}/submit", [
            'jawaban_teks' => 'Jawaban lengkap soal 1-10.',
        ])->assertOk();

        $submissionId = $submitResp->json('data.id');
        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'id' => $submissionId,
            'penugasan_id' => $assignmentId,
            'status' => 'dikumpulkan',
        ]);

        // 3. Guru grades submission
        $gradeResp = $this->actingAs($ctx['user'], 'sanctum')->postJson("/api/teacher/submissions/{$submissionId}/grade", [
            'nilai' => 95,
            'catatan_guru' => 'Mumtaz, penjelasan langkah sangat rapi.',
        ])->assertOk();

        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'id' => $submissionId,
            'status' => 'dinilai',
            'nilai_guru' => 95,
            'catatan_guru' => 'Mumtaz, penjelasan langkah sangat rapi.',
        ]);

        // 4. Orang Tua views children's assignments
        $parentResp = $this->actingAs($parentUser, 'sanctum')->getJson("/api/portal/assignments?child_id={$student->id}");
        $parentResp->assertOk()
            ->assertJson(['success' => true]);

        // Ensure graded submission is visible to parent with score and note
        $parentAssignments = $parentResp->json('data.data');
        $this->assertNotEmpty($parentAssignments);
        $found = collect($parentAssignments)->firstWhere('id', $assignmentId);
        $this->assertNotNull($found);
        $this->assertEquals(95, $found['pengumpulan_tugas'][0]['nilai_guru']);
        $this->assertEquals('Mumtaz, penjelasan langkah sangat rapi.', $found['pengumpulan_tugas'][0]['catatan_guru']);
    }

    public function test_teacher_cannot_create_assignment_with_mismatched_subject_not_taught_in_class(): void
    {
        $ctx = $this->createTeachingContext();

        // Foreign subject that teacher does not teach in this class
        $otherSubject = Subject::create([
            'name' => 'Bahasa Arab',
            'code' => 'ARB-' . Str::random(4),
            'education_unit_id' => $ctx['unit']->id,
            'kurikulum_id' => $ctx['kurikulum']->id,
            'is_active' => true,
        ]);

        $initialScheduleCount = ClassSchedule::query()->count();

        $response = $this->actingAs($ctx['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Mapel Mismatched',
            'subject_id' => $otherSubject->id,
            'class_id' => $ctx['kelas']->id,
            'instruksi' => 'Instruksi',
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(403);
        $this->assertEquals($initialScheduleCount, ClassSchedule::query()->count());
        $this->assertDatabaseMissing('lms_penugasan', [
            'judul_tugas' => 'Tugas Mapel Mismatched',
        ]);
    }

    public function test_assignment_creation_fails_with_422_when_curriculum_unconfigured_leaving_no_orphans(): void
    {
        $unit = EducationUnit::create([
            'name' => 'Unit Tanpa Kurikulum ' . Str::random(4),
            'code' => 'UTK-' . Str::random(4),
            'level' => 'CUSTOM_LEVEL',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2026/2027 ' . Str::random(4),
            'code' => '2026-' . Str::random(4),
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'tahun' => '2026/2027',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'name' => 'Mapel Tanpa Kurikulum',
            'code' => 'MTK-' . Str::random(4),
            'education_unit_id' => $unit->id,
            'kurikulum_id' => null,
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'nama_kelas' => 'Kelas Khusus',
            'kode_kelas' => 'KK-' . Str::random(4),
            'jenjang' => 'CUSTOM_LEVEL',
            'tingkat' => 1,
            'status' => 'Aktif',
            'kurikulum_id' => null,
        ]);

        $user = User::factory()->create(['name' => 'Guru Khusus']);
        $user->assignRole('Guru');

        $employee = Employee::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'nama_lengkap' => $user->name,
            'niy' => 'NIY-' . Str::random(8),
            'status_pegawai' => 'Tetap',
            'status' => 'Aktif',
            'status_aktivitas' => 'Aktif',
        ]);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => 'EMP-' . Str::random(8),
            'full_name' => $user->name,
            'education_unit_id' => $unit->id,
        ]);

        ClassSchedule::create([
            'kelas_id' => $kelas->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'semester_id' => $semester->id,
            'employee_id' => $employee->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 1,
            'time_start' => '07:30',
            'time_end' => '09:00',
        ]);

        $initialModulCount = \App\Models\LmsModulAjar::query()->count();
        $initialPenugasanCount = LmsPenugasan::query()->count();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Gagal Kurikulum',
            'subject_id' => $subject->id,
            'class_id' => $kelas->id,
            'instruksi' => 'Instruksi',
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Master kurikulum aktif belum dikonfigurasi', (string) $response->json('message'));

        // Zero partial or orphan records created
        $this->assertEquals($initialModulCount, \App\Models\LmsModulAjar::query()->count());
        $this->assertEquals($initialPenugasanCount, LmsPenugasan::query()->count());
    }

    public function test_unauthorized_teacher_cannot_cross_manage_assignments_via_lms_routes(): void
    {
        $ctxTeacher1 = $this->createTeachingContext();
        $ctxTeacher2 = $this->createTeachingContext([
            'teacher_name' => 'Guru Lain LMS',
            'teacher_email' => 'guru.lms.' . Str::random(6) . '@school.test',
        ]);

        // Teacher 1 creates assignment
        $assignResp = $this->actingAs($ctxTeacher1['user'], 'sanctum')->postJson('/api/teacher/assignments', [
            'judul' => 'Tugas Guru 1 LMS',
            'subject_id' => $ctxTeacher1['subject']->id,
            'class_id' => $ctxTeacher1['kelas']->id,
            'instruksi' => 'Petunjuk tugas',
            'deadline' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'status' => 'draft',
        ])->assertOk();

        $assignmentId = $assignResp->json('data.id');

        // Teacher 2 attempts to toggle-publish via /api/lms/penugasan/{id}/toggle-publish
        $toggleAttempt = $this->actingAs($ctxTeacher2['user'], 'sanctum')->postJson("/api/lms/penugasan/{$assignmentId}/toggle-publish");
        $toggleAttempt->assertStatus(403);

        // Teacher 2 attempts to delete via /api/lms/penugasan/{id}
        $deleteAttempt = $this->actingAs($ctxTeacher2['user'], 'sanctum')->deleteJson("/api/lms/penugasan/{$assignmentId}");
        $deleteAttempt->assertStatus(403);
    }
}

