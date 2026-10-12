<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\LmsPresensi;
use App\Models\Student;
use App\Models\User;
use App\Services\GateAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Tahap8FoundationConfigurationAndConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Fase A: Gate attendance schedule config save & retrieve for global and unit levels.
     */
    public function test_fase_a_gate_schedule_config_global_and_unit(): void
    {
        $service = app(GateAttendanceService::class);

        // 1. Default fallback when nothing saved
        $defaultConfig = $service->getScheduleConfig(null);
        $this->assertEquals('07:15', $defaultConfig['jam_masuk']);
        $this->assertEquals(10, $defaultConfig['toleransi_menit']);
        $this->assertEquals('14:15', $defaultConfig['jam_pulang']);
        $this->assertEquals('12:00', $defaultConfig['jam_cutoff_alpha']);

        // 2. Save global schedule config (stored in school_settings)
        $savedGlobal = $service->saveScheduleConfig(null, [
            'jam_masuk' => '07:30',
            'toleransi_menit' => 15,
            'jam_pulang' => '15:00',
            'jam_cutoff_alpha' => '11:45',
        ]);
        $this->assertTrue($savedGlobal['success']);

        $retrievedGlobal = $service->getScheduleConfig(null);
        $this->assertEquals('07:30', $retrievedGlobal['jam_masuk']);
        $this->assertEquals(15, $retrievedGlobal['toleransi_menit']);
        $this->assertEquals('15:00', $retrievedGlobal['jam_pulang']);

        // Verify it was saved in school_settings table with setting_key 'gate_schedule_default'
        $row = DB::table('school_settings')->where('setting_key', 'gate_schedule_default')->first();
        $this->assertNotNull($row);
        $val = is_string($row->setting_value) ? json_decode($row->setting_value, true) : (array) $row->setting_value;
        $this->assertEquals('07:30', $val['jam_masuk']);

        // 3. Save unit-level schedule config (stored in EducationUnit metadata)
        $unit = EducationUnit::factory()->create([
            'name' => 'SD IT Dar el-Iman',
            'code' => 'SDIT',
        ]);

        $savedUnit = $service->saveScheduleConfig($unit->id, [
            'jam_masuk' => '07:00',
            'toleransi_menit' => 5,
            'jam_pulang' => '13:30',
            'jam_cutoff_alpha' => '11:00',
        ]);
        $this->assertTrue($savedUnit['success']);

        $retrievedUnit = $service->getScheduleConfig($unit->id);
        $this->assertEquals('07:00', $retrievedUnit['jam_masuk']);
        $this->assertEquals(5, $retrievedUnit['toleransi_menit']);
        $this->assertEquals('13:30', $retrievedUnit['jam_pulang']);

        // Global config should remain untouched
        $checkGlobalAgain = $service->getScheduleConfig(null);
        $this->assertEquals('07:30', $checkGlobalAgain['jam_masuk']);
    }

    /**
     * Test Fase B: Attendance status label and badge color mapping while preserving internal codes.
     */
    public function test_fase_b_attendance_status_label_and_badge_mapping(): void
    {
        // 1. Gate attendance: status is ALPHA in DB
        $attendance = new Attendance([
            'status' => Attendance::STATUS_ALPHA,
        ]);
        $this->assertEquals('ALPHA', $attendance->status);
        $this->assertEquals('Tanpa Keterangan (Alpha)', $attendance->status_label);
        $this->assertEquals('danger', $attendance->status_badge_color);

        // Alias ALPA also maps gracefully without altering the internal attribute
        $attendanceAlpa = new Attendance([
            'status' => 'ALPA',
        ]);
        $this->assertEquals('ALPA', $attendanceAlpa->status);
        $this->assertEquals('Tanpa Keterangan (Alpha)', $attendanceAlpa->status_label);
        $this->assertEquals('danger', $attendanceAlpa->status_badge_color);

        // Array / JSON serialization includes appends
        $arr = $attendance->toArray();
        $this->assertArrayHasKey('status_label', $arr);
        $this->assertArrayHasKey('status_badge_color', $arr);
        $this->assertEquals('Tanpa Keterangan (Alpha)', $arr['status_label']);

        // 2. LMS Presensi: status_hadir is alpa in DB
        $lmsPresensi = new LmsPresensi([
            'status_hadir' => 'alpa',
        ]);
        $this->assertEquals('alpa', $lmsPresensi->status_hadir);
        $this->assertEquals('Alpa / Tanpa Keterangan', $lmsPresensi->status_label);
        $this->assertEquals('rose', $lmsPresensi->status_badge_color);

        // Alias alpha also maps to same label and badge
        $lmsPresensiAlpha = new LmsPresensi([
            'status_hadir' => 'alpha',
        ]);
        $this->assertEquals('alpha', $lmsPresensiAlpha->status_hadir);
        $this->assertEquals('Alpa / Tanpa Keterangan', $lmsPresensiAlpha->status_label);
        $this->assertEquals('rose', $lmsPresensiAlpha->status_badge_color);
    }

    /**
     * Test Fase C: Employee status (keaktifan) and status_pegawai (ikatan kerja) independence.
     */
    public function test_fase_c_employee_status_and_status_pegawai_isolation(): void
    {
        // 1. Create employee with Yayasan permanent employment
        $empYayasan = Employee::create([
            'niy' => 'NIY-2026-001',
            'nama_lengkap' => 'Ustadz Ahmad Fauzi',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
            'status_pegawai' => 'Pegawai Tetap Yayasan',
        ]);
        $this->assertEquals('Aktif', $empYayasan->status);
        $this->assertEquals('Pegawai Tetap Yayasan', $empYayasan->status_pegawai);

        // 2. Create employee on contract with Cuti status
        $empKontrak = Employee::create([
            'niy' => 'NIY-2026-002',
            'nama_lengkap' => 'Ustadzah Maryam',
            'jenis_kelamin' => 'P',
            'status' => 'Cuti',
            'status_pegawai' => 'Pegawai Kontrak',
        ]);
        $this->assertEquals('Cuti', $empKontrak->status);
        $this->assertEquals('Pegawai Kontrak', $empKontrak->status_pegawai);

        // Verify neither attribute leaked into the other
        $this->assertNotEquals($empKontrak->status, $empKontrak->status_pegawai);
        $this->assertNotEquals($empYayasan->status, $empYayasan->status_pegawai);
    }

    /**
     * Test Fase D: Alumni filtering with multi-keyword normalization.
     */
    public function test_fase_d_alumni_multi_keyword_filtering(): void
    {
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        // 1. Alumni continuing to Pesantren (recorded with 'Pondok Pesantren Tahfizh')
        Student::create([
            'nis' => 'ALM-001',
            'full_name' => 'Santri Zaid',
            'gender' => 'male',
            'is_active' => false,
            'metadata' => [
                'status_alumni' => 'Lulus',
                'tujuan_kelulusan' => 'Pondok Pesantren Tahfizh Al-Quran',
            ],
        ]);

        // 2. Alumni continuing to Ponpes (recorded with 'Ponpes Modern')
        Student::create([
            'nis' => 'ALM-002',
            'full_name' => 'Santri Umar',
            'gender' => 'male',
            'is_active' => false,
            'metadata' => [
                'status_alumni' => 'Tamat',
                'status_lanjutan' => 'Ponpes Modern Gontor',
            ],
        ]);

        // 3. Alumni working (recorded with 'Bekerja di BUMN')
        Student::create([
            'nis' => 'ALM-003',
            'full_name' => 'Alumni Khalid',
            'gender' => 'male',
            'is_active' => false,
            'metadata' => [
                'status_alumni' => 'Lulus',
                'pekerjaan' => 'Bekerja di Perusahaan Swasta',
            ],
        ]);

        // 4. Alumni university (recorded with 'Universitas Andalas')
        Student::create([
            'nis' => 'ALM-004',
            'full_name' => 'Alumni Aisyah',
            'gender' => 'female',
            'is_active' => false,
            'metadata' => [
                'status_alumni' => 'Lulus',
                'perguruan_tinggi' => 'Universitas Andalas (UNAND)',
            ],
        ]);

        // Filter by 'pesantren': should match both 'Pondok Pesantren' and 'Ponpes'
        $respPesantren = $this->actingAs($user)->getJson('/api/alumni?tujuan_kelulusan=pesantren');
        $respPesantren->assertStatus(200);
        $dataPesantren = $respPesantren->json('data');
        $this->assertCount(2, $dataPesantren);

        // Filter by 'ponpes': should also match both
        $respPonpes = $this->actingAs($user)->getJson('/api/alumni?tujuan_kelulusan=ponpes');
        $respPonpes->assertStatus(200);
        $dataPonpes = $respPonpes->json('data');
        $this->assertCount(2, $dataPonpes);

        // Filter by 'kerja': should match 'Bekerja di Perusahaan Swasta'
        $respKerja = $this->actingAs($user)->getJson('/api/alumni?tujuan_kelulusan=kerja');
        $respKerja->assertStatus(200);
        $dataKerja = $respKerja->json('data');
        $this->assertCount(1, $dataKerja);
        $this->assertEquals('Alumni Khalid', $dataKerja[0]['full_name']);

        // Filter by 'kuliah': should match 'Universitas Andalas'
        $respKuliah = $this->actingAs($user)->getJson('/api/alumni?tujuan_kelulusan=kuliah');
        $respKuliah->assertStatus(200);
        $dataKuliah = $respKuliah->json('data');
        $this->assertCount(1, $dataKuliah);
        $this->assertEquals('Alumni Aisyah', $dataKuliah[0]['full_name']);
    }
}
