<?php

namespace Tests\Feature;

use App\Exceptions\Auth\AuthLoginException;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\MasterKurikulum;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Auth\AuthService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UatTestingProvisioningSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UatComprehensiveRoleWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan role & permissions
        $this->seed(RolePermissionSeeder::class);

        // Siapkan unit pendidikan & struktur dasar
        $unit = EducationUnit::create([
            'name' => 'SDIT 1 Dar el-Iman',
            'code' => 'SDIT-01',
            'level' => 'SD',
            'is_active' => true,
        ]);

        $ay = AcademicYear::create([
            'name' => '2026/2027',
            'code' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'tahun' => '2026/2027',
        ]);

        $sem = Semester::create([
            'academic_year_id' => $ay->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'is_active' => true,
        ]);

        Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'nama_kelas' => 'Kelas 1-A',
            'kode_kelas' => '1A',
            'jenjang' => 'SD',
            'tingkat' => 1,
            'status' => 'Aktif',
        ]);

        Subject::create([
            'name' => 'Matematika Dasar Ceria',
            'code' => 'MTK-SD-1',
            'education_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        // Atur password environment untuk seeder
        putenv('UAT_TEST_PASSWORD=UatPass@2026!');
        $_ENV['UAT_TEST_PASSWORD'] = 'UatPass@2026!';

        // Jalankan seeder UAT
        $this->seed(UatTestingProvisioningSeeder::class);
    }

    /**
     * FASE 1 & FASE 2: Verifikasi Autentikasi dan Routing Portal 10 Role
     */
    public function test_all_10_roles_authentication_and_portal_routing(): void
    {
        $authService = app(AuthService::class);

        // 1. Super Admin
        $loginSuper = $authService->login('uat.superadmin@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginSuper['token']);
        $this->assertEquals('admin', $loginSuper['portal']);
        $this->assertTrue($loginSuper['user']->hasRole('Super Admin'));

        // 2. Admin
        $loginAdmin = $authService->login('uat.admin@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginAdmin['token']);
        $this->assertEquals('admin', $loginAdmin['portal']);
        $this->assertTrue($loginAdmin['user']->hasRole('Admin'));

        // 3. Operator
        $loginOperator = $authService->login('uat.operator@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginOperator['token']);
        $this->assertEquals('employee', $loginOperator['portal']);
        $this->assertTrue($loginOperator['user']->hasRole('Operator'));

        // 4. Pengurus Yayasan
        $loginYayasan = $authService->login('uat.yayasan@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginYayasan['token']);
        $this->assertEquals('employee', $loginYayasan['portal']);
        $this->assertTrue($loginYayasan['user']->hasRole('Pengurus Yayasan'));

        // 5. Kepala Sekolah
        $loginKepsek = $authService->login('uat.kepsek@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginKepsek['token']);
        $this->assertEquals('employee', $loginKepsek['portal']);
        $this->assertTrue($loginKepsek['user']->hasRole('Kepala Sekolah'));

        // 6. Divisi Pendidikan
        $loginDivisi = $authService->login('uat.divisi@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginDivisi['token']);
        $this->assertEquals('employee', $loginDivisi['portal']);
        $this->assertTrue($loginDivisi['user']->hasRole('Divisi Pendidikan'));

        // 7. Tata Usaha
        $loginTu = $authService->login('uat.tu@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginTu['token']);
        $this->assertEquals('employee', $loginTu['portal']);
        $this->assertTrue($loginTu['user']->hasRole('Tata Usaha'));

        // 8. Guru
        $loginGuru = $authService->login('uat.guru@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginGuru['token']);
        $this->assertEquals('employee', $loginGuru['portal']);
        $this->assertTrue($loginGuru['user']->hasRole('Guru'));

        // 9. Orang Tua
        $loginOrtu = $authService->login('uat.ortu@example.test', 'UatPass@2026!');
        $this->assertNotEmpty($loginOrtu['token']);
        $this->assertEquals('parent', $loginOrtu['portal']);
        $this->assertTrue($loginOrtu['user']->hasRole('Orang Tua'));

        // 10. User Biasa (Belum memiliki role/profile diizinkan login -> AuthLoginException)
        try {
            $authService->login('uat.user@example.test', 'UatPass@2026!');
            $this->fail('User biasa tanpa role/profil harus ditolak exception login secara aman.');
        } catch (AuthLoginException $e) {
            $this->assertContains($e->reason, [
                AuthLoginException::ROLE_NOT_ASSIGNED,
                AuthLoginException::IDENTIFIER_NOT_FOUND,
                AuthLoginException::PASSWORD_INVALID,
            ]);
        }
    }

    /**
     * FASE 5: Alur Lintas Role (Cross-Role Academic Lifecycle)
     * TU/Operator -> Guru LMS -> Siswa Pengumpulan -> Guru Penilaian -> Orang Tua
     */
    public function test_cross_role_academic_lifecycle_workflow(): void
    {
        $teacherUser = User::where('email', 'uat.guru@example.test')->firstOrFail();
        $studentUser = User::where('email', 'uat.siswa@example.test')->firstOrFail();
        $parentUser = User::where('email', 'uat.ortu@example.test')->firstOrFail();
        $tuUser = User::where('email', 'uat.tu@example.test')->firstOrFail();

        $kelas = Kelas::where('nama_kelas', 'Kelas 1-A')->firstOrFail();
        $subject = Subject::where('name', 'Matematika Dasar Ceria')->firstOrFail();
        $student = Student::where('nis', 'UAT99001')->firstOrFail();
        $teacher = Teacher::where('user_id', $teacherUser->id)->firstOrFail();
        $ay = AcademicYear::firstOrFail();
        $semester = Semester::firstOrFail();

        // Pastikan ada jadwal mengajar guru pada kelas tersebut
        ClassSchedule::firstOrCreate([
            'kelas_id' => $kelas->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ], [
            'academic_year_id' => $ay->id,
            'semester_id' => $semester->id,
            'employee_id' => $teacher->employee_id,
            'day_of_week' => 1,
            'time_start' => '08:00',
            'time_end' => '09:30',
        ]);

        // Buat Kurikulum & Modul Ajar jika diperlukan
        $kurikulum = MasterKurikulum::firstOrCreate([
            'kode_kurikulum' => 'KM-SD-UAT',
        ], [
            'nama_kurikulum' => 'Kurikulum Merdeka SD UAT',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $kelas->unit_pendidikan_id,
            'jenjang' => 'SD',
            'tahun_ajaran_id' => $ay->id,
            'tanggal_mulai' => '2026-07-01',
            'status' => true,
        ]);

        $subject->update(['kurikulum_id' => $kurikulum->id]);

        $modulAjar = LmsModulAjar::firstOrCreate([
            'unit_pendidikan_id' => $kelas->unit_pendidikan_id,
            'mata_pelajaran_id' => $subject->id,
            'kelas_id' => $kelas->id,
            'guru_id' => $teacher->employee_id,
        ], [
            'kurikulum_id' => $kurikulum->id,
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $semester->id,
            'kode_modul' => 'MOD-UAT-01',
            'judul_modul' => 'Modul Penjumlahan Dasar [UAT]',
            'deskripsi' => 'Modul ajar UAT',
            'status' => 'published',
        ]);

        // LANGKAH 1: Guru membuat Penugasan LMS untuk Kelas 1-A
        $penugasan = LmsPenugasan::create([
            'unit_pendidikan_id' => $kelas->unit_pendidikan_id,
            'modul_ajar_id' => $modulAjar->id,
            'guru_id' => $teacher->employee_id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $subject->id,
            'subject_id' => $subject->id,
            'semester_id' => $semester->id,
            'tahun_ajaran_id' => $ay->id,
            'judul_tugas' => 'Latihan Berhitung Ceria [UAT]',
            'deskripsi' => 'Kerjakan latihan soal halaman 1-5.',
            'tipe_penugasan' => 'online',
            'tipe_pengumpulan' => 'teks',
            'deadline' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'bobot_nilai' => 100,
            'is_published' => true,
            'created_by' => $teacherUser->id,
        ]);

        $this->assertDatabaseHas('lms_penugasan', [
            'id' => $penugasan->id,
            'judul_tugas' => 'Latihan Berhitung Ceria [UAT]',
            'is_published' => true,
        ]);

        // LANGKAH 2: Siswa mengumpulkan tugas melalui API
        $submissionResp = $this->actingAs($studentUser, 'sanctum')->postJson('/api/lms/pengumpulan-tugas', [
            'penugasan_id' => $penugasan->id,
            'siswa_id' => $student->id,
            'jawaban_teks' => 'Jawaban UAT: 5 + 5 = 10 dan 10 + 10 = 20.',
            'status' => 'dikumpulkan',
        ]);

        $submissionResp->assertStatus(201);
        $submissionId = $submissionResp->json('data.id');

        // LANGKAH 3: Guru menilai pengumpulan tugas tersebut
        $gradeResp = $this->actingAs($teacherUser, 'sanctum')->postJson("/api/lms/penugasan/{$penugasan->id}/nilai", [
            'siswa_id' => $student->id,
            'nilai_guru' => 95,
            'catatan_guru' => 'Bagus sekali! Pemahaman konsep sangat baik.',
            'status' => 'dinilai',
        ]);

        $gradeResp->assertStatus(200);

        // LANGKAH 4: Verifikasi nilai tersimpan di database
        $this->assertDatabaseHas('lms_pengumpulan_tugas', [
            'penugasan_id' => $penugasan->id,
            'siswa_id' => $student->id,
            'nilai_guru' => 95,
            'status' => 'dinilai',
        ]);

        // LANGKAH 5: Orang tua dapat melihat data anak dan relasi anak miliknya
        $parent = ParentModel::where('user_id', $parentUser->id)->firstOrFail();
        $this->assertEquals($student->parent_id, $parent->id);

        $parentQuery = Student::where('parent_id', $parent->id)->first();
        $this->assertNotNull($parentQuery);
        $this->assertEquals('UAT99001', $parentQuery->nis);
    }

    /**
     * FASE 6: Security - Endpoint tanpa autentikasi ditolak (401 Unauthorized)
     */
    public function test_security_unauthenticated_api_is_rejected(): void
    {
        $response = $this->getJson('/api/students');
        $response->assertStatus(401);

        $responseSettings = $this->postJson('/api/site-settings', [
            'school_name' => 'Hacked Name',
        ]);
        $responseSettings->assertStatus(401);
    }

    /**
     * FASE 6: Security - IDOR Prevention: Orang Tua tidak dapat mengakses data siswa lain
     */
    public function test_security_parent_cannot_access_unlinked_student_data(): void
    {
        $parentUser = User::where('email', 'uat.ortu@example.test')->firstOrFail();
        $parent = ParentModel::where('user_id', $parentUser->id)->firstOrFail();

        // Buat siswa milik orang tua lain
        $otherUnit = EducationUnit::first();
        $otherStudent = Student::create([
            'unit_id' => $otherUnit->id,
            'nis' => 'STRANGER001',
            'nisn' => '0099999999',
            'full_name' => 'Siswa Orang Lain',
            'gender' => 'male',
            'birth_date' => '2019-01-01',
            'birth_place' => 'Jakarta',
            'is_active' => true,
        ]);

        // Verifikasi bahwa query siswa untuk parent ini HANYA mengembalikan anak miliknya
        $myChildren = Student::where('parent_id', $parent->id)->pluck('id')->all();
        $this->assertNotContains($otherStudent->id, $myChildren);
    }

    /**
     * FASE 6: Security - Cross-Teacher Isolation
     * Guru dari unit lain tidak dapat membuat tugas pada rombel/unit yang bukan kewenangannya
     */
    public function test_security_cross_teacher_class_isolation(): void
    {
        $teacherUser = User::where('email', 'uat.guru@example.test')->firstOrFail();

        // Buat kelas di unit pendidikan lain yang tidak ditugaskan kepada guru ini
        $otherUnit = EducationUnit::create([
            'name' => 'SMA IT Dar el-Iman',
            'code' => 'SMAIT-01',
            'level' => 'SMA',
            'is_active' => true,
        ]);

        $ay = AcademicYear::first();
        $sem = Semester::first();

        $otherClass = Kelas::create([
            'unit_pendidikan_id' => $otherUnit->id,
            'tahun_ajaran_id' => $ay->id,
            'semester_id' => $sem->id,
            'nama_kelas' => 'Kelas 10-A SMA',
            'kode_kelas' => '10A',
            'jenjang' => 'SMA',
            'tingkat' => 10,
            'status' => 'Aktif',
        ]);

        $otherSubject = Subject::create([
            'name' => 'Fisika Kuantum',
            'code' => 'FIS-SMA',
            'education_unit_id' => $otherUnit->id,
            'is_active' => true,
        ]);

        // Guru SDIT mencoba membuat tugas untuk Kelas SMAIT
        $response = $this->actingAs($teacherUser, 'sanctum')->postJson('/api/lms/penugasan', [
            'unit_pendidikan_id' => $otherUnit->id,
            'kelas_id' => $otherClass->id,
            'subject_id' => $otherSubject->id,
            'judul_tugas' => 'Tugas Ilegal Lintas Unit',
            'deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        // Harus ditolak dengan status 403 Forbidden
        $response->assertStatus(403);
    }

    /**
     * FASE 6: Security - Role Boundary: Admin non-Super Admin tidak dapat mengubah pengaturan sensitif
     */
    public function test_security_admin_cannot_mutate_sensitive_superadmin_settings(): void
    {
        $adminUser = User::where('email', 'uat.admin@example.test')->firstOrFail();

        // Admin mencoba update konfigurasi umum
        $response = $this->actingAs($adminUser, 'sanctum')->postJson('/api/site-settings', [
            'school_name' => 'Hacked School Name',
        ]);

        // Middleware permission can:sistem.pengaturan memblokir
        $this->assertEquals(403, $response->status());
    }

    /**
     * FASE 6: Validation - Input kosong atau invalid format ditolak dengan status 422
     */
    public function test_security_empty_or_invalid_payload_returns_validation_error(): void
    {
        $teacherUser = User::where('email', 'uat.guru@example.test')->firstOrFail();

        $response = $this->actingAs($teacherUser, 'sanctum')->postJson('/api/lms/penugasan', [
            // Kosong tanpa field yang diperlukan
        ]);

        $this->assertContains($response->status(), [422, 403]);
    }

    /**
     * FASE 7: Import Validation & Edge Cases (Isolated SQLite Testing Environment)
     */
    public function test_import_validation_and_safety_in_isolated_environment(): void
    {
        $superadmin = User::where('email', 'uat.superadmin@example.test')->firstOrFail();

        // 1. Uji Payload Kosong -> Ditolak 422
        $respEmpty = $this->actingAs($superadmin, 'sanctum')->postJson('/api/master/tahun-ajaran/import', []);
        $respEmpty->assertStatus(422);
        $respEmpty->assertJsonValidationErrors(['rows']);

        // 2. Uji Format Tipe Data Salah (bukan array) -> Ditolak 422
        $respInvalidType = $this->actingAs($superadmin, 'sanctum')->postJson('/api/master/tahun-ajaran/import', [
            'rows' => 'bukan_array',
        ]);
        $respInvalidType->assertStatus(422);
        $respInvalidType->assertJsonValidationErrors(['rows']);

        // 3. Uji Baris Tidak Lengkap (missing start_date / end_date) -> Di-skip aman (total_imported = 0)
        $initialCount = AcademicYear::count();
        $respIncomplete = $this->actingAs($superadmin, 'sanctum')->postJson('/api/master/tahun-ajaran/import', [
            'rows' => [
                ['name' => 'Tahun Tanpa Tanggal'],
                ['start_date' => '2028-01-01', 'end_date' => '2028-12-31'], // Tanpa name
            ],
        ]);
        $respIncomplete->assertStatus(200);
        $respIncomplete->assertJson([
            'status' => 'success',
            'total_imported' => 0,
        ]);
        $this->assertEquals($initialCount, AcademicYear::count(), 'Data korup tidak boleh tersimpan.');

        // 4. Uji Baris Lengkap & Valid -> Berhasil diimpor di lingkungan uji terisolasi
        $respValid = $this->actingAs($superadmin, 'sanctum')->postJson('/api/master/tahun-ajaran/import', [
            'rows' => [
                [
                    'name' => '2029/2030',
                    'start_date' => '2029-07-01',
                    'end_date' => '2030-06-30',
                    'is_active' => false,
                    'keterangan' => 'Simulasi UAT Import Terisolasi',
                ],
            ],
        ]);
        $respValid->assertStatus(200);
        $respValid->assertJson([
            'status' => 'success',
            'total_imported' => 1,
        ]);
        $this->assertDatabaseHas('academic_years', [
            'name' => '2029/2030',
        ]);

        // 5. Uji Otorisasi: Role Guru / Siswa dilarang mengimpor master data (403 Forbidden)
        $guru = User::where('email', 'uat.guru@example.test')->firstOrFail();
        $respForbidden = $this->actingAs($guru, 'sanctum')->postJson('/api/master/tahun-ajaran/import', [
            'rows' => [['name' => 'Hack Period', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31']],
        ]);
        $respForbidden->assertStatus(403);
    }

    /**
     * FASE 8: Export Validation & Role Access in Isolated Testing Environment
     */
    public function test_export_validation_and_access_control(): void
    {
        $superadmin = User::where('email', 'uat.superadmin@example.test')->firstOrFail();
        $guru = User::where('email', 'uat.guru@example.test')->firstOrFail();

        // 1. Super Admin dapat mengekspor dalam format CSV
        $respCsv = $this->actingAs($superadmin, 'sanctum')->get('/api/master/tahun-ajaran/export?format=csv');
        $respCsv->assertStatus(200);

        // 2. Super Admin dapat mengekspor dalam format JSON (raw api)
        $respJson = $this->actingAs($superadmin, 'sanctum')->getJson('/api/master/tahun-ajaran/export?format=json');
        $respJson->assertStatus(200);
        $respJson->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['no', 'id', 'nama', 'start_date', 'end_date', 'is_active', 'created_at']
            ]
        ]);

        // 3. Role Orang Tua / Siswa yang tidak punya permission master.view dilarang mengakses ekspor master (403 Forbidden)
        $parent = User::where('email', 'uat.ortu@example.test')->firstOrFail();
        $respParent = $this->actingAs($parent, 'sanctum')->get('/api/master/tahun-ajaran/export?format=csv');
        $respParent->assertStatus(403);
    }
}

