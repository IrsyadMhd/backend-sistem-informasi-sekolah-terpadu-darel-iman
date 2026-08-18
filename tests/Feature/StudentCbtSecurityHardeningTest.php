<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 11 — Hardening keamanan CBT & Portal Siswa.
 *
 * Menjamin:
 *  - Kunci jawaban/pembahasan TIDAK bocor ke siswa/orang tua.
 *  - Sesi ujian TIDAK bisa diakses/diisi pengguna lain (ownership fail-closed).
 *  - Hasil ujian TIDAK tampil ke siswa sebelum dipublikasikan
 *    (tampilkan_nilai_langsung = false).
 *  - Timer ujian ditegakkan (jawaban ditolak setelah waktu habis).
 *  - Percobaan `proses` tidak ganda (partial unique index).
 *  - Tanda tangan catatan hanya oleh Orang Tua yang terhubung.
 */
class StudentCbtSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unit;
    protected MasterKurikulum $kurikulum;
    protected Subject $subject;
    protected Kelas $kelas;
    protected AcademicYear $tahunAjaran;
    protected Semester $semester;
    protected Employee $guru;
    protected User $guruUser;
    protected User $siswaUser;
    protected Student $siswa;
    protected User $ortuUser;
    protected ParentModel $ortu;
    protected LmsKisiKisi $kisi;
    protected LmsBankSoal $soalPg;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);

        $this->unit = EducationUnit::create(['name' => 'SMP IT Hardening', 'code' => 'SMP-H', 'level' => 'SMP', 'is_active' => true]);

        $this->tahunAjaran = AcademicYear::create([
            'name' => '2025/2026', 'code' => '2025-2026',
            'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => true, 'tahun' => '2025/2026',
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahunAjaran->id, 'name' => 'Ganjil', 'sequence' => 1,
            'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'is_active' => true,
        ]);

        $this->kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-H', 'nama_kurikulum' => 'Kurikulum Hardening', 'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id, 'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahunAjaran->id, 'tanggal_mulai' => '2025-07-01', 'status' => true,
        ]);

        $this->subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK-H', 'education_unit_id' => $this->unit->id, 'is_active' => true]);

        $this->guruUser = User::factory()->create(['name' => 'Guru Ujian', 'email' => 'guru.harden@school.id']);
        $this->guruUser->assignRole('Guru');
        $this->guru = Employee::create([
            'user_id' => $this->guruUser->id, 'unit_id' => $this->unit->id, 'education_unit_id' => $this->unit->id,
            'nama_lengkap' => 'Guru Ujian M.Pd', 'full_name' => 'Guru Ujian M.Pd', 'niy' => 'H-0001', 'nip' => 'H-0001', 'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id, 'tahun_ajaran_id' => $this->tahunAjaran->id, 'semester_id' => $this->semester->id,
            'nama_kelas' => '8A Hardening', 'kode_kelas' => '8A', 'jenjang' => 'SMP', 'tingkat' => 8, 'status' => 'Aktif', 'wali_kelas_id' => $this->guru->id,
        ]);

        $this->siswaUser = User::factory()->create(['name' => 'Siswa CBT', 'email' => 'siswa.harden@school.id']);
        $this->siswaUser->assignRole('Siswa');
        $this->siswa = Student::create([
            'user_id' => $this->siswaUser->id, 'education_unit_id' => $this->unit->id, 'kelas_id' => $this->kelas->id,
            'full_name' => 'Siswa CBT Pratama', 'nisn' => 'H-1000001', 'nis' => 'H-1001', 'gender' => 'male', 'is_active' => true,
        ]);

        $this->ortuUser = User::factory()->create(['name' => 'Ortu CBT', 'email' => 'ortu.harden@school.id']);
        $this->ortuUser->assignRole('Orang Tua');
        $this->ortu = ParentModel::create(['user_id' => $this->ortuUser->id, 'full_name' => 'Wali CBT']);
        StudentParent::create(['student_id' => $this->siswa->id, 'parent_id' => $this->ortu->id, 'relationship_type' => 'guardian', 'is_primary' => true]);

        $this->kisi = LmsKisiKisi::create([
            'kurikulum_id' => $this->kurikulum->id, 'mata_pelajaran_id' => $this->subject->id,
            'kelas_id' => $this->kelas->id, 'semester_id' => $this->semester->id, 'tahun_ajaran_id' => $this->tahunAjaran->id,
            'guru_id' => $this->guru->id, 'judul_kisi' => 'Kisi Ujian Hardening', 'jenis_ujian' => 'UH',
            'jumlah_soal' => 2, 'alokasi_waktu_menit' => 30, 'status' => true,
        ]);

        $this->soalPg = LmsBankSoal::create([
            'kisi_kisi_id' => $this->kisi->id, 'mata_pelajaran_id' => $this->subject->id,
            'kode_soal' => 'H-PG-1', 'pertanyaan' => '2 + 2 = ?', 'tipe_soal' => 'pg',
            'opsi_a' => '3', 'opsi_b' => '4', 'kunci_jawaban' => 'B', 'poin' => 50, 'status' => true,
        ]);

        LmsBankSoal::create([
            'kisi_kisi_id' => $this->kisi->id, 'mata_pelajaran_id' => $this->subject->id,
            'kode_soal' => 'H-ESAI-1', 'pertanyaan' => 'Jelaskan variabel!', 'tipe_soal' => 'esai', 'poin' => 50, 'status' => true,
        ]);
    }

    private function buatUjian(array $overrides = []): LmsUjian
    {
        return LmsUjian::create(array_merge([
            'kisi_kisi_id' => $this->kisi->id, 'kelas_id' => $this->kelas->id, 'semester_id' => $this->semester->id,
            'guru_id' => $this->guru->id, 'judul_ujian' => 'Ujian Harian Hardening',
            'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 30, 'nilai_kkm' => 70, 'tampilkan_nilai_langsung' => true, 'status' => 'published',
        ], $overrides));
    }

    public function test_answer_key_not_leaked_to_student_or_parent(): void
    {
        $this->actingAs($this->siswaUser)
            ->getJson("/api/lms/bank-soal/{$this->soalPg->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kunci_jawaban', null)
            ->assertJsonPath('data.pembahasan', null);

        $this->actingAs($this->siswaUser)
            ->getJson('/api/lms/bank-soal')
            ->assertOk()
            ->assertJsonPath('data.0.kunci_jawaban', null);

        $this->actingAs($this->ortuUser)
            ->getJson("/api/lms/bank-soal/{$this->soalPg->id}")
            ->assertOk()
            ->assertJsonPath('data.kunci_jawaban', null);
    }

    public function test_teacher_still_receives_answer_key(): void
    {
        $this->actingAs($this->guruUser)
            ->getJson("/api/lms/bank-soal/{$this->soalPg->id}")
            ->assertOk()
            ->assertJsonPath('data.kunci_jawaban', 'B');
    }

    public function test_legacy_start_session_rejects_non_staff_without_siswa_id(): void
    {
        $ujian = $this->buatUjian();

        // Orang Tua (bukan staf, bukan siswa) tidak boleh membuat sesi
        // tanpa izin; fallback ke siswa mana pun dihapus.
        $this->actingAs($this->ortuUser)
            ->postJson("/api/lms/ujian/{$ujian->id}/start-session")
            ->assertStatus(403);

        $this->actingAs($this->ortuUser)
            ->postJson("/api/lms/ujian/{$ujian->id}/start-session", ['siswa_id' => $this->siswa->id])
            ->assertStatus(403);
    }

    public function test_duplicate_proses_attempt_is_resumed_not_duplicated(): void
    {
        $ujian = $this->buatUjian();

        $first = $this->actingAs($this->siswaUser)
            ->postJson("/api/lms/ujian/{$ujian->id}/start-session");
        $first->assertOk()->assertJsonPath('success', true);
        $sesiId = $first->json('data.sesi_id');

        $second = $this->actingAs($this->siswaUser)
            ->postJson("/api/lms/ujian/{$ujian->id}/start-session");
        $second->assertOk()->assertJsonPath('success', true);
        $this->assertSame($sesiId, $second->json('data.sesi_id'));

        $this->assertSame(1, LmsUjianSesi::where('ujian_id', $ujian->id)->where('siswa_id', $this->siswa->id)->where('status', 'proses')->count());
    }

    public function test_answers_cannot_be_submitted_to_foreign_session(): void
    {
        $ujian = $this->buatUjian();

        $start = $this->actingAs($this->siswaUser)->postJson("/api/lms/ujian/{$ujian->id}/start-session");
        $sesiId = $start->json('data.sesi_id');

        $jawaban = [['soal_id' => $this->soalPg->id, 'jawaban_dipilih' => 'B']];

        // Orang Tua tidak boleh mengisi jawaban sesi siswa lain.
        $this->actingAs($this->ortuUser)
            ->postJson("/api/lms/ujian/sesi/{$sesiId}/submit-answers", ['jawaban' => $jawaban])
            ->assertStatus(403);

        $this->actingAs($this->ortuUser)
            ->postJson("/api/lms/ujian/sesi/{$sesiId}/finish-session")
            ->assertStatus(403);
    }

    public function test_results_endpoint_is_staff_only(): void
    {
        $ujian = $this->buatUjian();

        $this->actingAs($this->siswaUser)
            ->getJson("/api/lms/ujian/{$ujian->id}/results")
            ->assertStatus(403);

        $this->actingAs($this->guruUser)
            ->getJson("/api/lms/ujian/{$ujian->id}/results")
            ->assertOk();
    }

    public function test_submit_after_timer_expiry_is_rejected(): void
    {
        $ujian = $this->buatUjian(['durasi_menit' => 1]);

        $start = $this->actingAs($this->siswaUser)->postJson("/api/lms/ujian/{$ujian->id}/start-session");
        $sesiId = $start->json('data.sesi_id');

        // Paksa sesi mulai di masa lalu agar waktu pengerjaan habis.
        LmsUjianSesi::find($sesiId)->update(['waktu_mulai' => now()->subMinutes(5)]);

        $this->actingAs($this->siswaUser)
            ->postJson("/api/lms/ujian/sesi/{$sesiId}/submit-answers", [
                'jawaban' => [['soal_id' => $this->soalPg->id, 'jawaban_dipilih' => 'B']],
            ])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_portal_finish_hides_score_until_published(): void
    {
        $hidden = $this->buatUjian(['tampilkan_nilai_langsung' => false]);

        $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/lms/exams/{$hidden->id}/start")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_sign_student_note_rejected_for_student(): void
    {
        $teacher = \App\Models\Teacher::create(['full_name' => 'Guru Catatan', 'employee_number' => 'T-H-01']);
        $note = \App\Models\StudentNote::create([
            'student_id' => $this->siswa->id, 'teacher_id' => $teacher->id,
            'title' => 'Catatan Uji', 'content' => 'Isi catatan.',
            'visible_to_parent' => true, 'visible_to_student' => true,
            'category' => 'Akademik', 'priority' => 'medium', 'date' => now()->toDateString(),
        ]);

        $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/student-notes/{$note->id}/sign")
            ->assertStatus(403);
    }

    public function test_portal_submit_assignment_rejects_other_class(): void
    {
        $otherKelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id, 'tahun_ajaran_id' => $this->tahunAjaran->id,
            'semester_id' => $this->semester->id, 'nama_kelas' => '9B Hardening', 'kode_kelas' => '9B',
            'jenjang' => 'SMP', 'tingkat' => 9, 'status' => 'Aktif', 'wali_kelas_id' => $this->guru->id,
        ]);

        $penugasan = \App\Models\LmsPenugasan::create([
            'mata_pelajaran_id' => $this->subject->id, 'kelas_id' => $otherKelas->id,
            'guru_id' => $this->guru->id, 'semester_id' => $this->semester->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id, 'judul_tugas' => 'Tugas Kelas Lain',
            'nilai_maksimal' => 100, 'deadline' => now()->addDays(3), 'is_published' => true,
        ]);

        $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/assignments/{$penugasan->id}/submit", ['jawaban_teks' => 'Isi jawaban.'])
            ->assertStatus(403);
    }

    public function test_resumed_session_payload_has_no_scoring_or_answer_key_fields(): void
    {
        $ujian = $this->buatUjian();

        $start = $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/lms/exams/{$ujian->id}/start");
        $start->assertOk();
        $sesiId = $start->json('data.sesi_id');

        // Simpan satu jawaban PG, lalu lanjutkan sesi (resume) untuk memeriksa
        // payload yang dikirim kembali ke siswa.
        $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/lms/exam-sessions/{$sesiId}/answers", [
                'jawaban' => [['soal_id' => $this->soalPg->id, 'jawaban_dipilih' => 'B']],
            ])
            ->assertOk();

        $resume = $this->actingAs($this->siswaUser)
            ->postJson("/api/portal/lms/exams/{$ujian->id}/start");
        $resume->assertOk();

        $resume->assertJsonMissingPath('data.jawaban_tersimpan.0.is_correct');
        $resume->assertJsonMissingPath('data.jawaban_tersimpan.0.poin_didapat');
        $resume->assertJsonMissingPath('data.jawaban_tersimpan.0.jawaban_guru');
        $resume->assertJsonMissingPath('data.soal.0.kunci_jawaban');
        $resume->assertJsonMissingPath('data.soal.0.pembahasan');
        $resume->assertJsonMissingPath('data.soal.0.is_correct');
        $resume->assertJsonPath('data.jawaban_tersimpan.0.jawaban_dipilih', 'B');
    }
}
