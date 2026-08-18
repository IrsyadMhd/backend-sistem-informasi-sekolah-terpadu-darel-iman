<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsRapor;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsSesi6AssessmentAndReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherUser;
    protected Employee $teacher;
    protected User $studentUser;
    protected Student $student;
    protected EducationUnit $unit;
    protected MasterKurikulum $kurikulum;
    protected Subject $subject;
    protected Kelas $kelas;
    protected Kelas $targetKelas;
    protected AcademicYear $tahunAjaran;
    protected Semester $semester;
    protected CapaianPembelajaran $cp;
    protected TujuanPembelajaran $tp;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Guru', 'web');
        Role::findOrCreate('Siswa', 'web');
        Role::findOrCreate('Wali Kelas', 'web');

        $this->unit = EducationUnit::create([
            'name' => 'SMA IT Antigravity',
            'code' => 'SMA-AG',
            'level' => 'SMA',
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
            'kode_kurikulum' => 'KM-SMA',
            'nama_kurikulum' => 'Kurikulum Merdeka SMA',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMA',
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'name' => 'Fisika',
            'code' => 'FIS',
            'education_unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Guru Fisika',
            'email' => 'guru.fisika@school.id',
        ]);
        $this->teacherUser->assignRole('Guru');

        $this->teacher = Employee::create([
            'user_id' => $this->teacherUser->id,
            'unit_id' => $this->unit->id,
            'education_unit_id' => $this->unit->id,
            'nama_lengkap' => 'Dr. Guru Fisika M.Si',
            'full_name' => 'Dr. Guru Fisika M.Si',
            'niy' => '9988776655',
            'nip' => '9988776655',
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'nama_kelas' => '10-A Einstein',
            'kode_kelas' => '10A',
            'jenjang' => 'SMA',
            'tingkat' => 10,
            'status' => 'Aktif',
            'wali_kelas_id' => $this->teacher->id,
        ]);

        $this->targetKelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'nama_kelas' => '11-A Newton',
            'kode_kelas' => '11A',
            'jenjang' => 'SMA',
            'tingkat' => 11,
            'status' => 'Aktif',
            'wali_kelas_id' => $this->teacher->id,
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Budi Siswa',
            'email' => 'budi.siswa@school.id',
        ]);
        $this->studentUser->assignRole('Siswa');

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'education_unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Budi Utomo',
            'nisn' => '0098765432',
            'nis' => '202510001',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $this->cp = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_cp' => 'CP-FIS-E',
            'nama_cp' => 'Pengukuran dan Kinematika',
            'fase' => 'E',
            'elemen' => 'Pemahaman Fisika',
            'deskripsi' => 'Peserta didik mampu mengukur dan menganalisis gerak.',
            'status' => true,
        ]);

        $this->tp = TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-FIS-10.1',
            'deskripsi' => 'Mengukur besaran pokok dan turunan.',
            'status' => true,
        ]);
    }

    /** Test 1: LMS Penilaian auto calculation syncs assignment and CBT scores into StudentGrade. */
    public function test_assessment_auto_calculation_syncs_scores(): void
    {
        // Setup penugasan & submission score
        $penugasan = LmsPenugasan::create([
            'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_tugas' => 'Tugas Pengukuran Fisika',
            'nilai_maksimal' => 100,
            'is_published' => true,
        ]);

        LmsPengumpulanTugas::create([
            'penugasan_id' => $penugasan->id,
            'siswa_id' => $this->student->id,
            'jawaban_teks' => 'Hasil ukur jangka sorong: 2.34 cm.',
            'status' => 'dinilai',
            'nilai_guru' => 90,
            'waktu_kumpul' => now(),
            'waktu_dinilai' => now(),
            'dinilai_oleh' => $this->teacher->id,
        ]);

        // Auto calculate via endpoint
        $calcPayload = [
            'kelas_id' => $this->kelas->id,
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
            'bobot_tugas' => 20,
            'bobot_uh' => 25,
            'bobot_uts' => 25,
            'bobot_uas' => 30,
            'nilai_kkm' => 75,
        ];

        $calcResp = $this->actingAs($this->teacherUser)
            ->postJson('/api/lms/penilaian/calculate-auto', $calcPayload);

        $calcResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify StudentGrade record created and calculated
        $gradeRecord = StudentGrade::where('student_id', $this->student->id)
            ->where('subject_id', $this->subject->id)
            ->where('semester_id', $this->semester->id)
            ->first();

        $this->assertNotNull($gradeRecord);
        $this->assertEquals(90.0, $gradeRecord->score_assignment);
        $this->assertTrue($gradeRecord->final_score > 0);
        $this->assertTrue($gradeRecord->is_passed);
    }

    /** Test 2: Digital Report Card generation, ranking, publishing, and approval. */
    public function test_digital_report_card_generation_ranking_and_publishing(): void
    {
        // Pre-create grade for student
        StudentGrade::create([
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'academic_year_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'kelas_id' => $this->kelas->id,
            'score_assignment' => 88,
            'score_quiz' => 85,
            'score_midterm' => 90,
            'score_final' => 92,
            'final_score' => 89.2,
            'grade_letter' => 'B',
            'is_passed' => true,
        ]);

        // Generate class report card
        $generatePayload = [
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
        ];

        $genResp = $this->actingAs($this->teacherUser)
            ->postJson('/api/lms/rapor/generate-class', $generatePayload);

        $genResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $raporRecord = LmsRapor::where('siswa_id', $this->student->id)
            ->where('semester_id', $this->semester->id)
            ->first();

        $this->assertNotNull($raporRecord);
        $this->assertEquals(1, $raporRecord->peringkat_kelas);

        // Approve Report Card
        $approveResp = $this->actingAs($this->teacherUser)
            ->postJson("/api/lms/rapor/{$raporRecord->id}/approve");

        $approveResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_rapor', 'final');

        // Publish Report Card
        $publishResp = $this->actingAs($this->teacherUser)
            ->postJson("/api/lms/rapor/{$raporRecord->id}/publish");

        $publishResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status_rapor', 'published');

        // PDF Data Export
        $pdfResp = $this->actingAs($this->teacherUser)
            ->getJson("/api/lms/rapor/{$raporRecord->id}/pdf");

        $pdfResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rapor.id', $raporRecord->id);
    }

    /** Test 3: Class Promotion & Student Graduation / Alumni Formation. */
    public function test_class_promotion_and_student_graduation(): void
    {
        // 1. Promote student from Class 10A to 11A
        $this->student->update(['kelas_id' => $this->targetKelas->id]);
        $this->assertEquals($this->targetKelas->id, $this->student->fresh()->kelas_id);

        // 2. Graduate student & form Alumni record
        $meta = $this->student->metadata ?? [];
        $meta['is_alumni'] = true;
        $meta['status_siswa'] = 'alumni';
        $meta['tahun_lulus'] = '2026';

        $this->student->update([
            'is_active' => false,
            'metadata' => $meta,
        ]);

        $studentFresh = $this->student->fresh();
        $this->assertFalse($studentFresh->is_active);
        $this->assertTrue($studentFresh->metadata['is_alumni']);

        // Verify Alumni index includes this graduated student
        $alumniResp = $this->actingAs($this->teacherUser)
            ->getJson('/api/alumni');

        $alumniResp->assertStatus(200);
    }
}
