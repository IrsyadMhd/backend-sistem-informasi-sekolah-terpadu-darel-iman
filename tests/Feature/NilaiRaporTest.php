<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsPenilaian;
use App\Models\LmsRapor;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NilaiRaporTest extends TestCase
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

    public function test_buku_nilai_dan_rekap_nilai_crud(): void
    {
        $unit = EducationUnit::create(['code' => 'SD-S07', 'name' => 'SD IT S07', 'level' => 'SD']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subj = Subject::create(['code' => 'MTK-S07', 'name' => 'Matematika', 'education_unit_id' => $unit->id]);
        $kelas = Kelas::create(['nama_kelas' => 'VI A', 'level' => 6, 'education_unit_id' => $unit->id]);
        $student = Student::create(['full_name' => 'Siswa S07', 'nis' => '3007', 'gender' => 'L', 'status' => 'aktif', 'education_unit_id' => $unit->id]);

        // 1. Create Penilaian
        $penRes = $this->actingAs($this->user)->postJson('/api/lms/penilaian', [
            'student_id' => $student->id,
            'subject_id' => $subj->id,
            'semester_id' => $sem->id,
            'kelas_id' => $kelas->id,
            'academic_year_id' => $ay->id,
            'score_assignment' => 90,
            'score_quiz' => 88,
            'score_midterm' => 85,
            'score_final' => 95,
        ]);

        $penRes->assertCreated()
            ->assertJsonPath('data.score_assignment', 90);

        // 2. Read Buku Nilai / Rekap
        $indexRes = $this->actingAs($this->user)->getJson("/api/lms/penilaian?kelas_id={$kelas->id}&semester_id={$sem->id}");
        $indexRes->assertOk();
    }

    public function test_rapor_digital_generate_update_approve_publish_dan_pdf(): void
    {
        $unit = EducationUnit::create(['code' => 'SMP-S07', 'name' => 'SMP IT S07', 'level' => 'SMP']);
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $kelas = Kelas::create(['nama_kelas' => 'IX B', 'level' => 9, 'education_unit_id' => $unit->id]);
        $student = Student::create(['full_name' => 'Siswa SMP S07', 'nis' => '4007', 'gender' => 'P', 'status' => 'aktif', 'education_unit_id' => $unit->id]);
        $wali = Employee::create(['nama_lengkap' => 'Wali Kelas IX B', 'jenis_kelamin' => 'P', 'unit_id' => $unit->id]);

        // 1. Generate Class Rapor
        $genRes = $this->actingAs($this->user)->postJson('/api/v1/lms/rapor/generate-class', [
            'kelas_id' => $kelas->id,
            'semester_id' => $sem->id,
            'tahun_ajaran_id' => $ay->id,
        ]);

        $genRes->assertOk()
            ->assertJsonPath('success', true);

        // 2. Direct Store / Update Single Rapor
        $storeRes = $this->actingAs($this->user)->postJson('/api/v1/lms/rapor', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelas->id,
            'semester_id' => $sem->id,
            'tahun_ajaran_id' => $ay->id,
            'guru_wali_id' => $wali->id,
            'rata_rata' => 89.5,
            'catatan_wali_kelas' => "Pertahankan prestasi belajarmu.\nTingkatkan hafalan Al-Qur'an.",
        ]);

        $storeRes->assertCreated()
            ->assertJsonPath('data.rata_rata', 89.5);

        $raporId = $storeRes->json('data.id');

        // 3. Approve Rapor
        $appRes = $this->actingAs($this->user)->postJson("/api/v1/lms/rapor/{$raporId}/approve");
        $appRes->assertOk()->assertJsonPath('data.status_rapor', 'final');

        // 4. Publish Rapor
        $pubRes = $this->actingAs($this->user)->postJson("/api/v1/lms/rapor/{$raporId}/publish");
        $pubRes->assertOk()->assertJsonPath('data.status_rapor', 'published');

        // 5. Export PDF Data
        $pdfRes = $this->actingAs($this->user)->getJson("/api/v1/lms/rapor/{$raporId}/export-pdf");
        $pdfRes->assertOk()->assertJsonPath('success', true);
    }
}
