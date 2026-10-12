<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Models\TahfizhDailyLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MusyrifTahfizhMetadataIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Seed basic quran surahs if table empty
        if (DB::table('quran_surahs')->where('nomor', 1)->doesntExist()) {
            DB::table('quran_surahs')->insert([
                ['nomor' => 1, 'nama' => 'الفاتحة', 'nama_latin' => 'Al-Fatihah', 'jumlah_ayat' => 7, 'tempat_turun' => 'Mekah', 'arti' => 'Pembukaan', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
                ['nomor' => 2, 'nama' => 'البقرة', 'nama_latin' => 'Al-Baqarah', 'jumlah_ayat' => 286, 'tempat_turun' => 'Madinah', 'arti' => 'Sapi Betina', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
                ['nomor' => 78, 'nama' => 'النبإ', 'nama_latin' => "An-Naba'", 'jumlah_ayat' => 40, 'tempat_turun' => 'Mekah', 'arti' => 'Berita Besar', 'deskripsi' => '', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function test_musyrif_store_ziyadah_persists_canonical_metadata_type_and_evaluations(): void
    {
        [$unitA] = $this->createUnits();
        $musyrif = $this->createMusyrif($unitA, 'musyrif-1');
        $student = $this->createStudent($unitA, '1');

        $response = $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'ziyadah',
                'juz' => 1,
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
                'kelancaran' => 'Lancar',
                'tajwid' => 'A',
                'makhraj' => 'A',
                'notes_teacher' => 'Bacaan tartil dan makhraj fasih',
                'notes_student' => 'Siap melanjutkan ke Al-Baqarah',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);

        $logId = $response->json('data.id');
        $this->assertNotNull($logId);

        $log = TahfizhDailyLog::findOrFail($logId);
        $this->assertSame('Ziyadah', $log->metadata['type']);
        $this->assertSame(1, $log->metadata['juz']);
        $this->assertSame('Lancar', $log->metadata['kelancaran']);
        $this->assertSame('A', $log->metadata['tajwid']);
        $this->assertSame('A', $log->metadata['makhraj']);
        $this->assertSame('Siap melanjutkan ke Al-Baqarah', $log->metadata['notes_student']);

        // Check notes_teacher preserved with evaluation summary and user text
        $this->assertStringContainsString('[Ziyadah - Juz 1 | Tajwid: A, Makhraj: A, Kelancaran: Lancar]', $log->notes_teacher);
        $this->assertStringContainsString('Bacaan tartil dan makhraj fasih', $log->notes_teacher);
        $this->assertSame(7, $log->hafalan_baris);
    }

    public function test_musyrif_store_murajaah_persists_canonical_metadata_and_does_not_add_unique_verses(): void
    {
        [$unitA] = $this->createUnits();
        $musyrif = $this->createMusyrif($unitA, 'musyrif-2');
        $student = $this->createStudent($unitA, '2');

        // Initial Ziyadah: Surah 1 ayat 1-7
        $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'Ziyadah',
                'juz' => 1,
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
                'kelancaran' => 'Lancar',
                'tajwid' => 'A',
                'makhraj' => 'A',
            ])->assertCreated();

        // Submit Murajaah with variant 'Murojaah'
        $responseMurajaah = $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'Murojaah',
                'juz' => 1,
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
                'kelancaran' => 'Lancar',
                'tajwid' => 'B',
                'makhraj' => 'B',
                'notes_teacher' => 'Pengulangan hafalan juz 1',
            ]);

        $responseMurajaah->assertCreated();
        $murajaahLog = TahfizhDailyLog::findOrFail($responseMurajaah->json('data.id'));

        $this->assertSame('Murajaah', $murajaahLog->metadata['type']);
        $this->assertSame(0, $murajaahLog->hafalan_baris);
        $this->assertGreaterThan(0, $murajaahLog->murajaah_lembar);

        // Check report aggregation via admin
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.local',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Admin');

        $reportResponse = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/tahfizh/report?student_id=' . $student->id);

        $reportResponse->assertOk();
        $reportSummary = $reportResponse->json('summary');

        $this->assertSame(1, $reportSummary['total_ziyadah']);
        $this->assertSame(1, $reportSummary['total_murajaah']);
        // Unique verses must still be exactly 7 (from Ziyadah), Murajaah does not double-count
        $this->assertSame(7, $reportSummary['total_unique_ayat']);
    }

    public function test_musyrif_store_without_optional_fields_succeeds_without_error(): void
    {
        [$unitA] = $this->createUnits();
        $musyrif = $this->createMusyrif($unitA, 'musyrif-3');
        $student = $this->createStudent($unitA, '3');

        // Only required fields supplied (no juz, kelancaran, tajwid, makhraj, notes)
        $response = $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'ziyadah',
                'surah_number' => 78,
                'ayat_start' => 1,
                'ayat_end' => 10,
            ]);

        $response->assertCreated();
        $log = TahfizhDailyLog::findOrFail($response->json('data.id'));

        $this->assertSame('Ziyadah', $log->metadata['type']);
        $this->assertArrayNotHasKey('juz', $log->metadata);
        $this->assertArrayNotHasKey('kelancaran', $log->metadata);
        $this->assertArrayNotHasKey('tajwid', $log->metadata);
        $this->assertArrayNotHasKey('makhraj', $log->metadata);
        $this->assertStringContainsString('[Ziyadah]', $log->notes_teacher);
    }

    public function test_musyrif_murajaah_does_not_shift_last_log_ziyadah_continuation(): void
    {
        [$unitA] = $this->createUnits();
        $musyrif = $this->createMusyrif($unitA, 'musyrif-4');
        $student = $this->createStudent($unitA, '4');

        // Step 1: Input Ziyadah: An-Naba (78) 1-10 on day 1
        $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'Ziyadah',
                'surah_number' => 78,
                'ayat_start' => 1,
                'ayat_end' => 10,
                'record_date' => now()->subDay()->toDateString(),
            ])->assertCreated();

        // Step 2: Input Murajaah: Al-Baqarah (2) 1-20 on today
        $this->actingAs($musyrif, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $student->id,
                'type' => 'Murajaah',
                'surah_number' => 2,
                'ayat_start' => 1,
                'ayat_end' => 20,
                'record_date' => now()->toDateString(),
            ])->assertCreated();

        // Step 3: Musyrif fetches last-log for next continuation
        $lastLogRes = $this->actingAs($musyrif, 'sanctum')
            ->getJson('/api/musyrif/tahfizh/last-log?student_id=' . $student->id);

        $lastLogRes->assertOk();
        // It must return An-Naba (78) 1-10, NOT the Murajaah log
        $this->assertSame(78, (int) $lastLogRes->json('data.hafalan_surah_number'));
        $this->assertSame(10, (int) $lastLogRes->json('data.hafalan_ayah_end'));

        // Step 4: TahfizhController getLastHafalan also continues from An-Naba ayah 11
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-test@school.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Admin');

        $nextRes = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/tahfizh/last-hafalan/' . $student->id);

        $nextRes->assertOk();
        $this->assertSame(78, (int) $nextRes->json('data.next_surah_number'));
        $this->assertSame(11, (int) $nextRes->json('data.next_ayah_start'));
    }

    public function test_musyrif_cannot_input_tahfizh_for_student_outside_assigned_unit(): void
    {
        [$unitA, $unitB] = $this->createUnits();
        $musyrifA = $this->createMusyrif($unitA, 'musyrif-a');
        $studentB = $this->createStudent($unitB, 'b');

        // Negative check: Musyrif Unit A cannot submit setoran for Student Unit B
        $this->actingAs($musyrifA, 'sanctum')
            ->postJson('/api/musyrif/tahfizh', [
                'student_id' => $studentB->id,
                'type' => 'Ziyadah',
                'surah_number' => 1,
                'ayat_start' => 1,
                'ayat_end' => 7,
            ])
            ->assertForbidden();
    }

    /** @return array{EducationUnit, EducationUnit} */
    private function createUnits(): array
    {
        return [
            EducationUnit::create(['code' => 'UNIT-PA', 'name' => 'Ponpes Putra', 'level' => 'SMP', 'is_active' => true]),
            EducationUnit::create(['code' => 'UNIT-PI', 'name' => 'Ponpes Putri', 'level' => 'SMP', 'is_active' => true]),
        ];
    }

    private function createMusyrif(EducationUnit $unit, string $slug): User
    {
        $user = User::create([
            'name' => 'Musyrif ' . $unit->name,
            'email' => $slug . '@school-erp.local',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $user->assignRole('Musyrif');

        Employee::create([
            'niy' => 'NIY-' . $slug,
            'nama_lengkap' => 'Musyrif ' . $unit->name,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => 'Aktif',
        ]);

        return $user;
    }

    private function createStudent(EducationUnit $unit, string $suffix): Student
    {
        return Student::create([
            'full_name' => 'Santri ' . $suffix,
            'nisn' => '000000000' . $suffix,
            'nis' => 'SANTRI-' . $suffix,
            'gender' => 'male',
            'unit_id' => $unit->id,
            'is_active' => true,
            'status' => 'aktif',
        ]);
    }
}
