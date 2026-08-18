<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TahfizhCalculationAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_tahfizh_progress_merges_overlapping_verse_intervals_correctly()
    {
        $unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TEST-1',
            'name' => 'Unit Test 1',
            'level' => 'SMA',
        ]);

        $ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $kelas = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'jenjang' => 'SMA',
            'tingkat' => '10',
            'nama_kelas' => '10-A',
            'kode_kelas' => '10A',
        ]);

        $student = Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $unit->id,
            'kelas_id' => $kelas->id,
            'nis' => '998877',
            'full_name' => 'Siswa Hafiz',
            'gender' => 'male',
            'is_active' => true,
        ]);

        // Input setoran 1: Surah 1 (Al-Fatihah), Ayat 1-5
        TahfizhDailyLog::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'record_date' => '2026-08-01',
            'hafalan_surah_number' => 1,
            'hafalan_surah_name' => 'Al-Fatihah',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 5,
            'hafalan_baris' => 5,
            'status' => 'submitted',
        ]);

        // Input setoran 2: Surah 1 (Al-Fatihah), Ayat 4-7 (Overlapping 4-5)
        TahfizhDailyLog::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'record_date' => '2026-08-02',
            'hafalan_surah_number' => 1,
            'hafalan_surah_name' => 'Al-Fatihah',
            'hafalan_ayah_start' => 4,
            'hafalan_ayah_end' => 7,
            'hafalan_baris' => 4,
            'status' => 'submitted',
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tahfizh/student-progress/{$student->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Merged 1-5 and 4-7 should yield 7 unique verses for Al-Fatihah (not 5+4=9)
        $this->assertEquals(7, $data['total_ayats_memorized']);
        $this->assertEquals(1, $data['total_surahs_memorized']);
    }

    public function test_murajaah_does_not_inflate_new_hafalan_totals()
    {
        $student = Student::create([
            'id' => (string) Str::uuid(),
            'nis' => '998878',
            'full_name' => 'Siswa Murajaah',
            'gender' => 'male',
            'is_active' => true,
        ]);

        // Setoran baru
        TahfizhDailyLog::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'record_date' => '2026-08-01',
            'hafalan_surah_number' => 112,
            'hafalan_surah_name' => 'Al-Ikhlas',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 4,
            'hafalan_baris' => 4,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // Setoran Murajaah murni
        TahfizhDailyLog::create([
            'id' => (string) Str::uuid(),
            'student_id' => $student->id,
            'record_date' => '2026-08-02',
            'hafalan_surah_number' => 112,
            'hafalan_surah_name' => 'Al-Ikhlas',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 4,
            'hafalan_baris' => 0,
            'metadata' => ['type' => 'Murajaah'],
        ]);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tahfizh/student-progress/{$student->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Total ayats should remain 4, not 8
        $this->assertEquals(4, $data['total_ayats_memorized']);
    }

    public function test_parent_cannot_sign_note_for_unlinked_student()
    {
        $parentUser = User::factory()->create();
        $parentUser->assignRole('Orang Tua');

        $parent = ParentModel::create([
            'id' => (string) Str::uuid(),
            'user_id' => $parentUser->id,
            'full_name' => 'Orang Tua A',
        ]);

        $unlinkedStudent = Student::create([
            'id' => (string) Str::uuid(),
            'nis' => '776655',
            'full_name' => 'Siswa Bukan Anak',
            'gender' => 'female',
            'is_active' => true,
        ]);

        $teacher = Teacher::create([
            'id' => (string) Str::uuid(),
            'full_name' => 'Guru BK',
            'employee_number' => 'EMP-BK-01',
        ]);

        $note = StudentNote::create([
            'id' => (string) Str::uuid(),
            'student_id' => $unlinkedStudent->id,
            'teacher_id' => $teacher->id,
            'title' => 'Catatan Kedisiplinan',
            'content' => 'Tingkatkan kerapihan pakaian.',
            'category' => 'Kedisiplinan',
            'visible_to_parent' => true,
        ]);

        // Konvensi Portal (fail-closed, tanpa kebocoran eksistensi):
        // anak yang tidak terhubung → 404 di semua endpoint child-scoped,
        // termasuk tanda tangan catatan. Status 403 hanya untuk kasus anak
        // terhubung tetapi catatan belum dipublikasikan ke orang tua.
        $response = $this->actingAs($parentUser, 'sanctum')
            ->postJson("/api/portal/student-notes/{$note->id}/sign", [
                'child_id' => $unlinkedStudent->id,
                'notes_parent' => 'Sudah saya ingatkan.',
            ]);

        $response->assertStatus(404);
    }
}
