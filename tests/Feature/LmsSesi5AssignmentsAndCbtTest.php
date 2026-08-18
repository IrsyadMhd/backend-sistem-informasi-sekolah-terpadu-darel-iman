<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsModulAjar;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsSesi5AssignmentsAndCbtTest extends TestCase
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
    protected AcademicYear $tahunAjaran;
    protected Semester $semester;
    protected CapaianPembelajaran $cp;
    protected TujuanPembelajaran $tp;
    protected LmsModulAjar $modulAjar;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Guru', 'web');
        Role::findOrCreate('Siswa', 'web');

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
            'name' => 'Matematika',
            'code' => 'MTK',
            'education_unit_id' => $this->unit->id,
            'is_active' => true,
        ]);

        $this->teacherUser = User::factory()->create([
            'name' => 'Guru Matematika',
            'email' => 'guru.mtk@school.id',
        ]);
        $this->teacherUser->assignRole('Guru');

        $this->teacher = Employee::create([
            'user_id' => $this->teacherUser->id,
            'unit_id' => $this->unit->id,
            'education_unit_id' => $this->unit->id,
            'nama_lengkap' => 'Guru Matematika M.Pd',
            'full_name' => 'Guru Matematika M.Pd',
            'niy' => '1234567890',
            'nip' => '1234567890',
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id,
            'nama_kelas' => '7A Al-Khawarizmi',
            'kode_kelas' => '7A',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => 'Aktif',
            'wali_kelas_id' => $this->teacher->id,
        ]);

        $this->studentUser = User::factory()->create([
            'name' => 'Ahmad Siswa',
            'email' => 'ahmad.siswa@school.id',
        ]);
        $this->studentUser->assignRole('Siswa');

        $this->student = Student::create([
            'user_id' => $this->studentUser->id,
            'education_unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'full_name' => 'Ahmad Siswa Pratama',
            'nisn' => '0012345678',
            'nis' => '202507001',
            'gender' => 'L',
            'is_active' => true,
        ]);

        $this->cp = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_cp' => 'CP-MTK-D',
            'nama_cp' => 'CP Aljabar SMP',
            'fase' => 'D',
            'elemen' => 'Aljabar',
            'deskripsi' => 'Peserta didik dapat menyelesaikan persamaan linear satu variabel.',
            'status' => true,
        ]);

        $this->tp = TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-7.1',
            'deskripsi' => 'Memahami dan menyelesaikan persamaan linear sederhana.',
            'status' => true,
        ]);

        $this->modulAjar = LmsModulAjar::create([
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_modul' => 'Modul Aljabar Dasar',
            'is_published' => true,
        ]);
    }

    /** Test 1: Guru can create Penugasan and toggle publish status. */
    public function test_guru_can_create_and_publish_assignment(): void
    {
        $payload = [
            'modul_ajar_id' => $this->modulAjar->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_tugas' => 'Tugas Aljabar 1',
            'deskripsi' => 'Kerjakan latihan aljabar pada lembar kerja.',
            'tipe_tugas' => 'individu',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'bobot_persen' => 10,
            'deadline' => now()->addDays(5)->toDateTimeString(),
            'is_published' => false,
        ];

        $response = $this->actingAs($this->teacherUser)
            ->postJson('/api/lms/penugasan', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.judul_tugas', 'Tugas Aljabar 1');

        $assignmentId = $response->json('data.id');

        // Toggle publish
        $toggleResp = $this->actingAs($this->teacherUser)
            ->postJson("/api/lms/penugasan/{$assignmentId}/toggle-publish");

        $toggleResp->assertStatus(200)
            ->assertJsonPath('data.is_published', true);
    }

    /** Test 2: Student can submit assignment and Teacher can grade it. */
    public function test_student_assignment_submission_and_teacher_grading(): void
    {
        $penugasan = LmsPenugasan::create([
            'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => $this->teacher->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'judul_tugas' => 'Tugas Mandiri Aljabar',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(3),
            'is_published' => true,
        ]);

        // Student submits assignment
        $submitPayload = [
            'penugasan_id' => $penugasan->id,
            'siswa_id' => $this->student->id,
            'jawaban_teks' => 'Jawaban no 1: x = 5. Jawaban no 2: y = 10.',
            'url_link' => 'https://drive.google.com/file/d/sample',
        ];

        $submitResp = $this->actingAs($this->studentUser)
            ->postJson('/api/lms/pengumpulan-tugas', $submitPayload);

        $submitResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'dikumpulkan');

        $submissionId = $submitResp->json('data.id');

        // Teacher grades the submission
        $gradePayload = [
            'nilai_guru' => 95,
            'catatan_guru' => 'Sangat baik, penjelasan langkah runtut.',
        ];

        $gradeResp = $this->actingAs($this->teacherUser)
            ->putJson("/api/lms/pengumpulan-tugas/{$submissionId}", $gradePayload);

        $gradeResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nilai_guru', 95)
            ->assertJsonPath('data.status', 'dinilai');
    }

    /** Test 3: Guru can create Kisi-kisi Ujian and Bank Soal. */
    public function test_exam_blueprint_and_question_bank_crud(): void
    {
        $kisiPayload = [
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'cp_id' => $this->cp->id,
            'tp_id' => $this->tp->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'guru_id' => $this->teacher->id,
            'judul_kisi' => 'Kisi-kisi PTS Aljabar 7A',
            'jenis_ujian' => 'PTS',
            'jumlah_soal' => 5,
            'alokasi_waktu_menit' => 60,
            'status' => true,
        ];

        $kisiResp = $this->actingAs($this->teacherUser)
            ->postJson('/api/lms/kisi-kisi', $kisiPayload);

        $kisiResp->assertStatus(201)
            ->assertJsonPath('success', true);

        $kisiId = $kisiResp->json('data.id');

        // Create Bank Soal PG
        $soalPgPayload = [
            'kisi_kisi_id' => $kisiId,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_soal' => 'SOAL-01',
            'pertanyaan' => 'Berapa nilai x dari 2x + 4 = 10?',
            'tipe_soal' => 'pg',
            'opsi_a' => '1',
            'opsi_b' => '2',
            'opsi_c' => '3',
            'opsi_d' => '4',
            'opsi_e' => '5',
            'kunci_jawaban' => 'C',
            'poin' => 20,
            'status' => true,
        ];

        $soalResp = $this->actingAs($this->teacherUser)
            ->postJson('/api/lms/bank-soal', $soalPgPayload);

        $soalResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kunci_jawaban', 'C');
    }

    /** Test 4: CBT Engine Security & Session Lifecycle (No Key Leakage & Auto Scoring). */
    public function test_cbt_session_lifecycle_security_and_auto_scoring(): void
    {
        $kisi = LmsKisiKisi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'guru_id' => $this->teacher->id,
            'judul_kisi' => 'Kisi CBT Ujian Harian',
            'jenis_ujian' => 'UH',
            'jumlah_soal' => 2,
            'alokasi_waktu_menit' => 30,
            'status' => true,
        ]);

        $soal1 = LmsBankSoal::create([
            'kisi_kisi_id' => $kisi->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_soal' => 'SOAL-PG-1',
            'pertanyaan' => '2 + 3 = ?',
            'tipe_soal' => 'pg',
            'opsi_a' => '4',
            'opsi_b' => '5',
            'opsi_c' => '6',
            'kunci_jawaban' => 'B',
            'poin' => 50,
            'status' => true,
        ]);

        $soal2 = LmsBankSoal::create([
            'kisi_kisi_id' => $kisi->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_soal' => 'SOAL-ESAI-1',
            'pertanyaan' => 'Jelaskan pengertian variabel!',
            'tipe_soal' => 'esai',
            'poin' => 50,
            'status' => true,
        ]);

        $ujian = LmsUjian::create([
            'kisi_kisi_id' => $kisi->id,
            'kelas_id' => $this->kelas->id,
            'semester_id' => $this->semester->id,
            'guru_id' => $this->teacher->id,
            'judul_ujian' => 'Ujian Harian Matematika Aljabar',
            'waktu_mulai' => now()->subMinute(),
            'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 30,
            'nilai_kkm' => 70,
            'status' => 'published',
        ]);

        // Student starts exam session
        $startResp = $this->actingAs($this->studentUser)
            ->postJson("/api/lms/ujian/{$ujian->id}/start-session");

        $startResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Security check: Payload delivered to student MUST NOT contain kunci_jawaban
        $soalPayload = $startResp->json('data.soal');
        $this->assertCount(2, $soalPayload);
        $this->assertArrayNotHasKey('kunci_jawaban', $soalPayload[0]);
        $this->assertArrayNotHasKey('pembahasan', $soalPayload[0]);

        $sesiId = $startResp->json('data.sesi_id');

        // Submit transient answers
        $answers = [
            [
                'soal_id' => $soal1->id,
                'jawaban_dipilih' => 'B', // Correct answer
            ],
            [
                'soal_id' => $soal2->id,
                'jawaban_esai' => 'Variabel adalah simbol yang mewakili nilai yang belum diketahui.',
            ],
        ];

        $submitAnswersResp = $this->actingAs($this->studentUser)
            ->postJson("/api/lms/ujian/sesi/{$sesiId}/submit-answers", ['jawaban' => $answers]);

        $submitAnswersResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Finish CBT session
        $finishResp = $this->actingAs($this->studentUser)
            ->postJson("/api/lms/ujian/sesi/{$sesiId}/finish-session");

        $finishResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'selesai');

        // Check auto scoring for PG (50 points out of 100 max = raw score 50, final score 50.00)
        $sesiRecord = LmsUjianSesi::find($sesiId);
        $this->assertEquals(50.0, $sesiRecord->nilai_raw);

        // Teacher grades the essay (soal 2)
        $jawabanEsai = $sesiRecord->jawaban->firstWhere('soal_id', $soal2->id);

        $gradeEssayResp = $this->actingAs($this->teacherUser)
            ->postJson("/api/lms/ujian/jawaban/{$jawabanEsai->id}/grade-essay", [
                'poin_didapat' => 50,
                'catatan_guru' => 'Jawaban sangat tepat.',
            ]);

        $gradeEssayResp->assertStatus(200)
            ->assertJsonPath('success', true);

        // Recalculated final score should now be 100.00
        $sesiRecordFresh = $sesiRecord->fresh();
        $this->assertEquals(100.0, $sesiRecordFresh->nilai_final);
    }
}
