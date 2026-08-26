<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
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
use App\Models\Semester;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsLearningTest extends TestCase
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

    public function test_materi_dan_media_crud_dan_normalisasi_input(): void
    {
        $unit = EducationUnit::create(['code' => 'SD-S05', 'name' => 'SD IT S05', 'level' => 'SD']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $kur = MasterKurikulum::create(['kode_kurikulum' => 'KUR-S05', 'nama_kurikulum' => 'Kurikulum SIT', 'jenis_kurikulum' => 'SIT', 'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SD', 'tahun_ajaran_id' => $ay->id, 'tanggal_mulai' => '2026-07-01']);
        $subj = Subject::create(['code' => 'PAI-S05', 'name' => 'PAI', 'education_unit_id' => $unit->id]);
        $emp = Employee::create(['nama_lengkap' => 'Guru PAI', 'jenis_kelamin' => 'L', 'unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'I A', 'level' => 1, 'education_unit_id' => $unit->id]);

        $modul = LmsModulAjar::create([
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'kurikulum_id' => $kur->id,
            'mata_pelajaran_id' => $subj->id,
            'guru_id' => $emp->id,
            'kelas_id' => $kelas->id,
            'judul_modul' => 'Materi Adab',
            'fase' => 'A',
            'alokasi_waktu_jp' => 2,
        ]);

        // 1. Create Materi
        $materiRes = $this->actingAs($this->user)->postJson('/api/materi', [
            'modul_ajar_id' => $modul->id,
            'judul' => '  Mengenal   Adab   Makan  ',
            'isi' => "Line 1\nLine 2", // Must keep multiline
            'tipe' => 'dokumen',
        ]);

        $materiRes->assertCreated()
            ->assertJsonPath('data.judul', 'Mengenal Adab Makan')
            ->assertJsonPath('data.isi', "Line 1\nLine 2");

        $materiId = $materiRes->json('data.id');

        // 2. Create Media
        $mediaRes = $this->actingAs($this->user)->postJson('/api/media', [
            'materi_id' => $materiId,
            'nama_file' => '  Video   Adab   Makan  ',
            'tipe_file' => 'video',
            'url_eksternal' => 'https://www.youtube.com/watch?v=example',
        ]);

        $mediaRes->assertCreated()
            ->assertJsonPath('data.nama_file', 'Video Adab Makan');
    }

    public function test_referensi_keamanan_url_protocol(): void
    {
        // Penolakan javascript: URL
        $unsafeRes = $this->actingAs($this->user)->postJson('/api/referensi', [
            'judul' => 'Buku Referensi',
            'url' => 'javascript:alert("hacked")',
        ]);

        $unsafeRes->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);

        // Success valid URL
        $safeRes = $this->actingAs($this->user)->postJson('/api/referensi', [
            'judul' => '  Buku   Tuntunan   Shalat  ',
            'url' => 'https://dareliman.sch.id/buku-tuntunan',
        ]);

        $safeRes->assertCreated()
            ->assertJsonPath('data.judul', 'Buku Tuntunan Shalat');
    }

    public function test_aktivitas_dan_diskusi_crud(): void
    {
        $unit = EducationUnit::create(['code' => 'SMP-S05', 'name' => 'SMP IT S05', 'level' => 'SMP']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $kur = MasterKurikulum::create(['kode_kurikulum' => 'KUR-SMP-S05', 'nama_kurikulum' => 'Kurikulum SIT', 'jenis_kurikulum' => 'SIT', 'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SMP', 'tahun_ajaran_id' => $ay->id, 'tanggal_mulai' => '2026-07-01']);
        $subj = Subject::create(['code' => 'IPS-S05', 'name' => 'IPS', 'education_unit_id' => $unit->id]);
        $emp = Employee::create(['nama_lengkap' => 'Guru IPS', 'jenis_kelamin' => 'P', 'unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'VIII A', 'level' => 8, 'education_unit_id' => $unit->id]);

        $modul = LmsModulAjar::create([
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'kurikulum_id' => $kur->id,
            'mata_pelajaran_id' => $subj->id,
            'guru_id' => $emp->id,
            'kelas_id' => $kelas->id,
            'judul_modul' => 'Modul IPS Interaksi Sosial',
            'fase' => 'D',
            'alokasi_waktu_jp' => 4,
        ]);

        // 1. Aktivitas
        $aktRes = $this->actingAs($this->user)->postJson('/api/aktivitas-belajar', [
            'modul_ajar_id' => $modul->id,
            'nama_aktivitas' => '  Diskusi   Kelompok   Interaksi  ',
            'jenis_aktivitas' => 'Diskusi',
            'instruksi' => "Diskusikan dalam kelompok masing-masing.\nTuliskan kesimpulan.",
            'waktu' => 45,
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        $aktRes->assertCreated()
            ->assertJsonPath('data.nama_aktivitas', 'Diskusi Kelompok Interaksi')
            ->assertJsonPath('data.instruksi', "Diskusikan dalam kelompok masing-masing.\nTuliskan kesimpulan.");

        // 2. Diskusi
        $disRes = $this->actingAs($this->user)->postJson('/api/lms/diskusi', [
            'modul_ajar_id' => $modul->id,
            'judul' => '  Forum   Tanya   Jawab   IPS  ',
            'deskripsi' => "Silakan tanyakan hal yang kurang dipahami.",
            'status' => 'aktif',
        ]);

        $disRes->assertCreated()
            ->assertJsonPath('data.judul', 'Forum Tanya Jawab IPS');
    }
}
