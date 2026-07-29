<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulAjarApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EducationUnit $unit;
    protected AcademicYear $tahun;
    protected Semester $semester;
    protected MasterKurikulum $kurikulum;
    protected Subject $subject;
    protected Employee $guru;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin.modul@sekolah.sch.id',
        ]);

        $this->unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity',
            'code' => 'SMP-AG',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $this->tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'tahun' => '2025/2026',
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahun->id,
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
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'PAI-SMP',
            'nama_mapel' => 'Pendidikan Agama Islam',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $this->guru = Employee::create([
            'niy' => '199505052022011003',
            'nama_lengkap' => 'Ustadz Hanif, S.Pd.I',
            'email' => 'hanif@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kode_kelas' => 'K-7-A',
            'nama_kelas' => '7-A',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => true,
        ]);
    }

    public function test_dapat_mengambil_daftar_modul_ajar(): void
    {
        LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-7-01',
            'judul_modul' => 'Al-Qur\'an dan Kelestarian Alam',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 4,
            'status' => 'Publish',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/lms/modul-ajar');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'statistik',
            ]);
    }

    public function test_dapat_membuat_modul_ajar_via_api(): void
    {
        $payload = [
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-API-01',
            'judul_modul' => 'Modul Baru via API Test',
            'fase' => 'Fase D',
            'semester' => 'Ganjil',
            'alokasi_waktu_jp' => 4,
            'status' => 'Draft',
            'versi' => '1.0',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/lms/modul-ajar', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_modul', 'MA-API-01')
            ->assertJsonPath('data.judul_modul', 'Modul Baru via API Test');

        $this->assertDatabaseHas('lms_modul_ajar', [
            'kode_modul' => 'MA-API-01',
        ]);
    }

    public function test_dapat_mempublikasikan_dan_mendapatkan_revisi(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-REV-01',
            'judul_modul' => 'Modul Revisi Test',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $pubResp = $this->actingAs($this->user)
            ->postJson("/api/lms/modul-ajar/{$modul->id}/publish");

        $pubResp->assertStatus(200)
            ->assertJsonPath('data.status', 'Publish');

        $revResp = $this->actingAs($this->user)
            ->getJson("/api/lms/modul-ajar/{$modul->id}/revisions");

        $revResp->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data']);
    }

    public function test_dapat_menduplikasi_dan_menghapus_modul_ajar(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DUP-01',
            'judul_modul' => 'Modul Asli',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Publish',
        ]);

        $dupResp = $this->actingAs($this->user)
            ->postJson("/api/lms/modul-ajar/{$modul->id}/duplicate");

        $dupResp->assertStatus(201)
            ->assertJsonPath('data.judul_modul', 'Modul Asli (Salinan)');

        $delResp = $this->actingAs($this->user)
            ->deleteJson("/api/lms/modul-ajar/{$modul->id}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('lms_modul_ajar', ['id' => $modul->id]);
    }
}
