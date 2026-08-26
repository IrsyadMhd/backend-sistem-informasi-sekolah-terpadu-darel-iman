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
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPlanningTest extends TestCase
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

    public function test_cp_dan_tp_crud_dan_normalisasi_input(): void
    {
        $unit = EducationUnit::create(['code' => 'SD-S04', 'name' => 'SD IT S04', 'level' => 'SD']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $kurikulum = MasterKurikulum::create(['kode_kurikulum' => 'KUR-S04', 'nama_kurikulum' => 'Kurikulum Merdeka', 'jenis_kurikulum' => 'Merdeka', 'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SD', 'tahun_ajaran_id' => $ay->id, 'tanggal_mulai' => '2026-07-01']);
        $subject = Subject::create(['code' => 'MTK-S04', 'name' => 'Matematika', 'education_unit_id' => $unit->id]);

        // 1. Create CP
        $cpRes = $this->actingAs($this->user)->postJson('/api/master/capaian-pembelajaran', [
            'kurikulum_id' => $kurikulum->id,
            'mata_pelajaran_id' => $subject->id,
            'kode_cp' => '  cp-mtk-01  ',
            'nama_cp' => '  Peserta   didik   mampu   menghitung  ',
            'fase' => 'A',
        ]);

        $cpRes->assertCreated()
            ->assertJsonPath('data.kode_cp', 'CP-MTK-01')
            ->assertJsonPath('data.nama_cp', 'Peserta didik mampu menghitung');

        $cpId = $cpRes->json('data.id');

        // 2. Create TP
        $tpRes = $this->actingAs($this->user)->postJson('/api/master/tujuan-pembelajaran', [
            'cp_id' => $cpId,
            'kode_tp' => '  tp-mtk-01  ',
            'nama_tp' => '  Mengenal   bilangan   1-100  ',
        ]);

        $tpRes->assertCreated()
            ->assertJsonPath('data.kode_tp', 'TP-MTK-01')
            ->assertJsonPath('data.nama_tp', 'Mengenal bilangan 1-100');
    }

    public function test_modul_ajar_dan_export_pdf(): void
    {
        $unit = EducationUnit::create(['code' => 'SMP-S04', 'name' => 'SMP IT S04', 'level' => 'SMP']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $kur = MasterKurikulum::create(['kode_kurikulum' => 'KUR-SMP-S04', 'nama_kurikulum' => 'Kurikulum SIT', 'jenis_kurikulum' => 'SIT', 'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SMP', 'tahun_ajaran_id' => $ay->id, 'tanggal_mulai' => '2026-07-01']);
        $subj = Subject::create(['code' => 'IPA-S04', 'name' => 'IPA Terpadu', 'education_unit_id' => $unit->id]);
        $emp = Employee::create(['nama_lengkap' => 'Guru IPA', 'jenis_kelamin' => 'L', 'unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'VII A', 'level' => 7, 'education_unit_id' => $unit->id]);

        $res = $this->actingAs($this->user)->postJson('/api/modul-ajar', [
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'kurikulum_id' => $kur->id,
            'mata_pelajaran_id' => $subj->id,
            'guru_id' => $emp->id,
            'kelas_id' => $kelas->id,
            'kode_modul' => '  ma-ipa-01  ',
            'judul_modul' => '  Ekosistem   dan   Lingkungan  ',
            'fase' => 'D',
            'alokasi_waktu_jp' => 4,
            'status' => 'Publish',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.kode_modul', 'MA-IPA-01')
            ->assertJsonPath('data.judul_modul', 'Ekosistem dan Lingkungan');

        $modulId = $res->json('data.id');

        // Test Export PDF
        $pdfRes = $this->actingAs($this->user)->getJson("/api/modul-ajar/{$modulId}/export/pdf");
        $pdfRes->assertOk();
    }

    public function test_kisi_kisi_dan_bank_soal_crud(): void
    {
        $unit = EducationUnit::create(['code' => 'SMA-S04', 'name' => 'SMA IT S04', 'level' => 'SMA']);
        $subj = Subject::create(['code' => 'BIO-S04', 'name' => 'Biologi', 'education_unit_id' => $unit->id]);

        // 1. Create Kisi-Kisi
        $kisiRes = $this->actingAs($this->user)->postJson('/api/lms/kisi-kisi', [
            'judul_kisi' => '  Kisi-Kisi   PTS   Biologi  ',
            'mata_pelajaran_id' => $subj->id,
            'jenis_ujian' => 'PTS',
            'jumlah_soal' => 20,
            'alokasi_waktu_menit' => 90,
        ]);

        $kisiRes->assertCreated()
            ->assertJsonPath('data.judul_kisi', 'Kisi-Kisi PTS Biologi');

        $kisiId = $kisiRes->json('data.id');

        // 2. Create Bank Soal
        $soalRes = $this->actingAs($this->user)->postJson('/api/lms/bank-soal', [
            'kisi_kisi_id' => $kisiId,
            'mata_pelajaran_id' => $subj->id,
            'kode_soal' => '  soal-bio-01  ',
            'pertanyaan' => 'Apa fungsi klorofil pada tumbuhan?',
            'tipe_soal' => 'pg',
            'opsi_a' => 'Fotosintesis',
            'opsi_b' => 'Respirasi',
            'kunci_jawaban' => 'opsi_a',
        ]);

        $soalRes->assertCreated()
            ->assertJsonPath('data.kode_soal', 'SOAL-BIO-01');
    }
}
