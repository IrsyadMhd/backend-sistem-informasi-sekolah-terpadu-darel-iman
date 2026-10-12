<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\QuranSurah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TahfizhDailyLog;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TahfizhEvaluationContractTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unit;
    protected AcademicYear $academicYear;
    protected Semester $semester;
    protected Kelas $kelas;
    protected Student $student;
    protected Teacher $teacher;
    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        if (DB::table('quran_surahs')->where('nomor', 1)->doesntExist()) {
            DB::table('quran_surahs')->insert([
                ['nomor' => 1, 'nama' => 'الفاتحة', 'nama_latin' => 'Al-Fatihah', 'jumlah_ayat' => 7, 'tempat_turun' => 'Mekah', 'arti' => 'Pembukaan', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
                ['nomor' => 2, 'nama' => 'البقرة', 'nama_latin' => 'Al-Baqarah', 'jumlah_ayat' => 286, 'tempat_turun' => 'Madinah', 'arti' => 'Sapi Betina', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
                ['nomor' => 78, 'nama' => 'النبإ', 'nama_latin' => "An-Naba'", 'jumlah_ayat' => 40, 'tempat_turun' => 'Mekah', 'arti' => 'Berita Besar', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        $this->unit = EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'SMP-TEST',
            'name' => 'SMP Test Unit',
            'level' => 'SMP',
        ]);

        $this->academicYear = AcademicYear::create([
            'id' => (string) Str::uuid(),
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'id' => (string) Str::uuid(),
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $this->kelas = Kelas::create([
            'id' => (string) Str::uuid(),
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'jenjang' => 'SMP',
            'nama_kelas' => '7A',
            'kode_kelas' => '7A-TEST',
            'tingkat' => 7,
            'is_active' => true,
        ]);

        $this->student = Student::create([
            'id' => (string) Str::uuid(),
            'education_unit_id' => $this->unit->id,
            'unit_id' => $this->unit->id,
            'kelas_id' => $this->kelas->id,
            'nis' => '1001',
            'full_name' => 'Santri Evaluasi Uji',
            'nama_lengkap' => 'Santri Evaluasi Uji',
            'gender' => 'male',
            'status' => 'aktif',
            'is_active' => true,
        ]);

        $this->teacherUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ustadz Pembimbing',
            'email' => 'ustadz-eval@school.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->teacherUser->assignRole('Guru');

        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->teacherUser->id,
            'unit_id' => $this->unit->id,
            'niy' => 'NIY-EVAL-01',
            'nip' => 'NIP-EVAL-01',
            'nama_lengkap' => 'Ustadz Pembimbing',
            'email' => 'ustadz-eval@school.local',
            'status' => 'Aktif',
        ]);

        $this->teacher = Teacher::create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->teacherUser->id,
            'education_unit_id' => $this->unit->id,
            'full_name' => 'Ustadz Pembimbing',
            'employee_number' => 'EMP-EVAL-01',
            'status' => 'aktif',
        ]);

        // Assign teacher to class 7A
        $this->kelas->update(['wali_kelas_id' => $employee->id]);
    }

    /**
     * 1. TeacherPortalController accepts structured evaluation (kelancaran, tajwid, makhraj)
     */
    public function test_teacher_portal_saves_evaluation_fields_correctly(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                'student_id' => $this->student->id,
                'class_id' => $this->kelas->id,
                'type' => 'Ziyadah',
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
                'kelancaran' => 'Sangat Lancar',
                'tajwid' => 'Sangat Baik',
                'makhraj' => 'Baik',
                'notes_teacher' => 'Lafal makhraj fasih',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $log = TahfizhDailyLog::where('student_id', $this->student->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('Sangat Lancar', $log->metadata['kelancaran']);
        $this->assertSame('Sangat Baik', $log->metadata['tajwid']);
        $this->assertSame('Baik', $log->metadata['makhraj']);
        $this->assertSame('Lafal makhraj fasih', $log->notes_teacher);
        $this->assertSame('Sangat Lancar', $log->kelancaran_label);
        $this->assertSame('Sangat Baik', $log->tajwid_label);
        $this->assertSame('Baik', $log->makhraj_label);
    }

    /**
     * 2. TeacherPortalController accepts null or omitted evaluation fields safely without 422
     */
    public function test_teacher_portal_handles_omitted_evaluation_fields_without_error(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                'student_id' => $this->student->id,
                'class_id' => $this->kelas->id,
                'type' => 'Ziyadah',
                'surah_number' => 78,
                'ayat_start' => 1,
                'ayat_end' => 10,
                // kelancaran, tajwid, makhraj omitted
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $log = TahfizhDailyLog::where('student_id', $this->student->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertNull($log->metadata['kelancaran'] ?? null);
        $this->assertNull($log->metadata['tajwid'] ?? null);
        $this->assertNull($log->metadata['makhraj'] ?? null);
        // Fallback for kelancaran should be Terverifikasi (since status is verified)
        $this->assertSame('Terverifikasi', $log->kelancaran_label);
    }

    /**
     * 3. TeacherPortalController still validates required core fields strictly
     */
    public function test_teacher_portal_strictly_validates_required_core_fields(): void
    {
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                // student_id, surah_number, ayat_start, ayat_end missing
                'type' => 'Ziyadah',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['student_id', 'surah_number', 'ayat_start', 'ayat_end']);
    }

    /**
     * 4. TeacherPortalController edit mode preserves existing metadata keys and values
     */
    public function test_teacher_portal_edit_mode_preserves_existing_metadata(): void
    {
        $initialLog = TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'class_id' => $this->kelas->id,
            'teacher_id' => $this->teacher->id,
            'record_date' => now()->toDateString(),
            'hafalan_surah_number' => 1,
            'hafalan_surah_name' => 'Al-Fatihah',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 7,
            'status' => 'verified',
            'notes_teacher' => 'Catatan awal guru',
            'metadata' => [
                'type' => 'Ziyadah',
                'juz' => 1,
                'kelancaran' => 'Lancar',
                'tajwid' => 'A',
                'makhraj' => 'A',
                'custom_historical_key' => 'keep_this_intact',
            ],
        ]);

        // Edit request only updates ayat_end and does not resend kelancaran, tajwid, makhraj
        $response = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/teacher/tahfizh', [
                'id' => $initialLog->id,
                'student_id' => $this->student->id,
                'class_id' => $this->kelas->id,
                'type' => 'Ziyadah',
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
            ]);

        $response->assertOk();

        $initialLog->refresh();
        $this->assertSame('keep_this_intact', $initialLog->metadata['custom_historical_key']);
        $this->assertSame('Lancar', $initialLog->metadata['kelancaran']);
        $this->assertSame('A', $initialLog->metadata['tajwid']);
        $this->assertSame('A', $initialLog->metadata['makhraj']);
        $this->assertSame('Catatan awal guru', $initialLog->notes_teacher);
    }

    /**
     * 5. TahfizhDailyLog accessors read letters (A, B, C, D) and descriptive values safely
     */
    public function test_tahfizh_daily_log_accessors_support_both_letters_and_text(): void
    {
        // Case A: Letter grades
        $logLetter = new TahfizhDailyLog([
            'status' => 'verified',
            'metadata' => [
                'kelancaran' => 'Lancar',
                'tajwid' => 'A',
                'makhraj' => 'B',
            ],
        ]);
        $this->assertSame('Lancar', $logLetter->kelancaran_label);
        $this->assertSame('A', $logLetter->tajwid_label);
        $this->assertSame('B', $logLetter->makhraj_label);

        // Case B: Descriptive grades
        $logText = new TahfizhDailyLog([
            'status' => 'verified',
            'metadata' => [
                'kelancaran' => 'Sangat Lancar',
                'tajwid' => 'Sangat Baik',
                'makhraj' => 'Perlu Bimbingan',
            ],
        ]);
        $this->assertSame('Sangat Lancar', $logText->kelancaran_label);
        $this->assertSame('Sangat Baik', $logText->tajwid_label);
        $this->assertSame('Perlu Bimbingan', $logText->makhraj_label);
    }

    /**
     * 6. TahfizhDailyLog accessors extract evaluation tags from legacy notes_teacher format
     */
    public function test_tahfizh_daily_log_accessors_extract_from_legacy_notes_teacher(): void
    {
        $logLegacy = new TahfizhDailyLog([
            'status' => 'verified',
            'notes_teacher' => '[Ziyadah - Juz 1 | Tajwid: A, Makhraj: B, Kelancaran: Lancar] Santri membaca tartil',
            'metadata' => [],
        ]);

        $this->assertSame('Lancar', $logLegacy->kelancaran_label);
        $this->assertSame('A', $logLegacy->tajwid_label);
        $this->assertSame('B', $logLegacy->makhraj_label);
    }

    /**
     * 7. TahfizhDailyLog accessors handle null and unknown values gracefully without throwing
     */
    public function test_tahfizh_daily_log_accessors_handle_null_and_unknown_gracefully(): void
    {
        $logEmpty = new TahfizhDailyLog([
            'status' => null,
            'notes_teacher' => null,
            'metadata' => null,
        ]);

        $this->assertNull($logEmpty->kelancaran_label);
        $this->assertNull($logEmpty->tajwid_label);
        $this->assertNull($logEmpty->makhraj_label);
    }

    /**
     * 8. TahfizhDailyLog evaluation variations (Perlu Bimbingan, Bimbingan, Kurang, D) are preserved
     */
    public function test_tahfizh_evaluation_variations_preserved_without_loss(): void
    {
        $logRepeatVariations = new TahfizhDailyLog([
            'status' => 'verified',
            'metadata' => [
                'kelancaran' => 'Bimbingan',
                'tajwid' => 'D',
                'makhraj' => 'Kurang',
            ],
        ]);

        $this->assertSame('Bimbingan', $logRepeatVariations->kelancaran_label);
        $this->assertSame('D', $logRepeatVariations->tajwid_label);
        $this->assertSame('Kurang', $logRepeatVariations->makhraj_label);

        $logNormal = new TahfizhDailyLog([
            'status' => 'verified',
            'metadata' => [
                'kelancaran' => 'Lancar',
                'tajwid' => 'Baik',
                'makhraj' => 'Sangat Baik',
            ],
        ]);

        $this->assertSame('Lancar', $logNormal->kelancaran_label);
        $this->assertSame('Baik', $logNormal->tajwid_label);
        $this->assertSame('Sangat Baik', $logNormal->makhraj_label);
    }

    /**
     * 9. StudentParentPortal preserves honest quality evaluation without overriding with 'Mutqin' on approved status
     */
    public function test_student_parent_portal_preserves_honest_quality_evaluation_without_forcing_mutqin(): void
    {
        $studentUser = User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Santri Evaluasi Uji',
            'email' => 'santri-eval@school.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $studentUser->assignRole('Siswa');

        // Link student to user
        $this->student->update(['user_id' => $studentUser->id]);

        // Create an approved setoran with 'Perlu Bimbingan'
        $logRepeat = TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'class_id' => $this->kelas->id,
            'teacher_id' => $this->teacher->id,
            'record_date' => now()->toDateString(),
            'hafalan_surah_number' => 78,
            'hafalan_surah_name' => "An-Naba'",
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 10,
            'status' => 'approved',
            'notes_teacher' => 'Perlu bimbingan pada kelancaran',
            'signature_teacher' => 'Ustadz Pembimbing',
            'metadata' => [
                'type' => 'Ziyadah',
                'kelancaran' => 'Perlu Bimbingan',
                'tajwid' => 'Cukup',
                'makhraj' => 'Perlu Bimbingan',
            ],
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/portal/tahfizh");

        $response->assertOk();
        $responseData = $response->json('data.data.0') ?? $response->json('data.0') ?? [];

        // Assert quality predicate is honestly 'Perlu Bimbingan' and NOT forced to 'Mutqin'
        $this->assertNotSame('Mutqin', $responseData['nilai'] ?? null);
        $this->assertNotSame('Mutqin', $responseData['predikat'] ?? null);
        $this->assertSame('Perlu Bimbingan', $responseData['nilai'] ?? null);
        $this->assertSame('Perlu Bimbingan', $responseData['predikat'] ?? null);
        $this->assertSame('Perlu Bimbingan', $responseData['kelancaran'] ?? null);
        $this->assertSame('Cukup', $responseData['tajwid'] ?? null);
        $this->assertSame('Perlu Bimbingan', $responseData['makhraj'] ?? null);
        // Approval status is separated
        $this->assertSame('approved', $responseData['status'] ?? null);
    }

    /**
     * 10. StudentParentPortal displays available status ('Terverifikasi') without inventing 'Mutqin' when evaluation is absent
     */
    public function test_student_parent_portal_displays_available_status_when_evaluation_is_absent(): void
    {
        $studentUser = User::where('email', 'santri-eval@school.local')->first();
        if (!$studentUser) {
            $studentUser = User::create([
                'id' => (string) Str::uuid(),
                'name' => 'Santri Evaluasi Uji',
                'email' => 'santri-eval@school.local',
                'password' => Hash::make('Password123!'),
                'is_active' => true,
            ]);
            $studentUser->assignRole('Siswa');
            $this->student->update(['user_id' => $studentUser->id]);
        }

        // Create an approved setoran WITHOUT quality evaluation in metadata
        $logNoEval = TahfizhDailyLog::create([
            'student_id' => $this->student->id,
            'class_id' => $this->kelas->id,
            'teacher_id' => $this->teacher->id,
            'record_date' => now()->toDateString(),
            'hafalan_surah_number' => 1,
            'hafalan_surah_name' => 'Al-Fatihah',
            'hafalan_ayah_start' => 1,
            'hafalan_ayah_end' => 7,
            'status' => 'approved',
            'metadata' => [
                'type' => 'Ziyadah',
            ],
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/portal/tahfizh");

        $response->assertOk();
        $logs = collect($response->json('data.data') ?? $response->json('data') ?? []);
        $targetLog = $logs->firstWhere('id', $logNoEval->id);

        $this->assertNotNull($targetLog);
        $this->assertNotSame('Mutqin', $targetLog['nilai'] ?? null);
        $this->assertNotSame('Mutqin', $targetLog['predikat'] ?? null);
        $this->assertSame('Terverifikasi', $targetLog['nilai'] ?? null);
        $this->assertSame('approved', $targetLog['status'] ?? null);
    }

    /**
     * 11. Database contract: tahfizh_daily_logs table does not assume physical columns 'nilai' or 'predikat'
     */
    public function test_database_contract_has_no_physical_nilai_or_predikat_columns(): void
    {
        $hasNilaiColumn = DB::getSchemaBuilder()->hasColumn('tahfizh_daily_logs', 'nilai');
        $hasPredikatColumn = DB::getSchemaBuilder()->hasColumn('tahfizh_daily_logs', 'predikat');

        $this->assertFalse($hasNilaiColumn, 'Tabel tahfizh_daily_logs tidak boleh memiliki kolom fisik nilai');
        $this->assertFalse($hasPredikatColumn, 'Tabel tahfizh_daily_logs tidak boleh memiliki kolom fisik predikat');

        // Verify JSON metadata column exists for holding flexible evaluation fields
        $hasMetadataColumn = DB::getSchemaBuilder()->hasColumn('tahfizh_daily_logs', 'metadata');
        $this->assertTrue($hasMetadataColumn, 'Tabel tahfizh_daily_logs harus memiliki kolom JSON metadata');
    }
}
