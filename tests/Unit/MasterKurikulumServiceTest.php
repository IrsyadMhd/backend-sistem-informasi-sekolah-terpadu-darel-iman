<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Services\MasterKurikulumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterKurikulumServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MasterKurikulumService $service;

    protected EducationUnit $unit;

    protected AcademicYear $tahun;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MasterKurikulumService::class);

        $this->unit = EducationUnit::create([
            'name' => 'SD IT Antigravity',
            'code' => 'SD-AG',
            'level' => 'SD',
            'is_active' => true,
        ]);

        $this->tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahun->id,
            'name' => 'Ganjil',
            'semester_number' => 1,
            'is_active' => true,
        ]);
    }

    public function test_dapat_menambah_kurikulum_baru(): void
    {
        $payload = [
            'kode_kurikulum' => 'KUR-SD-TEST-01',
            'nama_kurikulum' => 'Kurikulum SD Test',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'status' => true,
            'deskripsi' => 'Pengujian Service Master Kurikulum',
        ];

        $result = $this->service->simpan($payload);

        $this->assertInstanceOf(MasterKurikulum::class, $result);
        $this->assertDatabaseHas('master_kurikulum', [
            'kode_kurikulum' => 'KUR-SD-TEST-01',
            'nama_kurikulum' => 'Kurikulum SD Test',
            'unit_pendidikan_id' => $this->unit->id,
        ]);
    }

    public function test_dapat_memperbarui_kurikulum(): void
    {
        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-SD-UPDATE',
            'nama_kurikulum' => 'Kurikulum Lama',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $updated = $this->service->ubah($kurikulum->id, [
            'nama_kurikulum' => 'Kurikulum Baru Terupdate',
            'status' => false,
        ]);

        $this->assertEquals('Kurikulum Baru Terupdate', $updated->nama_kurikulum);
        $this->assertFalse($updated->status);
    }

    public function test_dapat_melakukan_soft_delete_dan_restore(): void
    {
        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-SD-DELETE',
            'nama_kurikulum' => 'Kurikulum Dihapus',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $resDelete = $this->service->hapus($kurikulum->id);
        $this->assertTrue($resDelete['success']);
        $this->assertSoftDeleted('master_kurikulum', ['id' => $kurikulum->id]);

        $resRestore = $this->service->pulihkan($kurikulum->id);
        $this->assertTrue($resRestore);
        $this->assertDatabaseHas('master_kurikulum', [
            'id' => $kurikulum->id,
            'deleted_at' => null,
        ]);
    }
}
