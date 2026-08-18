<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TujuanPembelajaranApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CapaianPembelajaran $cp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $unit = EducationUnit::create([
            'code' => 'SD-01',
            'name' => 'SD Terpadu Test',
            'level' => 'SD',
            'is_active' => true,
        ]);

        $ta = AcademicYear::create([
            'name' => '2025/2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);

        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-TEST',
            'nama_kurikulum' => 'Kurikulum Merdeka Test',
            'jenis_kurikulum' => 'Merdeka',
            'unit_pendidikan_id' => $unit->id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $ta->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $subject = Subject::create([
            'code' => 'MTK-01',
            'name' => 'Matematika Dasar',
            'category' => 'Wajib',
        ]);

        $this->cp = CapaianPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'mata_pelajaran_id' => $subject->id,
            'kode_cp' => 'CP-MTK-1',
            'nama_cp' => 'Capaian Pembelajaran Matematika Kelas 1',
            'deskripsi' => 'Peserta didik dapat memahami konsep aljabar dasar.',
            'fase' => 'A',
            'kelas_target' => '1',
            'urutan' => 1,
            'status' => true,
        ]);
    }

    public function test_can_fetch_tujuan_pembelajaran_list(): void
    {
        TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-1-1',
            'nama_tp' => 'Mengenal bilangan cacah',
            'deskripsi' => 'Peserta didik dapat mengenal bilangan 1-20.',
            'alokasi_waktu_jp' => 4,
            'urutan' => 1,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/lms/tujuan-pembelajaran');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'cp_id', 'kode_tp', 'deskripsi_tp', 'status'],
                ],
            ]);
    }

    public function test_can_create_tujuan_pembelajaran(): void
    {
        $payload = [
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-1-2',
            'deskripsi_tp' => 'Peserta didik mampu menjumlahkan dua bilangan cacah.',
            'alokasi_waktu_jp' => 2,
            'urutan' => 2,
            'status' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/lms/tujuan-pembelajaran', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tujuan Pembelajaran berhasil ditambahkan.',
            ]);

        $this->assertDatabaseHas('lms_tujuan_pembelajaran', [
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-1-2',
        ]);
    }

    public function test_fails_to_create_tp_without_cp_id(): void
    {
        $payload = [
            'deskripsi_tp' => 'Peserta didik mampu mengukur panjang benda.',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/lms/tujuan-pembelajaran', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cp_id']);
    }

    public function test_can_show_tujuan_pembelajaran_detail(): void
    {
        $tp = TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-1-3',
            'nama_tp' => 'Pengurangan bilangan',
            'deskripsi' => 'Peserta didik mampu melakukan pengurangan sederhana.',
            'urutan' => 3,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/lms/tujuan-pembelajaran/{$tp->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tp->id);
    }

    public function test_can_update_tujuan_pembelajaran(): void
    {
        $tp = TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-MTK-1-4',
            'nama_tp' => 'Geometri Dasar',
            'deskripsi' => 'Mengenal bentuk bangun datar.',
            'urutan' => 4,
            'status' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/lms/tujuan-pembelajaran/{$tp->id}", [
                'deskripsi_tp' => 'Mengenal bentuk bangun datar dan ruang.',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('lms_tujuan_pembelajaran', [
            'id' => $tp->id,
            'deskripsi' => 'Mengenal bentuk bangun datar dan ruang.',
        ]);
    }

    public function test_can_soft_delete_and_restore_tp(): void
    {
        $tp = TujuanPembelajaran::create([
            'cp_id' => $this->cp->id,
            'kode_tp' => 'TP-DEL-1',
            'nama_tp' => 'Tes Hapus',
            'deskripsi' => 'Akan dihapus',
            'status' => true,
        ]);

        // Soft Delete
        $delResponse = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/lms/tujuan-pembelajaran/{$tp->id}");

        $delResponse->assertStatus(200);
        $this->assertSoftDeleted('lms_tujuan_pembelajaran', ['id' => $tp->id]);

        // Restore
        $resResponse = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/lms/tujuan-pembelajaran/{$tp->id}/restore");

        $resResponse->assertStatus(200);
        $this->assertNotSoftDeleted('lms_tujuan_pembelajaran', ['id' => $tp->id]);
    }

    public function test_can_fetch_options_and_stats(): void
    {
        $optResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/lms/tujuan-pembelajaran/options');

        $optResponse->assertStatus(200)
            ->assertJsonStructure(['data' => ['capaian_pembelajaran']]);

        $statResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/lms/tujuan-pembelajaran/stats');

        $statResponse->assertStatus(200)
            ->assertJsonStructure(['data' => ['total_tp', 'total_tp_aktif', 'total_cp', 'cp_ber_tp']]);
    }
}
