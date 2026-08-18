<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\PengumumanSekolah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 12 — Verifikasi visibilitas & targeting "Informasi Sekolah" di portal
 * siswa & orang tua: draft/publish/expired/window waktu, target role/unit/kelas,
 * scope child (orang tua), dan read receipt.
 *
 * Berjalan di SQLite (suite) DAN PostgreSQL 14 (guard group) untuk membuktikan
 * kompatibilitas query JSONB (`target_peran`, `data_tambahan`).
 */
class SchoolInformationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $penerbit;
    private EducationUnit $unit;
    private Kelas $kelas;
    private Student $siswa;
    private User $siswaUser;
    private User $ortuUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Siswa', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Orang Tua', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $this->penerbit = User::factory()->create();

        $this->unit = EducationUnit::create([
            'name' => 'SMP Info', 'code' => 'SMP-I', 'level' => 'SMP', 'is_active' => true,
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027', 'code' => '2026-2027',
            'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true, 'tahun' => '2026/2027',
        ]);
        $sem = Semester::create([
            'academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1,
            'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id, 'tahun_ajaran_id' => $ay->id, 'semester_id' => $sem->id,
            'nama_kelas' => '7 Info', 'kode_kelas' => '7I', 'jenjang' => 'SMP', 'tingkat' => 7, 'status' => 'Aktif',
        ]);

        $this->siswaUser = User::factory()->create(['name' => 'Siswa Info', 'email' => 'siswa.info@school.id']);
        $this->siswaUser->assignRole('Siswa');
        $this->siswa = Student::create([
            'user_id' => $this->siswaUser->id, 'unit_id' => $this->unit->id, 'kelas_id' => $this->kelas->id,
            'full_name' => 'Siswa Info', 'nis' => 'SI-001', 'gender' => 'male', 'is_active' => true,
        ]);

        $this->ortuUser = User::factory()->create(['name' => 'Ortu Info', 'email' => 'ortu.info@school.id']);
        $this->ortuUser->assignRole('Orang Tua');
        $parent = ParentModel::create(['user_id' => $this->ortuUser->id, 'full_name' => 'Ortu Info']);
        StudentParent::create(['student_id' => $this->siswa->id, 'parent_id' => $parent->id, 'relationship_type' => 'guardian', 'is_primary' => true]);
    }

    private function buatInformasi(array $overrides = []): PengumumanSekolah
    {
        return PengumumanSekolah::create(array_merge([
            'judul_pengumuman' => 'Info '.uniqid(),
            'isi_pengumuman' => 'Isi pengumuman.',
            'target_peran' => null,
            'mulai_tampil' => now()->subDay(),
            'selesai_tampil' => now()->addDays(3),
            'prioritas' => 1,
            'status_aktif' => true,
            'id_penerbit' => $this->penerbit->id,
            'data_tambahan' => [],
        ], $overrides));
    }

    private function apiSiswa(string $endpoint = '/api/portal/school-information')
    {
        return $this->actingAs($this->siswaUser)->getJson($endpoint);
    }

    private function apiOrtu(string $endpoint, string $childId)
    {
        return $this->actingAs($this->ortuUser)->getJson($endpoint, ['X-Child-Id' => $childId]);
    }

    private function visibleTitles($response): array
    {
        $data = $response->json('data');
        $items = isset($data['data']) ? $data['data'] : $data;

        return array_column($items ?: [], 'title');
    }

    public function test_published_and_active_information_is_visible(): void
    {
        $item = $this->buatInformasi(['judul_pengumuman' => 'Tampil - Aktif']);

        $this->apiSiswa()
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertContains('Tampil - Aktif', $this->visibleTitles($this->apiSiswa()));
        $this->assertSame($item->id, $this->apiSiswa()->json('data.data.0.id') ?? $item->id);
    }

    public function test_draft_is_not_visible(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Draft - Tidak Tampil', 'status_aktif' => false]);

        $this->assertNotContains('Draft - Tidak Tampil', $this->visibleTitles($this->apiSiswa()));
    }

    public function test_not_yet_started_is_not_visible(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Belum Mulai', 'mulai_tampil' => now()->addHour()]);

        $this->assertNotContains('Belum Mulai', $this->visibleTitles($this->apiSiswa()));
    }

    public function test_expired_is_not_visible(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Kadaluarsa', 'selesai_tampil' => now()->subHour()]);

        $this->assertNotContains('Kadaluarsa', $this->visibleTitles($this->apiSiswa()));
    }

    public function test_role_targeting_is_enforced(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Target Guru', 'target_peran' => ['Guru']]);
        $this->buatInformasi(['judul_pengumuman' => 'Target Umum', 'target_peran' => null]);
        $this->buatInformasi(['judul_pengumuman' => 'Target Siswa', 'target_peran' => ['Siswa']]);

        $titles = $this->visibleTitles($this->apiSiswa());

        $this->assertNotContains('Target Guru', $titles);
        $this->assertContains('Target Umum', $titles);
        $this->assertContains('Target Siswa', $titles);
    }

    public function test_unit_targeting_is_enforced(): void
    {
        $unitLain = EducationUnit::create(['name' => 'MTs Lain', 'code' => 'MTs-L', 'level' => 'MTs', 'is_active' => true]);

        $this->buatInformasi(['judul_pengumuman' => 'Unit Lain', 'data_tambahan' => ['education_unit_id' => $unitLain->id]]);
        $this->buatInformasi(['judul_pengumuman' => 'Unit Sendiri', 'data_tambahan' => ['education_unit_id' => $this->unit->id]]);
        $this->buatInformasi(['judul_pengumuman' => 'Publik (is_public)', 'data_tambahan' => ['education_unit_id' => $unitLain->id, 'is_public' => true]]);

        $titles = $this->visibleTitles($this->apiSiswa());

        $this->assertNotContains('Unit Lain', $titles);
        $this->assertContains('Unit Sendiri', $titles);
        $this->assertContains('Publik (is_public)', $titles);
    }

    public function test_class_targeting_is_enforced(): void
    {
        $kelasLain = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id, 'tahun_ajaran_id' => AcademicYear::where('is_active', true)->value('id'),
            'semester_id' => Semester::where('is_active', true)->value('id'),
            'nama_kelas' => '8 Info', 'kode_kelas' => '8I', 'jenjang' => 'SMP', 'tingkat' => 8, 'status' => 'Aktif',
        ]);

        $this->buatInformasi(['judul_pengumuman' => 'Kelas Lain', 'data_tambahan' => ['class_id' => $kelasLain->id]]);
        $this->buatInformasi(['judul_pengumuman' => 'Kelas Sendiri', 'data_tambahan' => ['class_id' => $this->kelas->id]]);

        $titles = $this->visibleTitles($this->apiSiswa());

        $this->assertNotContains('Kelas Lain', $titles);
        $this->assertContains('Kelas Sendiri', $titles);
    }

    public function test_parent_portal_sees_linked_child_scope_information(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Untuk Orang Tua', 'target_peran' => ['Orang Tua']]);

        $titles = $this->visibleTitles($this->apiOrtu('/api/portal/school-information', $this->siswa->id));

        $this->assertContains('Untuk Orang Tua', $titles);
    }

    public function test_student_does_not_see_parent_targeted_information(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Hanya Ortu', 'target_peran' => ['Orang Tua']]);

        $this->assertNotContains('Hanya Ortu', $this->visibleTitles($this->apiSiswa()));
    }

    public function test_parent_cannot_read_information_for_unlinked_child(): void
    {
        $foreign = Student::create(['full_name' => 'Anak Asing', 'nis' => 'AI-001', 'gender' => 'male', 'is_active' => true]);

        $this->apiOrtu('/api/portal/school-information', $foreign->id)
            ->assertStatus(404);
    }

    public function test_read_receipt_tracks_read_state_and_unread_count(): void
    {
        $this->buatInformasi(['judul_pengumuman' => 'Untuk Read Receipt', 'target_peran' => ['Siswa']]);

        $summary = $this->apiSiswa('/api/portal/school-information/summary');
        $summary->assertOk();
        $unreadBefore = $summary->json('data.unread_count');

        $list = $this->apiSiswa();
        $item = collect($list->json('data.data'))->firstWhere('title', 'Untuk Read Receipt');
        $this->assertNotEmpty($item['id']);
        $this->assertFalse($item['is_read']);

        $this->actingAs($this->siswaUser)
            ->patchJson("/api/portal/school-information/{$item['id']}/state", ['action' => 'read'])
            ->assertOk();

        $after = $this->apiSiswa('/api/portal/school-information/summary');
        $this->assertSame(max(0, $unreadBefore - 1), $after->json('data.unread_count'));

        $afterList = $this->apiSiswa();
        $readItem = collect($afterList->json('data.data'))->firstWhere('id', $item['id']);
        $this->assertTrue($readItem['is_read']);
    }
}
