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

    public function test_kelas_import_payload_kosong_file_dan_overwrite(): void
    {
        $unit = \App\Models\EducationUnit::factory()->create([
            'name' => 'SDIT 1 Dar el-Iman',
            'code' => 'SDIT1',
        ]);

        $academicYear = \App\Models\AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $semester = \App\Models\Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        // 1. Validasi jika payload data impor kosong
        $emptyRes = $this->actingAs($this->user)->postJson('/api/kelas/import', [
            'data' => [],
        ]);
        $emptyRes->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Payload data impor tidak boleh kosong atau tidak ada baris data yang valid.');

        // 2. Import data baru via array payload
        $import1 = $this->actingAs($this->user)->postJson('/api/kelas/import', [
            'data' => [
                [
                    'kode_kelas' => 'KLS-TEST-01',
                    'nama_kelas' => 'Kelas 1A Utsman',
                    'jenjang' => 'SDIT',
                    'tingkat' => '1',
                    'unit_pendidikan_id' => $unit->id,
                    'kapasitas' => 28,
                    'ruangan' => 'Ruang 101',
                    'status' => 'Aktif',
                ],
            ],
            'overwrite' => false,
        ]);
        $import1->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.berhasil', 1)
            ->assertJsonPath('data.duplikat', 0);

        $this->assertDatabaseHas('tbl_kelas', [
            'kode_kelas' => 'KLS-TEST-01',
            'nama_kelas' => 'Kelas 1A Utsman',
            'kapasitas' => 28,
        ]);

        // 3. Import data duplikat TANPA overwrite (harus dilewati dan dilaporkan duplikat)
        $importDuplikat = $this->actingAs($this->user)->postJson('/api/kelas/import', [
            'data' => [
                [
                    'kode_kelas' => 'KLS-TEST-01',
                    'nama_kelas' => 'Kelas 1A Diubah Tanpa Overwrite',
                    'unit_pendidikan_id' => $unit->id,
                    'kapasitas' => 35,
                ],
            ],
            'overwrite' => false,
        ]);
        $importDuplikat->assertOk()
            ->assertJsonPath('data.berhasil', 0)
            ->assertJsonPath('data.duplikat', 1)
            ->assertJsonPath('data.diperbarui', 0);

        // Pastikan nama kelas dan kapasitas di database TIDAK berubah
        $this->assertDatabaseHas('tbl_kelas', [
            'kode_kelas' => 'KLS-TEST-01',
            'nama_kelas' => 'Kelas 1A Utsman',
            'kapasitas' => 28,
        ]);

        // 4. Import data duplikat DENGAN overwrite: true (harus ditimpa/diperbarui)
        $importOverwrite = $this->actingAs($this->user)->postJson('/api/kelas/import', [
            'data' => [
                [
                    'kode_kelas' => 'KLS-TEST-01',
                    'nama_kelas' => 'Kelas 1A Berhasil Ditimpa',
                    'unit_pendidikan_id' => $unit->id,
                    'kapasitas' => 32,
                    'ruangan' => 'Ruang 105',
                ],
            ],
            'overwrite' => true,
        ]);
        $importOverwrite->assertOk()
            ->assertJsonPath('data.berhasil', 0)
            ->assertJsonPath('data.diperbarui', 1)
            ->assertJsonPath('data.duplikat', 0);

        // Pastikan nama kelas dan kapasitas di database BERHASIL ditimpa
        $this->assertDatabaseHas('tbl_kelas', [
            'kode_kelas' => 'KLS-TEST-01',
            'nama_kelas' => 'Kelas 1A Berhasil Ditimpa',
            'kapasitas' => 32,
            'ruangan' => 'Ruang 105',
        ]);

        // 5. Import via file CSV (multipart/form-data)
        $csvContent = "Kode Kelas,Nama Kelas,Jenjang,Tingkat,Unit Pendidikan,Kapasitas,Ruangan,Status\n";
        $csvContent .= "KLS-CSV-01,Kelas 2B Ali,SDIT,2,{$unit->name},30,Ruang 202,Aktif\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('kelas_import.csv', $csvContent);

        $fileRes = $this->actingAs($this->user)->post('/api/kelas/import', [
            'file' => $file,
            'overwrite' => '0',
        ]);
        $fileRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.berhasil', 1);

        $this->assertDatabaseHas('tbl_kelas', [
            'kode_kelas' => 'KLS-CSV-01',
            'nama_kelas' => 'Kelas 2B Ali',
        ]);
    }

    public function test_user_account_import_payload_kosong_dan_overwrite(): void
    {
        // 1. Template CSV download
        $tpl = $this->actingAs($this->user)->get('/api/hak-akses/users/import-template');
        $tpl->assertOk();

        // 2. Payload kosong harus return 422
        $resKosong = $this->actingAs($this->user)->postJson('/api/hak-akses/users/import', [
            'data' => [],
        ]);
        $resKosong->assertStatus(422)
            ->assertJsonPath('message', 'Payload data impor tidak boleh kosong.');

        // 3. Import akun baru
        $res1 = $this->actingAs($this->user)->postJson('/api/hak-akses/users/import', [
            'data' => [
                [
                    'name' => 'Akun Pengguna Uji 1',
                    'email' => 'uji1@sekolah.sch.id',
                    'phone' => '081234567891',
                    'role' => 'Guru',
                    'password' => 'Password123!',
                    'is_active' => true,
                ],
            ],
            'overwrite' => false,
        ]);
        $res1->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.berhasil', 1)
            ->assertJsonPath('data.duplikat', 0);

        $this->assertDatabaseHas('users', [
            'email' => 'uji1@sekolah.sch.id',
            'name' => 'Akun Pengguna Uji 1',
        ]);

        // 4. Import duplikat TANPA overwrite (harus dilewati dan status Dilewati)
        $resDup = $this->actingAs($this->user)->postJson('/api/hak-akses/users/import', [
            'data' => [
                [
                    'name' => 'Akun Berubah Tanpa Overwrite',
                    'email' => 'uji1@sekolah.sch.id',
                    'phone' => '089999999999',
                ],
            ],
            'overwrite' => false,
        ]);
        $resDup->assertOk()
            ->assertJsonPath('data.berhasil', 0)
            ->assertJsonPath('data.duplikat', 1)
            ->assertJsonPath('data.diperbarui', 0);

        $this->assertDatabaseHas('users', [
            'email' => 'uji1@sekolah.sch.id',
            'name' => 'Akun Pengguna Uji 1',
        ]);

        // 5. Import duplikat DENGAN overwrite: true (harus ditimpa dan status Ditimpa)
        $resOvr = $this->actingAs($this->user)->postJson('/api/hak-akses/users/import', [
            'data' => [
                [
                    'name' => 'Akun Berhasil Ditimpa',
                    'email' => 'uji1@sekolah.sch.id',
                    'phone' => '087777777777',
                ],
            ],
            'overwrite' => true,
        ]);
        $resOvr->assertOk()
            ->assertJsonPath('data.berhasil', 0)
            ->assertJsonPath('data.diperbarui', 1)
            ->assertJsonPath('data.duplikat', 0);

        $this->assertDatabaseHas('users', [
            'email' => 'uji1@sekolah.sch.id',
            'name' => 'Akun Berhasil Ditimpa',
            'phone' => '087777777777',
        ]);

        // 6. Import via File CSV (multipart/form-data)
        $csvContent = "name,email,role,phone,password,is_active\n";
        $csvContent .= "Pengguna CSV,pengguna.csv@sekolah.sch.id,Guru,081233445566,Password123!,true\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('users_import.csv', $csvContent);

        $resFile = $this->actingAs($this->user)->post('/api/hak-akses/users/import', [
            'file' => $file,
            'overwrite' => '0',
        ]);
        $resFile->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.berhasil', 1);

        $this->assertDatabaseHas('users', [
            'email' => 'pengguna.csv@sekolah.sch.id',
            'name' => 'Pengguna CSV',
        ]);
    }

    public function test_kelas_update_with_unchanged_kode_kelas_succeeds(): void
    {
        $unit = \App\Models\EducationUnit::create([
            'code' => 'SDIT-TST',
            'name' => 'SDIT Test',
            'level' => 'SD',
            'is_active' => true,
        ]);
        $year = \App\Models\AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $semester = \App\Models\Semester::create([
            'academic_year_id' => $year->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);
        $kelas = \App\Models\Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'KLS-QA-99',
            'nama_kelas' => 'Kelas 1 QA',
            'kapasitas' => 28,
            'status' => 'Aktif',
        ]);

        $res = $this->actingAs($this->user)->putJson("/api/kelas/{$kelas->id}", [
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $year->id,
            'semester_id' => $semester->id,
            'jenjang' => 'SD',
            'tingkat' => '1',
            'kode_kelas' => 'KLS-QA-99',
            'nama_kelas' => 'Kelas 1 QA Edited',
            'kapasitas' => 30,
            'status' => 'Aktif',
        ]);

        $res->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.kode_kelas', 'KLS-QA-99')
            ->assertJsonPath('data.nama_kelas', 'Kelas 1 QA Edited');

        $this->assertDatabaseHas('tbl_kelas', [
            'id' => $kelas->id,
            'kode_kelas' => 'KLS-QA-99',
            'nama_kelas' => 'Kelas 1 QA Edited',
            'kapasitas' => 30,
        ]);
    }
}
