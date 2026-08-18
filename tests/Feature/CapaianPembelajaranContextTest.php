<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\MasterKurikulum;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapaianPembelajaranContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_cp_cannot_combine_a_curriculum_with_another_units_subject(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $unitA = EducationUnit::create(['code' => 'CP-A', 'name' => 'Unit CP A', 'level' => 'SD', 'is_active' => true]);
        $unitB = EducationUnit::create(['code' => 'CP-B', 'name' => 'Unit CP B', 'level' => 'SMP', 'is_active' => true]);
        $curriculumA = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-CP-A',
            'nama_kurikulum' => 'Kurikulum Unit A',
            'jenis_kurikulum' => 'Merdeka',
            'unit_pendidikan_id' => $unitA->id,
            'tahun_ajaran_id' => $year->id,
            'jenjang' => 'SD',
            'tanggal_mulai' => '2026-07-01',
            'status' => true,
        ]);
        $subjectB = Subject::create([
            'unit_pendidikan_id' => $unitB->id,
            'kode_mapel' => 'MTK-B',
            'nama_mapel' => 'Matematika Unit B',
            'status' => true,
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/lms/capaian-pembelajaran', [
                'unit_pendidikan_id' => $unitA->id,
                'tahun_ajaran_id' => $year->id,
                'kurikulum_id' => $curriculumA->id,
                'mata_pelajaran_id' => $subjectB->id,
                'kode_cp' => 'CP-CROSS-UNIT',
                'nama_cp' => 'CP lintas unit tidak valid',
                'status' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mata_pelajaran_id');
    }

    public function test_tp_options_are_limited_to_the_requested_subject_context(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $unitA = EducationUnit::create(['code' => 'OPT-A', 'name' => 'Unit Option A', 'level' => 'SD', 'is_active' => true]);
        $unitB = EducationUnit::create(['code' => 'OPT-B', 'name' => 'Unit Option B', 'level' => 'SMP', 'is_active' => true]);
        $curriculumA = MasterKurikulum::create(['kode_kurikulum' => 'KM-OPT-A', 'nama_kurikulum' => 'Kurikulum A', 'jenis_kurikulum' => 'Merdeka', 'unit_pendidikan_id' => $unitA->id, 'tahun_ajaran_id' => $year->id, 'jenjang' => 'SD', 'tanggal_mulai' => '2026-07-01', 'status' => true]);
        $curriculumB = MasterKurikulum::create(['kode_kurikulum' => 'KM-OPT-B', 'nama_kurikulum' => 'Kurikulum B', 'jenis_kurikulum' => 'Merdeka', 'unit_pendidikan_id' => $unitB->id, 'tahun_ajaran_id' => $year->id, 'jenjang' => 'SMP', 'tanggal_mulai' => '2026-07-01', 'status' => true]);
        $subjectA = Subject::create(['unit_pendidikan_id' => $unitA->id, 'kurikulum_id' => $curriculumA->id, 'kode_mapel' => 'IPA-A', 'nama_mapel' => 'IPA A', 'status' => true]);
        $subjectB = Subject::create(['unit_pendidikan_id' => $unitB->id, 'kurikulum_id' => $curriculumB->id, 'kode_mapel' => 'IPA-B', 'nama_mapel' => 'IPA B', 'status' => true]);
        $cpA = CapaianPembelajaran::create(['unit_pendidikan_id' => $unitA->id, 'tahun_ajaran_id' => $year->id, 'kurikulum_id' => $curriculumA->id, 'mata_pelajaran_id' => $subjectA->id, 'kode_cp' => 'CP-OPT-A', 'nama_cp' => 'CP A', 'status' => true]);
        CapaianPembelajaran::create(['unit_pendidikan_id' => $unitB->id, 'tahun_ajaran_id' => $year->id, 'kurikulum_id' => $curriculumB->id, 'mata_pelajaran_id' => $subjectB->id, 'kode_cp' => 'CP-OPT-B', 'nama_cp' => 'CP B', 'status' => true]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/lms/tujuan-pembelajaran/options?unit_pendidikan_id={$unitA->id}&tahun_ajaran_id={$year->id}&kurikulum_id={$curriculumA->id}&mata_pelajaran_id={$subjectA->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.capaian_pembelajaran')
            ->assertJsonPath('data.capaian_pembelajaran.0.id', $cpA->id);
    }
}
