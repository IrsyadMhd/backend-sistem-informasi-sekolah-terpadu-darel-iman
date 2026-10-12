<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\QuranSurah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TahfizhAyahAchievement;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TahfizhCoreInputFlowTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unit;
    protected AcademicYear $ay;
    protected Semester $sem;
    protected Kelas $kelas;
    protected Student $student;
    protected Teacher $teacher;
    protected User $teacherUser;
    protected User $unauthorizedTeacherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Seed basic Surah data for testing
        QuranSurah::firstOrCreate(
            ['nomor' => 1],
            ['nama' => 'الفاتحة', 'nama_latin' => 'Al-Fatihah', 'jumlah_ayat' => 7, 'tempat_turun' => 'Mekah', 'arti' => 'Pembukaan', 'deskripsi' => '']
        );
        QuranSurah::firstOrCreate(
            ['nomor' => 78],
            ['nama' => 'النبأ', 'nama_latin' => 'An-Naba\'', 'jumlah_ayat' => 40, 'tempat_turun' => 'Mekah', 'arti' => 'Berita Besar', 'deskripsi' => '']
        );
        QuranSurah::firstOrCreate(
            ['nomor' => 112],
            ['nama' => 'الإخلاص', 'nama_latin' => 'Al-Ikhlas', 'jumlah_ayat' => 4, 'tempat_turun' => 'Mekah', 'arti' => 'Memurnikan Keesaan Allah', 'deskripsi' => '']
        );

        $this->unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-TAHFIZH',
            'name' => 'Unit Tahfizh',
            'level' => 'SMP',
        ]);

        $this->ay = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->sem = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $this->ay->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->ay->id,
            'semester_id' => $this->sem->id,
            'jenjang' => 'SMP',
            'tingkat' => '7',
            'nama_kelas' => '7-A Tahfizh',
            'kode_kelas' => '7A-TFZ',
        ]);

        $this->student = Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $this->unit->id,
            'unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'nis' => 'TFZ-001',
            'full_name' => 'Ahmad Santri Tahfizh',
            'gender' => 'male',
            'is_active' => true,
        ]);

        // Teacher with assignment to this kelas
        $this->teacherUser = User::factory()->create(['name' => 'Ustadz Pembimbing']);
        $this->teacherUser->assignRole('Guru');

        $emp = Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->teacherUser->id,
            'niy' => 'NIY-001',
            'nama_lengkap' => 'Ustadz Pembimbing',
            'unit_pendidikan_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $this->teacher = Teacher::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->teacherUser->id,
            'full_name' => 'Ustadz Pembimbing',
            'employee_number' => 'EMP-TFZ-01',
        ]);

        // Assign teacher as wali kelas / pembimbing
        $this->kelas->update(['wali_kelas_id' => $emp->id]);

        // Another teacher without assignment to this kelas
        $this->unauthorizedTeacherUser = User::factory()->create(['name' => 'Ustadz Lain']);
        $this->unauthorizedTeacherUser->assignRole('Guru');
        Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->unauthorizedTeacherUser->id,
            'niy' => 'NIY-002',
            'nama_lengkap' => 'Ustadz Lain',
            'status' => 'aktif',
        ]);
        Teacher::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->unauthorizedTeacherUser->id,
            'full_name' => 'Ustadz Lain',
            'employee_number' => 'EMP-TFZ-02',
        ]);
    }

    public function test_first_setoran_returns_manual_selection_mode_not_locked_to_alfatihah_or_annaba()
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/tahfizh/last-hafalan/{$this->student->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertTrue($data['is_first_hafalan']);
        $this->assertNull($data['next_surah_number']);
        $this->assertNull($data['next_ayah_start']);
        $this->assertFalse($data['is_ayah_start_locked']);
    }

    public function test_first_setoran_can_be_saved_manually_and_appears_in_history()
    {
        $payload = [
            'student_id' => $this->student->id,
            'record_date' => '2026-08-03',
            'hafalan_surah_number' => 78, // An-Naba
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
            'hafalan_baris' => 10,
            'type' => 'Ziyadah',
        ];

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tahfizh_daily_logs', [
            'student_id' => $this->student->id,
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
        ]);

        // Subsequent check returns continuation from An-Naba Ayat 11
        $continueResponse = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/tahfizh/last-hafalan/{$this->student->id}");

        $continueResponse->assertStatus(200);
        $continueData = $continueResponse->json('data');

        $this->assertFalse($continueData['is_first_hafalan']);
        $this->assertEquals(78, $continueData['last_surah_number']);
        $this->assertEquals(10, $continueData['last_ayah_end']);
        $this->assertEquals(78, $continueData['next_surah_number']);
        $this->assertEquals(11, $continueData['next_ayah_start']);
        $this->assertTrue($continueData['is_ayah_start_locked']);
    }

    public function test_multiple_setoran_on_same_day_creates_new_records_without_overwriting()
    {
        // First setoran on 2026-08-04: An-Naba 1-5
        $res1 = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-04',
                'hafalan_surah_number' => 78,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 5,
                'type' => 'Ziyadah',
            ]);
        $res1->assertStatus(200);

        // Second setoran on same day 2026-08-04: An-Naba 6-10
        $res2 = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-04',
                'hafalan_surah_number' => 78,
                'hafalan_ayah_start' => 6,
                'hafalan_ayah_end' => 10,
                'type' => 'Ziyadah',
            ]);
        $res2->assertStatus(200);

        // Both records must exist in tahfizh_daily_logs
        $logs = TahfizhDailyLog::where('student_id', $this->student->id)
            ->where('record_date', '2026-08-04')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertEquals(1, $logs->first()->hafalan_ayah_start);
        $this->assertEquals(5, $logs->first()->hafalan_ayah_end);
        $this->assertEquals(6, $logs->last()->hafalan_ayah_start);
        $this->assertEquals(10, $logs->last()->hafalan_ayah_end);
    }

    public function test_murajaah_does_not_advance_hafalan_baru_continuation_and_does_not_inflate_achievements()
    {
        // Setoran Ziyadah: An-Naba 1-10
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-05',
                'hafalan_surah_number' => 78,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 10,
                'type' => 'Ziyadah',
            ]);

        // Setoran Murajaah: Al-Fatihah 1-7
        $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-06',
                'hafalan_surah_number' => 1,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 7,
                'type' => 'Murajaah',
            ]);

        // Kelanjutan berikutnya harus tetap dari Ziyadah (An-Naba ayat 11), bukan Al-Fatihah
        $continueResponse = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/tahfizh/last-hafalan/{$this->student->id}");

        $continueResponse->assertStatus(200);
        $continueData = $continueResponse->json('data');

        $this->assertEquals(78, $continueData['next_surah_number']);
        $this->assertEquals(11, $continueData['next_ayah_start']);

        // Check unique validated achievements: only An-Naba 1-10 should be validated (10 verses), not Al-Fatihah
        $achievements = TahfizhAyahAchievement::where('student_id', $this->student->id)->get();
        $this->assertCount(10, $achievements);
        $this->assertTrue($achievements->every(fn ($a) => $a->surah_number === 78));
    }

    public function test_validation_rejects_invalid_ayah_range_and_surah_overflow()
    {
        // 1. Ayah start > Ayah end
        $res1 = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-07',
                'hafalan_surah_number' => 1,
                'hafalan_ayah_start' => 5,
                'hafalan_ayah_end' => 2,
                'type' => 'Ziyadah',
            ]);
        $res1->assertStatus(422);

        // 2. Ayah end exceeds Al-Fatihah total verses (7)
        $res2 = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-07',
                'hafalan_surah_number' => 1,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 10,
                'type' => 'Ziyadah',
            ]);
        $res2->assertStatus(422);
    }

    public function test_unauthorized_teacher_outside_scope_is_forbidden()
    {
        $response = $this->actingAs($this->unauthorizedTeacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'student_id' => $this->student->id,
                'record_date' => '2026-08-08',
                'hafalan_surah_number' => 1,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 7,
                'type' => 'Ziyadah',
            ]);

        $response->assertStatus(403);
    }

    public function test_mobile_endpoint_creates_new_record_and_handles_murajaah_consistently()
    {
        // Mobile endpoint POST /api/teacher/tahfizh
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                'student_id' => $this->student->id,
                'type' => 'Ziyadah',
                'juz' => 30,
                'surah_number' => 78,
                'ayat_start' => 1,
                'ayat_end' => 5,
                'kelancaran' => 'Sangat Lancar',
                'tajwid' => 'Baik',
                'makhraj' => 'Baik',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tahfizh_daily_logs', [
            'student_id' => $this->student->id,
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 5,
            'hafalan_baris' => 5,
        ]);

        // Second submission via mobile on same day creates another record
        $response2 = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                'student_id' => $this->student->id,
                'type' => 'Murajaah',
                'juz' => 30,
                'surah_number' => 78,
                'ayat_start' => 1,
                'ayat_end' => 5,
                'kelancaran' => 'Sangat Lancar',
                'tajwid' => 'Baik',
                'makhraj' => 'Baik',
            ]);

        $response2->assertStatus(200);

        // Murajaah record must have hafalan_baris = 0
        $this->assertDatabaseHas('tahfizh_daily_logs', [
            'student_id' => $this->student->id,
            'hafalan_surah_number' => 78,
            'hafalan_baris' => 0,
        ]);

        // Total logs must be 2, not 1
        $count = TahfizhDailyLog::where('student_id', $this->student->id)->count();
        $this->assertEquals(2, $count);
    }

    public function test_weekly_sheet_returns_multiple_logs_on_same_day_separately()
    {
        // Setoran 1 on 2026-08-03 (Monday): Ziyadah An-Naba 1-5
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-03',
            'hafalan_surah_number' => 78,
            'hafalan_surah_name' => "An-Naba'",
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 5,
            'hafalan_baris' => 5,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // Setoran 2 on 2026-08-03 (Monday): Murajaah Al-Fatihah 1-7
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-03',
            'hafalan_surah_number' => 1,
            'hafalan_surah_name' => 'Al-Fatihah',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 7,
            'hafalan_baris' => 0,
            'murajaah_lembar' => 1,
            'metadata' => ['type' => 'Murajaah'],
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson("/api/tahfizh/weekly-sheet?student_id={$this->student->id}&start_date=2026-08-03");

        $response->assertStatus(200);
        $days = $response->json('data.days');
        $monday = collect($days)->firstWhere('record_date', '2026-08-03');

        $this->assertNotNull($monday);
        $this->assertEquals(2, $monday['total_logs']);
        $this->assertCount(2, $monday['logs']);
        $this->assertEquals(78, $monday['logs'][0]['hafalan_surah_number']);
        $this->assertEquals(1, $monday['logs'][1]['hafalan_surah_number']);
    }

    public function test_editing_setoran_with_explicit_id_updates_target_record_without_duplication_or_affecting_others()
    {
        $log1 = TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-04',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 5,
            'hafalan_baris' => 5,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        $log2 = TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-04',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 6,
            'hafalan_ayah_end' => 10,
            'hafalan_baris' => 5,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // Edit log1 specifically: change ayah end to 7 and baris to 7
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/tahfizh/daily-log', [
                'id' => $log1->id,
                'student_id' => $this->student->id,
                'record_date' => '2026-08-04',
                'hafalan_surah_number' => 78,
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 7,
                'hafalan_baris' => 7,
                'type' => 'Ziyadah',
            ]);

        $response->assertStatus(200);

        // Total count remains 2 (no duplicate created)
        $this->assertEquals(2, TahfizhDailyLog::where('student_id', $this->student->id)->count());

        // Log 1 updated
        $updatedLog1 = $log1->fresh();
        $this->assertEquals(7, $updatedLog1->hafalan_ayah_end);
        $this->assertEquals(7, $updatedLog1->hafalan_baris);

        // Log 2 unaffected
        $unaffectedLog2 = $log2->fresh();
        $this->assertEquals(6, $unaffectedLog2->hafalan_ayah_start);
        $this->assertEquals(10, $unaffectedLog2->hafalan_ayah_end);
    }

    public function test_rekap_and_history_handles_historical_murojaah_and_separates_ziyadah_from_murajaah()
    {
        // 1. Ziyadah log
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-05',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
            'hafalan_baris' => 10,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // 2. Legacy 'Murojaah' spelling log
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-05',
            'hafalan_surah_number' => 1,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 7,
            'hafalan_baris' => 0,
            'murajaah_lembar' => 1,
            'metadata' => ['type' => 'Murojaah'],
        ]);

        // 3. Modern 'Murajaah' log
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-06',
            'hafalan_surah_number' => 112,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 4,
            'hafalan_baris' => 0,
            'murajaah_lembar' => 1,
            'metadata' => ['type' => 'Murajaah'],
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/tahfizh/report');

        $response->assertStatus(200);
        $summary = $response->json('summary');

        $this->assertEquals(3, $summary['total_logs']);
        $this->assertEquals(1, $summary['total_ziyadah']);
        // Both Murajaah and legacy Murojaah are counted under total_murajaah
        $this->assertEquals(2, $summary['total_murajaah']);
        // Unique ayats only count Ziyadah (An-Naba 1-10 = 10 ayats), Murajaah does not add
        $this->assertEquals(10, $summary['total_unique_ayat']);

        // Filtering by type=Murajaah should return 2 records (both Murajaah and Murojaah)
        $filterMurajaahResponse = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/tahfizh/report?type=Murajaah');

        $filterMurajaahResponse->assertStatus(200);
        $this->assertCount(2, $filterMurajaahResponse->json('data'));
    }

    public function test_rekap_merges_overlapping_ayah_ranges_in_unique_ayah_count()
    {
        // Log 1: An-Naba 1-10
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-07',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
            'hafalan_baris' => 10,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // Log 2: An-Naba 6-15 (overlapping 6-10)
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-08',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 6,
            'hafalan_ayah_end' => 15,
            'hafalan_baris' => 10,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/tahfizh/report');

        $response->assertStatus(200);
        $summary = $response->json('summary');

        // Total transaction records is 2
        $this->assertEquals(2, $summary['total_logs']);
        // Merged unique ayats: range 1-10 merged with 6-15 => 1-15 = 15 ayats (not 20)
        $this->assertEquals(15, $summary['total_unique_ayat']);
    }

    public function test_teacher_endpoint_returns_accessible_logs_and_forbids_unauthorized_scope()
    {
        TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'record_date' => '2026-08-09',
            'hafalan_surah_number' => 78,
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
            'hafalan_baris' => 10,
            'metadata' => ['type' => 'Ziyadah'],
        ]);

        // Authorized teacher sees student log
        $resAuth = $this->actingAs($this->teacherUser, 'sanctum')
            ->getJson('/api/teacher/tahfizh');

        $resAuth->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($resAuth->json('data.data')));

        // Unauthorized teacher outside assigned classroom requesting the class gets 403
        $resUnauth = $this->actingAs($this->unauthorizedTeacherUser, 'sanctum')
            ->getJson("/api/teacher/tahfizh?class_id={$this->kelas->id}");

        $resUnauth->assertStatus(403);
    }
}
