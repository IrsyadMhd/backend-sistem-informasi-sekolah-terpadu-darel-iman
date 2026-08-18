<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Services\LmsModulAjarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulAjarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LmsModulAjarService $service;

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
        $this->service = app(LmsModulAjarService::class);

        $this->unit = EducationUnit::create([
            'name' => 'SMA IT Antigravity Test',
            'code' => 'SMA-AG-TEST',
            'level' => 'SMA',
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
            'kode_kurikulum' => 'KM-2025',
            'nama_kurikulum' => 'Kurikulum Merdeka 2025',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMA',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'PAI-10',
            'nama_mapel' => 'Pendidikan Agama Islam X',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMA',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $this->guru = Employee::create([
            'niy' => '199202022022011002',
            'nama_lengkap' => 'Ustadz Abdullah, S.Pd',
            'email' => 'abdullah.test@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kode_kelas' => 'K-X-1',
            'nama_kelas' => 'X-IPA-1',
            'jenjang' => 'SMA',
            'tingkat' => 10,
            'status' => true,
        ]);
    }

    public function test_dapat_menambah_modul_ajar_baru(): void
    {
        $payload = [
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-TEST-01',
            'judul_modul' => 'Toleransi Beragama dalam Islam',
            'fase' => 'Fase E',
            'semester' => 'Ganjil',
            'alokasi_waktu_jp' => 4,
            'status' => 'Draft',
            'versi' => '1.0',
        ];

        $modul = $this->service->simpan($payload);

        $this->assertInstanceOf(LmsModulAjar::class, $modul);
        $this->assertDatabaseHas('lms_modul_ajar', [
            'kode_modul' => 'MA-TEST-01',
            'judul_modul' => 'Toleransi Beragama dalam Islam',
            'guru_id' => $this->guru->id,
        ]);
    }

    public function test_dapat_menduplikasi_dan_mempublikasi_modul_ajar(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-ORIGINAL',
            'judul_modul' => 'Modul Original',
            'fase' => 'Fase E',
            'alokasi_waktu_jp' => 4,
            'status' => 'Draft',
            'versi' => '1.0',
        ]);

        $published = $this->service->publikasikan($modul->id);
        $this->assertEquals('Publish', $published->status);

        $duplicate = $this->service->duplikasi($modul->id);
        $this->assertInstanceOf(LmsModulAjar::class, $duplicate);
        $this->assertEquals('Modul Original (Salinan)', $duplicate->judul_modul);
        $this->assertEquals('Draft', $duplicate->status);
    }

    public function test_dapat_soft_delete_dan_restore_modul_ajar(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DEL-01',
            'judul_modul' => 'Modul Dihapus',
            'fase' => 'Fase E',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $deleted = $this->service->hapus($modul->id);
        $this->assertTrue($deleted);
        $this->assertSoftDeleted('lms_modul_ajar', ['id' => $modul->id]);

        $restored = $this->service->pulihkan($modul->id);
        $this->assertTrue($restored);
        $this->assertDatabaseHas('lms_modul_ajar', [
            'id' => $modul->id,
            'deleted_at' => null,
        ]);
    }
}
