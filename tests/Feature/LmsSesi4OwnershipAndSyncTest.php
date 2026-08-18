<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsAktivitasBelajar;
use App\Models\LmsDiskusi;
use App\Models\LmsMateri;
use App\Models\LmsMedia;
use App\Models\LmsModulAjar;
use App\Models\LmsReferensi;
use App\Models\MasterKurikulum;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsSesi4OwnershipAndSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher1User;
    protected User $teacher2User;
    protected Employee $employee1;
    protected Employee $employee2;
    protected User $studentUser;
    protected Student $student;
    protected EducationUnit $unit;
    protected MasterKurikulum $kurikulum;
    protected Subject $subject;
    protected Kelas $kelas;
    protected AcademicYear $tahunAjaran;
    protected Semester $semester;
    protected LmsModulAjar $modul1;
    protected LmsModulAjar $modul2;
    protected LmsMateri $materi1;
    protected LmsMateri $materi2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Guru', 'web');
        Role::findOrCreate('Siswa', 'web');
        Role::findOrCreate('Wali Kelas', 'web');

        $this->unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity',
            'code' => 'SMP-AG',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $this->tahunAjaran = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'tahun' => '2025/2026',
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahunAjaran->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);

        $this->kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-SMP',
            'nama_kurikulum' => 'Kurikulum Merdeka SMP',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Matematika SMP',
            'code' => 'MTK-SMP',
            'education_unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'nama_kelas' => '7A',
            'kode_kelas' => '7A-SMP',
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'jenjang' => 'SMP',
            'tingkat' => '7',
            'is_active' => true,
        ]);

        $this->teacher1User = User::factory()->create(['email' => 'teacher1@test.com']);
        $this->teacher1User->assignRole('Guru');
        $this->employee1 = Employee::create([
            'user_id' => $this->teacher1User->id,
            'unit_id' => $this->unit->id,
            'education_unit_id' => $this->unit->id,
            'nama_lengkap' => 'Guru Satu',
            'full_name' => 'Guru Satu',
            'niy' => '11111',
            'nip' => '11111',
            'is_active' => true,
        ]);

        $this->teacher2User = User::factory()->create(['email' => 'teacher2@test.com']);
        $this->teacher2User->assignRole('Guru');
        $this->employee2 = Employee::create([
            'user_id' => $this->teacher2User->id,
            'unit_id' => $this->unit->id,
            'education_unit_id' => $this->unit->id,
            'nama_lengkap' => 'Guru Dua',
            'full_name' => 'Guru Dua',
            'niy' => '22222',
            'nip' => '22222',
            'is_active' => true,
        ]);

        $this->studentUser = User::factory()->create(['email' => 'student@test.com']);
        $this->studentUser->assignRole('Siswa');
        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Siswa Utama',
            'gender' => 'male',
            'nis' => 'NIS-'.str()->upper(str()->random(10)),
            'nisn' => '009991',
            'is_active' => true,
        ]);

        $this->modul1 = LmsModulAjar::create([
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->employee1->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_modul' => 'Modul Aljabar Guru 1',
            'status' => 'published',
        ]);

        $this->modul2 = LmsModulAjar::create([
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->employee2->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_modul' => 'Modul Aljabar Guru 2',
            'status' => 'published',
        ]);

        $this->materi1 = LmsMateri::create([
            'modul_ajar_id' => $this->modul1->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->employee1->id,
            'judul' => 'Persamaan Linier 1',
            'konten' => 'Isi materi 1',
            'is_published' => true,
        ]);

        $this->materi2 = LmsMateri::create([
            'modul_ajar_id' => $this->modul2->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->employee2->id,
            'judul' => 'Persamaan Linier 2',
            'konten' => 'Isi materi 2',
            'is_published' => true,
        ]);
    }

    public function test_teacher_cannot_add_or_modify_media_on_another_teachers_materi()
    {
        $media2 = LmsMedia::create([
            'materi_id' => $this->materi2->id,
            'nama_file' => 'Media Guru 2',
            'tipe_file' => 'pdf',
        ]);

        // Teacher 1 tries to create media on Teacher 2's materi -> 403
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson('/api/lms/media', [
                'materi_id' => $this->materi2->id,
                'nama_file' => 'Illegal Media',
                'tipe_file' => 'pdf',
            ]);
        $response->assertStatus(403);

        // Teacher 1 tries to update Teacher 2's media -> 403
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->putJson("/api/lms/media/{$media2->id}", [
                'nama_file' => 'Hacked Name',
            ]);
        $response->assertStatus(403);

        // Teacher 1 tries to delete Teacher 2's media -> 403
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->deleteJson("/api/lms/media/{$media2->id}");
        $response->assertStatus(403);
    }

    public function test_reference_rejects_unsafe_javascript_url()
    {
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson('/api/lms/referensi', [
                'modul_ajar_id' => $this->modul1->id,
                'judul' => 'Bad Reference',
                'url' => 'javascript:alert(1)',
            ]);

        $response->assertStatus(422);
    }

    public function test_teacher_cannot_manage_referensi_of_another_teachers_modul()
    {
        $ref2 = LmsReferensi::create([
            'modul_ajar_id' => $this->modul2->id,
            'judul' => 'Referensi Guru 2',
            'status' => 'aktif',
        ]);

        // Teacher 1 tries to create referensi for Teacher 2's modul -> 403
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson('/api/lms/referensi', [
                'modul_ajar_id' => $this->modul2->id,
                'judul' => 'Illegal Reference',
            ]);
        $response->assertStatus(403);

        // Teacher 1 tries to update -> 403
        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->putJson("/api/lms/referensi/{$ref2->id}", [
                'judul' => 'Modified Title',
            ]);
        $response->assertStatus(403);
    }

    public function test_teacher_cannot_manage_aktivitas_of_another_teachers_modul()
    {
        $act2 = LmsAktivitasBelajar::create([
            'modul_ajar_id' => $this->modul2->id,
            'nama_aktivitas' => 'Aktivitas Guru 2',
            'jenis_aktivitas' => 'Inti',
            'waktu' => 15,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson('/api/lms/aktivitas', [
                'modul_ajar_id' => $this->modul2->id,
                'nama_aktivitas' => 'Illegal Activity',
                'jenis_aktivitas' => 'Inti',
                'waktu' => 15,
                'urutan' => 1,
                'status' => 'aktif',
            ]);
        $response->assertStatus(403);

        $response = $this->actingAs($this->teacher1User, 'sanctum')
            ->deleteJson("/api/lms/aktivitas/{$act2->id}");
        $response->assertStatus(403);
    }

    public function test_discussion_comment_ownership_and_moderation()
    {
        $diskusi = LmsDiskusi::create([
            'modul_ajar_id' => $this->modul1->id,
            'judul' => 'Diskusi Matematika',
            'status' => 'aktif',
            'is_closed' => false,
        ]);

        // Student 1 adds comment
        $respStudent = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/lms/diskusi/{$diskusi->id}/komentar", [
                'konten' => 'Saya ingin bertanya tentang materi ini.',
            ]);
        $respStudent->assertStatus(201);
        $commentId = $respStudent->json('data.id');

        // Other student tries to delete student 1's comment -> 403
        $student2User = User::factory()->create(['email' => 'student2@test.com']);
        $student2User->assignRole('Siswa');
        Student::create([
            'user_id' => $student2User->id,
            'unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Siswa Kedua',
            'gender' => 'male',
            'nis' => 'NIS-'.str()->upper(str()->random(10)),
            'nisn' => '009992',
            'is_active' => true,
        ]);

        $response = $this->actingAs($student2User, 'sanctum')
            ->deleteJson("/api/lms/diskusi/{$diskusi->id}/komentar/{$commentId}");
        $response->assertStatus(403);

        // Teacher 1 (owner) can pin topic and toggle close
        $respPin = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson("/api/lms/diskusi/{$diskusi->id}/toggle-pin");
        $respPin->assertStatus(200);

        $respClose = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson("/api/lms/diskusi/{$diskusi->id}/toggle-close");
        $respClose->assertStatus(200);

        // Once closed, new comment is rejected with 422
        $respClosedComment = $this->actingAs($this->studentUser, 'sanctum')
            ->postJson("/api/lms/diskusi/{$diskusi->id}/komentar", [
                'konten' => 'Komentar pada diskusi tertutup.',
            ]);
        $respClosedComment->assertStatus(422);
    }

    public function test_presensi_options_and_stats_accessible_to_teacher_without_403()
    {
        $schedule = ClassSchedule::create([
            'employee_id' => $this->employee1->id,
            'subject_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'academic_year_id' => $this->tahunAjaran->id,
            'is_active' => true,
            'day_of_week' => 1,
            'time_start' => '08:00:00',
            'time_end' => '09:30:00',
        ]);

        // Teacher fetches options -> 200 OK
        $responseOptions = $this->actingAs($this->teacher1User, 'sanctum')
            ->getJson('/api/lms/presensi/options');
        $responseOptions->assertStatus(200);
        $responseOptions->assertJsonPath('success', true);

        // Teacher fetches stats -> 200 OK
        $responseStats = $this->actingAs($this->teacher1User, 'sanctum')
            ->getJson('/api/lms/presensi/stats');
        $responseStats->assertStatus(200);
        $responseStats->assertJsonPath('success', true);

        // Store presensi
        $respStore = $this->actingAs($this->teacher1User, 'sanctum')
            ->postJson('/api/lms/presensi/bulk', [
                'jadwal_pelajaran_id' => $schedule->id,
                'tanggal' => now()->toDateString(),
                'pertemuan_ke' => 1,
                'items' => [
                    [
                        'siswa_id' => $this->student->id,
                        'status_hadir' => 'hadir',
                        'keterangan' => 'Hadir tepat waktu',
                    ],
                ],
            ]);
        $respStore->assertStatus(200);

        $this->assertDatabaseHas('lms_presensi', [
            'jadwal_pelajaran_id' => $schedule->id,
            'siswa_id' => $this->student->id,
            'status_hadir' => 'hadir',
        ]);
    }
}
