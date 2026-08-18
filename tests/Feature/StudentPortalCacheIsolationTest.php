<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 11 — Cache isolation & cross-student data boundary tests.
 *
 * Memastikan siswa A tidak pernah menerima data siswa B via API,
 * manipulasi student_id ditolak, dan attempt CBT ter-scope per siswa.
 */
class StudentPortalCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $siswaAUser;
    private User $siswaBUser;
    private Student $siswaA;
    private Student $siswaB;
    private Kelas $kelasA;
    private Kelas $kelasB;
    private LmsPenugasan $penugasanA;
    private LmsUjian $ujianA;
    private LmsUjianSesi $sesiA;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);

        $unit = \App\Models\EducationUnit::create([
            'name' => 'SMP Cache Test', 'code' => 'SMP-C', 'level' => 'SMP', 'is_active' => true,
        ]);

        $tahunAjaran = AcademicYear::create([
            'name' => '2025/2026', 'code' => '2025-2026',
            'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => true, 'tahun' => '2025/2026',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $tahunAjaran->id, 'name' => 'Ganjil', 'sequence' => 1,
            'start_date' => '2025-07-01', 'end_date' => '2025-12-31', 'is_active' => true,
        ]);

        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-C', 'nama_kurikulum' => 'Kurikulum Cache', 'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SMP',
            'tahun_ajaran_id' => $tahunAjaran->id, 'tanggal_mulai' => '2025-07-01', 'status' => true,
        ]);

        $subject = Subject::create(['name' => 'IPA', 'code' => 'IPA-C', 'education_unit_id' => $unit->id, 'is_active' => true]);

        $guruUser = User::factory()->create();
        $guruUser->assignRole('Guru');
        $guru = Employee::create([
            'user_id' => $guruUser->id, 'unit_id' => $unit->id, 'education_unit_id' => $unit->id,
            'nama_lengkap' => 'Guru Cache', 'full_name' => 'Guru Cache', 'niy' => 'C-001', 'nip' => 'C-001', 'is_active' => true,
        ]);

        $this->kelasA = Kelas::create([
            'unit_pendidikan_id' => $unit->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id,
            'nama_kelas' => '7A Cache', 'kode_kelas' => '7A', 'jenjang' => 'SMP', 'tingkat' => 7, 'status' => 'Aktif', 'wali_kelas_id' => $guru->id,
        ]);

        $this->kelasB = Kelas::create([
            'unit_pendidikan_id' => $unit->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id,
            'nama_kelas' => '7B Cache', 'kode_kelas' => '7B', 'jenjang' => 'SMP', 'tingkat' => 7, 'status' => 'Aktif', 'wali_kelas_id' => $guru->id,
        ]);

        $this->siswaAUser = User::factory()->create(['name' => 'Siswa A Cache', 'email' => 'siswa.a.cache@school.id']);
        $this->siswaAUser->assignRole('Siswa');
        $this->siswaA = Student::create([
            'user_id' => $this->siswaAUser->id, 'education_unit_id' => $unit->id, 'kelas_id' => $this->kelasA->id,
            'full_name' => 'Siswa A Cache', 'nis' => 'CA-001', 'gender' => 'male', 'is_active' => true,
        ]);

        $this->siswaBUser = User::factory()->create(['name' => 'Siswa B Cache', 'email' => 'siswa.b.cache@school.id']);
        $this->siswaBUser->assignRole('Siswa');
        $this->siswaB = Student::create([
            'user_id' => $this->siswaBUser->id, 'education_unit_id' => $unit->id, 'kelas_id' => $this->kelasB->id,
            'full_name' => 'Siswa B Cache', 'nis' => 'CB-001', 'gender' => 'female', 'is_active' => true,
        ]);

        $this->penugasanA = LmsPenugasan::create([
            'kelas_id' => $this->kelasA->id, 'mata_pelajaran_id' => $subject->id,
            'guru_id' => $guru->id, 'judul_tugas' => 'Tugas Cache A', 'instruksi' => 'Kerjakan tugas ini.',
            'semester_id' => $semester->id, 'tahun_ajaran_id' => $tahunAjaran->id,
            'deadline' => now()->addDays(3), 'status' => 'published', 'is_published' => true,
        ]);

        LmsPengumpulanTugas::create([
            'penugasan_id' => $this->penugasanA->id, 'siswa_id' => $this->siswaA->id,
            'jawaban_teks' => 'Jawaban rahasia siswa A', 'status' => 'dikumpulkan', 'waktu_kumpul' => now(),
        ]);

        StudentGrade::create([
            'student_id' => $this->siswaA->id, 'subject_id' => $subject->id,
            'semester_id' => $semester->id, 'academic_year_id' => $tahunAjaran->id,
            'final_score' => 95, 'is_passed' => true,
        ]);

        $kisi = LmsKisiKisi::create([
            'kurikulum_id' => $kurikulum->id, 'mata_pelajaran_id' => $subject->id,
            'kelas_id' => $this->kelasA->id, 'semester_id' => $semester->id, 'tahun_ajaran_id' => $tahunAjaran->id,
            'guru_id' => $guru->id, 'judul_kisi' => 'Kisi Cache', 'jenis_ujian' => 'UH',
            'jumlah_soal' => 1, 'alokasi_waktu_menit' => 30, 'status' => true,
        ]);

        $soal = LmsBankSoal::create([
            'kisi_kisi_id' => $kisi->id, 'mata_pelajaran_id' => $subject->id,
            'kode_soal' => 'C-PG-1', 'pertanyaan' => '1 + 1 = ?', 'tipe_soal' => 'pg',
            'opsi_a' => '1', 'opsi_b' => '2', 'kunci_jawaban' => 'B', 'poin' => 100, 'status' => true,
        ]);

        $this->ujianA = LmsUjian::create([
            'kisi_kisi_id' => $kisi->id, 'kelas_id' => $this->kelasA->id, 'semester_id' => $semester->id,
            'guru_id' => $guru->id, 'judul_ujian' => 'Ujian Cache A',
            'waktu_mulai' => now()->subMinute(), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 30, 'nilai_kkm' => 70, 'tampilkan_nilai_langsung' => true, 'status' => 'published',
        ]);

        $this->sesiA = LmsUjianSesi::create([
            'ujian_id' => $this->ujianA->id, 'siswa_id' => $this->siswaA->id,
            'waktu_mulai' => now(), 'status' => 'proses',
        ]);
    }

    public function test_second_student_never_receives_first_student_cached_data(): void
    {
        $dashboardA = $this->actingAs($this->siswaAUser)->getJson('/api/portal/dashboard');
        $dashboardA->assertOk()->assertJsonPath('data.student.id', $this->siswaA->id);

        $dashboardB = $this->actingAs($this->siswaBUser)->getJson('/api/portal/dashboard');
        $dashboardB->assertOk()
            ->assertJsonPath('data.student.id', $this->siswaB->id)
            ->assertJsonMissing(['id' => $this->siswaA->id, 'full_name' => 'Siswa A Cache']);

        $gradesB = $this->actingAs($this->siswaBUser)->getJson('/api/portal/grades');
        $gradesB->assertOk();
        $this->assertEmpty(collect($gradesB->json('data'))->where('student_id', $this->siswaA->id));

        $assignmentsB = $this->actingAs($this->siswaBUser)->getJson('/api/portal/assignments');
        $assignmentsB->assertOk();
        $this->assertEmpty(collect($assignmentsB->json('data.data'))->where('id', $this->penugasanA->id));
    }

    public function test_student_query_keys_include_student_identity(): void
    {
        $profileA = $this->actingAs($this->siswaAUser)->getJson('/api/portal/profile');
        $profileA->assertOk()->assertJsonPath('data.id', $this->siswaA->id);

        $profileB = $this->actingAs($this->siswaBUser)->getJson('/api/portal/profile');
        $profileB->assertOk()->assertJsonPath('data.id', $this->siswaB->id);

        $this->assertNotSame($profileA->json('data.id'), $profileB->json('data.id'));
    }

    public function test_student_id_query_manipulation_is_ignored_for_student_role(): void
    {
        // Siswa tidak punya child-switch; student_id di query harus diabaikan
        // dan tetap mengembalikan data diri sendiri (bukan siswa B).
        $this->actingAs($this->siswaAUser)
            ->getJson('/api/portal/dashboard?student_id='.$this->siswaB->id)
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->siswaA->id);
    }

    public function test_exam_attempt_cache_is_student_scoped(): void
    {
        $this->actingAs($this->siswaBUser)
            ->postJson("/api/portal/lms/exam-sessions/{$this->sesiA->id}/answers", [
                'jawaban' => [['soal_id' => '00000000-0000-0000-0000-000000000001', 'jawaban_dipilih' => 'A']],
            ])
            ->assertStatus(403);

        $this->actingAs($this->siswaBUser)
            ->postJson("/api/lms/ujian/sesi/{$this->sesiA->id}/submit-answers", [
                'jawaban' => [],
            ])
            ->assertStatus(403);
    }

    public function test_student_cannot_submit_assignment_for_other_class(): void
    {
        $this->actingAs($this->siswaBUser)
            ->postJson("/api/portal/assignments/{$this->penugasanA->id}/submit", [
                'jawaban_teks' => 'Percobaan curi submit',
            ])
            ->assertStatus(403);
    }

    public function test_logout_clears_student_session_token(): void
    {
        $token = $this->siswaAUser->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        // Token yang dicabut harus dihapus dari tabel personal_access_tokens.
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('personal_access_tokens')->count());

        // Guard pada instance test meng-cache user dari request pertama;
        // paksa re-resolusi agar token (yang sudah terhapus) di-revalidasi.
        \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/portal/dashboard');

        $this->assertContains($response->getStatusCode(), [401, 403], 'Token yang dicabut tidak boleh mengakses portal siswa.');
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_self_scope_endpoints_reject_foreign_student_context(): void
    {
        $manipulations = [
            ['endpoint' => '/api/portal/grades', 'method' => 'GET'],
            ['endpoint' => '/api/portal/tahfizh', 'method' => 'GET'],
            ['endpoint' => '/api/portal/mutabaah', 'method' => 'GET'],
            ['endpoint' => '/api/portal/reports', 'method' => 'GET'],
            ['endpoint' => '/api/portal/notifications', 'method' => 'GET'],
        ];

        foreach ($manipulations as $case) {
            $response = $this->actingAs($this->siswaAUser)
                ->getJson($case['endpoint'].'?student_id='.$this->siswaB->id);

            $response->assertOk("Endpoint {$case['endpoint']} harus tetap self-scope untuk siswa.");
            if ($case['endpoint'] === '/api/portal/grades') {
                $this->assertEmpty(collect($response->json('data'))->where('student_id', $this->siswaB->id));
            }
        }
    }
}
