<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsKisiKisi;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenilaian;
use App\Models\LmsPenugasan;
use App\Models\LmsUjian;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');
    }

    public function test_penugasan_dan_pengumpulan_tugas_crud_dan_penilaian(): void
    {
        $unit = EducationUnit::create(['code' => 'SD-S06', 'name' => 'SD IT S06', 'level' => 'SD']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subj = Subject::create(['code' => 'MAT-S06', 'name' => 'Matematika', 'education_unit_id' => $unit->id]);
        $emp = Employee::create(['nama_lengkap' => 'Guru Mat', 'jenis_kelamin' => 'L', 'unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'V B', 'level' => 5, 'education_unit_id' => $unit->id]);
        $student = Student::create(['full_name' => 'Siswa S06', 'nis' => '1006', 'gender' => 'L', 'status' => 'aktif', 'education_unit_id' => $unit->id]);

        // 1. Create Penugasan
        $tugRes = $this->actingAs($this->user)->postJson('/api/penugasan', [
            'judul_tugas' => '  Tugas   Pecahan   Senilai  ',
            'instruksi' => "Kerjakan soal 1-5.\nTunjukkan jalurnya.",
            'mata_pelajaran_id' => $subj->id,
            'kelas_id' => $kelas->id,
            'guru_id' => $emp->id,
            'semester_id' => $sem->id,
            'tahun_ajaran_id' => $ay->id,
            'nilai_maksimal' => 100,
        ]);

        $tugRes->assertCreated()
            ->assertJsonPath('data.judul_tugas', 'Tugas Pecahan Senilai');

        $tugasId = $tugRes->json('data.id');

        // 2. Student Submission
        $sub = LmsPengumpulanTugas::create([
            'penugasan_id' => $tugasId,
            'siswa_id' => $student->id,
            'jawaban_teks' => 'Hasil pengerjaan saya.',
            'status' => 'dikumpulkan',
        ]);

        // 3. Update Submission (Grade & Feedback)
        $gradeRes = $this->actingAs($this->user)->putJson("/api/pengumpulan-tugas/{$sub->id}", [
            'nilai_guru' => 95,
            'catatan_guru' => 'Sangat baik dan rapi.',
            'status' => 'dinilai',
        ]);

        $gradeRes->assertOk()
            ->assertJsonPath('data.nilai_guru', 95)
            ->assertJsonPath('data.status', 'dinilai');
    }

    public function test_ujian_cbt_dan_penilaian_rekap(): void
    {
        $unit = EducationUnit::create(['code' => 'SMP-S06', 'name' => 'SMP IT S06', 'level' => 'SMP']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subj = Subject::create(['code' => 'ENG-S06', 'name' => 'Bahasa Inggris', 'education_unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'IX A', 'level' => 9, 'education_unit_id' => $unit->id]);
        $student = Student::create(['full_name' => 'Siswa SMP 06', 'nis' => '2006', 'gender' => 'P', 'status' => 'aktif', 'education_unit_id' => $unit->id]);

        $kisi = LmsKisiKisi::create([
            'judul_kisi' => 'Kisi-Kisi English PTS',
            'mata_pelajaran_id' => $subj->id,
            'jenis_ujian' => 'PTS',
            'jumlah_soal' => 10,
            'alokasi_waktu_menit' => 60,
        ]);

        // 1. Create Ujian CBT
        $cbtRes = $this->actingAs($this->user)->postJson('/api/lms/ujian', [
            'kisi_kisi_id' => $kisi->id,
            'kelas_id' => $kelas->id,
            'semester_id' => $sem->id,
            'judul_ujian' => '  PTS   English   Semester   Ganjil  ',
            'durasi_menit' => 60,
            'status' => 'published',
        ]);

        $cbtRes->assertCreated()
            ->assertJsonPath('data.judul_ujian', 'PTS English Semester Ganjil');

        // 2. Rekap Penilaian
        $nilRes = $this->actingAs($this->user)->postJson('/api/lms/penilaian', [
            'student_id' => $student->id,
            'subject_id' => $subj->id,
            'semester_id' => $sem->id,
            'kelas_id' => $kelas->id,
            'academic_year_id' => $ay->id,
            'score_assignment' => 90,
            'score_quiz' => 85,
            'score_midterm' => 88,
            'score_final' => 92,
        ]);

        $nilRes->assertCreated()
            ->assertJsonPath('data.score_assignment', 90);

        // 3. Export Penilaian
        $expRes = $this->actingAs($this->user)->getJson('/api/lms/penilaian/export');
        $expRes->assertOk()->assertJsonPath('status', 'success');
    }
}
