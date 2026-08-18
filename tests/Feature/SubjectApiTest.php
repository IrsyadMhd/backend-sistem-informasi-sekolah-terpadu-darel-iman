<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SubjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected EducationUnit $unit;

    protected MasterKurikulum $kurikulum;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');

        $this->unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity Test',
            'code' => 'SMP-AG-TEST',
            'level' => 'SMP',
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
            'kode_kurikulum' => 'KUR-SMP-TEST',
            'nama_kurikulum' => 'Kurikulum SMP Test',
            'jenis_kurikulum' => 'Merdeka',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $tahun->id,
            'tanggal_mulai' => '2025-07-15',
            'status' => true,
        ]);
    }

    public function test_dapat_mengambil_daftar_mata_pelajaran(): void
    {
        Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-IPA-SMP',
            'nama_mapel' => 'Ilmu Pengetahuan Alam',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 4,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/master/subjects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'statistik',
            ]);
    }

    public function test_dapat_menambah_mata_pelajaran_via_api(): void
    {
        $payload = [
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-IPS-STORE',
            'nama_mapel' => 'Ilmu Pengetahuan Sosial',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'tingkat_kelas' => 'Kelas 7',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
            'deskripsi' => 'Testing API Store Subject',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/subjects', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode_mapel', 'MP-IPS-STORE');

        $this->assertDatabaseHas('subjects', [
            'kode_mapel' => 'MP-IPS-STORE',
            'kurikulum_id' => $this->kurikulum->id,
        ]);
    }

    public function test_mencegah_duplikasi_kode_mapel_dalam_kurikulum_sama(): void
    {
        Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-DUPLICATE',
            'nama_mapel' => 'Mapel Pertama',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $payloadDuplicate = [
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-DUPLICATE',
            'nama_mapel' => 'Mapel Kedua Sama',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/master/subjects', $payloadDuplicate);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kode_mapel']);
    }

    public function test_dapat_melihat_detail_mata_pelajaran(): void
    {
        $subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-SHOW-01',
            'nama_mapel' => 'Mapel Detail Show',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/master/subjects/{$subject->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $subject->id)
            ->assertJsonPath('data.kode_mapel', 'MP-SHOW-01');
    }

    public function test_dapat_mengubah_mata_pelajaran_via_api(): void
    {
        $subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-PUT-01',
            'nama_mapel' => 'Nama Sebelum Ubah',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $payload = [
            'nama_mapel' => 'Nama Setelah Ubah API',
            'jam_pelajaran' => 4,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/master/subjects/{$subject->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_mapel', 'Nama Setelah Ubah API')
            ->assertJsonPath('data.jam_pelajaran', 4);
    }

    public function test_dapat_menghapus_dan_memulihkan_mata_pelajaran_via_api(): void
    {
        $subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'MP-DEL-API',
            'nama_mapel' => 'Mapel Hapus API',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 2,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $delResponse = $this->actingAs($this->user)
            ->deleteJson("/api/master/subjects/{$subject->id}");

        $delResponse->assertStatus(200);
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);

        $restoreResponse = $this->actingAs($this->user)
            ->postJson("/api/master/subjects/{$subject->id}/restore");

        $restoreResponse->assertStatus(200);
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'deleted_at' => null,
        ]);
    }

    public function test_menolak_kurikulum_dari_unit_pendidikan_lain(): void
    {
        $otherUnit = EducationUnit::create([
            'name' => 'Unit Lain',
            'code' => 'UNIT-OTHER',
            'level' => 'SMP',
            'is_active' => true,
        ]);
        $otherYear = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => false,
        ]);
        $otherKurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KUR-OTHER',
            'nama_kurikulum' => 'Kurikulum Unit Lain',
            'jenis_kurikulum' => 'Merdeka',
            'unit_pendidikan_id' => $otherUnit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $otherYear->id,
            'tanggal_mulai' => '2026-07-01',
            'status' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/master/subjects', [
                'unit_pendidikan_id' => $this->unit->id,
                'kurikulum_id' => $otherKurikulum->id,
                'kode_mapel' => 'MP-CROSS-UNIT',
                'nama_mapel' => 'Mapel Lintas Unit',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMP',
                'jam_pelajaran' => 2,
                'kkm' => 75,
                'status' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kurikulum_id']);
    }

    public function test_import_mata_pelajaran_menyimpan_data_dan_export_menghasilkan_file(): void
    {
        $csv = implode("\n", [
            'unit_pendidikan_id,kurikulum_id,kode_mapel,nama_mapel,jenjang,jam_pelajaran,kkm,status',
            "{$this->unit->id},{$this->kurikulum->id},MP-IMPORT,Mapel Import,SMP,3,75,aktif",
        ]);

        $import = $this->actingAs($this->user)
            ->post('/api/master/subjects/import', [
                'file' => UploadedFile::fake()->createWithContent('subjects.csv', $csv),
            ])
            ->assertOk();

        $import->assertJsonPath('data.imported_rows', 1);
        $this->assertDatabaseHas('subjects', ['kode_mapel' => 'MP-IMPORT']);

        $this->actingAs($this->user)
            ->get('/api/master/subjects/export/excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->user)
            ->get('/api/master/subjects/export/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
