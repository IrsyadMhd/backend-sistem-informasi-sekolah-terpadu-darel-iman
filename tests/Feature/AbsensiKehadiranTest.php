<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\LmsPresensi;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiKehadiranTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $teacherUser;
    protected User $studentUser;
    protected Student $student;
    protected Employee $teacher;
    protected EducationUnit $unit;
    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->unit = EducationUnit::create(['code' => 'SD-S08', 'name' => 'SD IT S08', 'level' => 'SD']);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->teacher = Employee::create(['nama_lengkap' => 'Guru S08', 'jenis_kelamin' => 'L', 'unit_id' => $this->unit->id]);
        $this->teacherUser = User::factory()->create(['email' => 'guru08@school.id']);
        $this->teacherUser->assignRole('Guru');
        $this->teacher->update(['user_id' => $this->teacherUser->id]);

        $this->kelas = Kelas::create(['nama_kelas' => 'V A', 'level' => 5, 'education_unit_id' => $this->unit->id, 'wali_kelas_id' => $this->teacher->id]);

        $this->student = Student::create(['full_name' => 'Siswa S08', 'nis' => '5008', 'gender' => 'L', 'status' => 'aktif', 'education_unit_id' => $this->unit->id, 'kelas_id' => $this->kelas->id]);
        $this->studentUser = User::factory()->create(['email' => 'siswa08@school.id']);
        $this->studentUser->assignRole('Siswa');
        $this->student->update(['user_id' => $this->studentUser->id]);
    }

    public function test_dashboard_wali_kelas_dan_rekap_kehadiran_access(): void
    {
        // 1. Homeroom Dashboard
        $dashRes = $this->actingAs($this->teacherUser)->getJson('/api/v1/attendance/homeroom/dashboard');
        $dashRes->assertOk();

        // 2. Homeroom Recap
        $recapRes = $this->actingAs($this->teacherUser)->getJson("/api/v1/attendance/rekap-kehadiran?kelas_id={$this->kelas->id}");
        $recapRes->assertOk();
    }

    public function test_kehadiran_saya_dan_riwayat_saya_personal_scope_isolation(): void
    {
        // 1. Student Personal Attendance
        $selfRes = $this->actingAs($this->studentUser)->getJson('/api/v1/attendance/kehadiran-saya');
        $selfRes->assertOk();

        // 2. Student Personal History
        $histRes = $this->actingAs($this->studentUser)->getJson('/api/v1/attendance/riwayat-saya');
        $histRes->assertOk();
    }

    public function test_monitoring_guru_mengajar(): void
    {
        $monRes = $this->actingAs($this->superAdmin)->getJson('/api/v1/attendance/monitoring-guru');
        $monRes->assertOk();
    }

    public function test_absensi_pembelajaran_jadwal_dropdown_dan_store(): void
    {
        $ay = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'semester_type' => 'odd', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subj = Subject::create(['code' => 'IPA-S08', 'name' => 'IPA Terpadu', 'education_unit_id' => $this->unit->id]);

        $jadwal = JadwalPelajaran::create([
            'kelas_id' => $this->kelas->id,
            'subject_id' => $subj->id,
            'employee_id' => $this->teacher->id,
            'hari' => 'Senin',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '09:00:00',
        ]);

        // 1. Get Schedules List (Jadwal Dropdown)
        $schRes = $this->actingAs($this->teacherUser)->getJson('/api/v1/lms/presensi/my-schedules');
        $schRes->assertOk();

        // 2. Store Attendance Roster
        $storeRes = $this->actingAs($this->teacherUser)->postJson('/api/v1/lms/presensi', [
            'jadwal_pelajaran_id' => $jadwal->id,
            'tanggal' => now()->toDateString(),
            'pertemuan_ke' => 1,
            'materi_pembelajaran' => 'Pengenalan Ekosistem',
            'presensi' => [
                [
                    'student_id' => $this->student->id,
                    'status' => 'hadir',
                    'catatan' => 'Siswa sangat aktif mengikuti pelajaran.',
                ]
            ],
        ]);

        $storeRes->assertOk()
            ->assertJsonPath('success', true);
    }
}
