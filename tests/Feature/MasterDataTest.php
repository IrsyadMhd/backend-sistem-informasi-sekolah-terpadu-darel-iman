<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
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

    public function test_jabatan_normalisasi_input_dan_export_import(): void
    {
        // 1. Create with un-trimmed spaces
        $res = $this->actingAs($this->user)->postJson('/api/jabatan', [
            'kode_jabatan' => '  jbt-norm-01  ',
            'nama_jabatan' => '  Staf  Administrasi   Utama  ',
            'satuan_kerja' => 'Unit Pendidikan',
            'scope_akses' => 'unit_sendiri',
            'level_jabatan' => 5,
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.kode_jabatan', 'JBT-NORM-01')
            ->assertJsonPath('data.nama_jabatan', 'Staf Administrasi Utama');

        // 2. Export Jabatan
        $exportRes = $this->actingAs($this->user)->getJson('/api/jabatan/export');
        $exportRes->assertOk()->assertJsonPath('status', 'success');

        // 3. Import Jabatan
        $importRes = $this->actingAs($this->user)->postJson('/api/jabatan/import', [
            'data' => [
                [
                    'kode_jabatan' => 'JBT-IMP-02',
                    'nama_jabatan' => 'Guru Pendamping',
                    'satuan_kerja' => 'Unit Pendidikan',
                    'scope_akses' => 'unit_sendiri',
                    'level_jabatan' => 8,
                ],
            ],
        ]);
        $importRes->assertOk()->assertJsonPath('status', 'success');
    }

    public function test_employee_normalisasi_input_dan_export_import(): void
    {
        // 1. Create Employee with spaces
        $res = $this->actingAs($this->user)->postJson('/api/employees', [
            'niy' => '  PEG-999-01  ',
            'nama_lengkap' => '  Ustadz   Ahmad   Mansur  ',
            'jenis_kelamin' => 'L',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.niy', 'PEG-999-01')
            ->assertJsonPath('data.nama_lengkap', 'Ustadz Ahmad Mansur');

        // 2. Export Employee
        $exportRes = $this->actingAs($this->user)->getJson('/api/employees/export');
        $exportRes->assertOk()->assertJsonPath('status', 'success');

        // 3. Import Employee Duplicate Check
        $importRes = $this->actingAs($this->user)->postJson('/api/employees/import', [
            'data' => [
                [
                    'niy' => 'PEG-999-01',
                    'nama_lengkap' => 'Ustadz Ahmad Mansur Duplicate',
                    'jenis_kelamin' => 'L',
                ],
            ],
        ]);
        $importRes->assertOk()
            ->assertJsonPath('data.duplikat', 1);
    }

    public function test_student_normalisasi_input_dan_export_import(): void
    {
        // 1. Create Student with spaces
        $res = $this->actingAs($this->user)->postJson('/api/students', [
            'nis' => '  2026999  ',
            'full_name' => '  Siti   Aisyah  ',
            'gender' => 'female',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.nis', '2026999')
            ->assertJsonPath('data.full_name', 'Siti Aisyah');

        // 2. Export Student
        $exportRes = $this->actingAs($this->user)->getJson('/api/students/export');
        $exportRes->assertOk()->assertJsonPath('status', 'success');

        // 3. Import Student Template
        $tplRes = $this->actingAs($this->user)->getJson('/api/students/import-template');
        $tplRes->assertOk();
    }

    public function test_alumni_export_import(): void
    {
        // 1. Create Student as Alumni
        $std = Student::create([
            'nis' => '20220001',
            'full_name' => 'Alumni Fulan',
            'gender' => 'male',
            'is_active' => false,
            'metadata' => [
                'is_alumni' => true,
                'status_alumni' => 'alumni',
                'tahun_lulus' => '2025',
                'tujuan_kelulusan' => 'ITB',
            ],
        ]);

        // 2. Export Alumni
        $exportRes = $this->actingAs($this->user)->getJson('/api/alumni/export');
        $exportRes->assertOk()->assertJsonPath('status', 'success');

        // 3. Import Alumni
        $importRes = $this->actingAs($this->user)->postJson('/api/alumni/import', [
            'data' => [
                [
                    'nis' => '20220001',
                    'full_name' => 'Alumni Fulan Updated',
                    'tahun_lulus' => '2025',
                    'tujuan_kelulusan' => 'UGM',
                ],
            ],
        ]);
        $importRes->assertOk()->assertJsonPath('data.berhasil', 1);
    }
}
