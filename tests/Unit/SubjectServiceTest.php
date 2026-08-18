<?php

namespace Tests\Unit;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SubjectService $service;

    protected EducationUnit $unit;

    protected MasterKurikulum $kurikulum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubjectService::class);

        $this->unit = EducationUnit::create([
            'name' => 'SD IT Antigravity Test',
            'code' => 'SD-AG-TEST',
            'level' => 'SD',
            'is_active' => true,
        ]);

        $tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $this->kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-TEST-SD',
            'nama_kurikulum' => 'Kurikulum Test SD',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);
    }

    public function test_dapat_menambah_mata_pelajaran_baru(): void
    {
        $payload = [
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-PAI-01',
            'nama_mapel' => 'Pendidikan Agama Islam Test',
            'code' => 'MP-PAI-01',
            'name' => 'Pendidikan Agama Islam Test',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SD',
            'tingkat_kelas' => 'All',
            'jam_pelajaran' => 4,
            'kkm' => 75.00,
            'status' => true,
            'deskripsi' => 'Pengujian Service Subject',
        ];

        $subject = $this->service->simpan($payload);

        $this->assertInstanceOf(Subject::class, $subject);
        $this->assertDatabaseHas('subjects', [
            'kode_mapel' => 'MP-PAI-01',
            'nama_mapel' => 'Pendidikan Agama Islam Test',
            'kurikulum_id' => $this->kurikulum->id,
        ]);
    }

    public function test_dapat_memperbarui_mata_pelajaran(): void
    {
        $subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-MTK-OLD',
            'nama_mapel' => 'Matematika Lama',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SD',
            'jam_pelajaran' => 4,
            'kkm' => 70.00,
            'status' => true,
        ]);

        $updated = $this->service->ubah($subject->id, [
            'nama_mapel' => 'Matematika Terupdate',
            'kkm' => 75.00,
        ]);

        $this->assertEquals('Matematika Terupdate', $updated->nama_mapel);
        $this->assertEquals(75.00, $updated->kkm);
    }

    public function test_dapat_soft_delete_dan_restore_mata_pelajaran(): void
    {
        $subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-DEL-01',
            'nama_mapel' => 'Mapel Dihapus',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SD',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $deleted = $this->service->hapus($subject->id);
        $this->assertTrue($deleted);
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);

        $restored = $this->service->pulihkan($subject->id);
        $this->assertTrue($restored);
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'deleted_at' => null,
        ]);
    }
}
