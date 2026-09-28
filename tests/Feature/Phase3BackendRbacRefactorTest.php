<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\DeleteRequest;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\MasterKurikulum;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Models\WorshipAttendanceDetail;
use App\Services\Approval\DeleteRequestService;
use App\Services\AttendanceAccessService;
use App\Services\LmsMateriService;
use App\Services\NavigationService;
use App\Services\WorshipAttendanceService;
use App\Support\RoleName;
use Database\Seeders\AppNavigationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

class Phase3BackendRbacRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppNavigationSeeder::class);
    }

    public function test_super_admin_bypass_via_gate_and_role_name_normalizer(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $this->assertTrue(RoleName::userHasAny($superAdmin, ['super_admin']));
        $this->assertTrue(RoleName::userHasAny($superAdmin, ['Super Admin']));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('any.arbitrary.ability'));
    }

    public function test_lms_materi_service_filters_by_permission_rather_than_hardcoded_role(): void
    {
        $unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity',
            'code' => 'SMP-AG',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'tahun' => '2025/2026',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $tahun->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);

        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-SMP',
            'nama_kurikulum' => 'Kurikulum Merdeka SMP',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $subject = Subject::create([
            'unit_pendidikan_id' => $unit->id,
            'kurikulum_id' => $kurikulum->id,
            'kode_mapel' => 'PAI-SMP',
            'nama_mapel' => 'Pendidikan Agama Islam',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester_id' => $semester->id,
            'kode_kelas' => 'K-7-A',
            'nama_kelas' => '7-A',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => true,
        ]);

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('Guru');

        $teacherEmp = Employee::create([
            'niy' => '199505052022011003',
            'nama_lengkap' => 'Ustadz Hanif, S.Pd.I',
            'user_id' => $teacherUser->id,
            'jenis_kelamin' => 'L',
            'unit_id' => $unit->id,
            'status' => 'Aktif',
            'status_pegawai' => 'Tetap',
        ]);

        $otherTeacherUser = User::factory()->create();
        $otherTeacherUser->assignRole('Guru');

        $otherTeacherEmp = Employee::create([
            'niy' => '199505052022011004',
            'nama_lengkap' => 'Ustadz Budi, S.Pd.I',
            'user_id' => $otherTeacherUser->id,
            'jenis_kelamin' => 'L',
            'unit_id' => $unit->id,
            'status' => 'Aktif',
            'status_pegawai' => 'Tetap',
        ]);

        $modulOwn = LmsModulAjar::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester_id' => $semester->id,
            'kurikulum_id' => $kurikulum->id,
            'mata_pelajaran_id' => $subject->id,
            'guru_id' => $teacherEmp->id,
            'kelas_id' => $kelas->id,
            'kode_modul' => 'MA-7-01',
            'judul_modul' => 'Al-Qur\'an dan Kelestarian Alam',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 4,
            'status' => 'Publish',
        ]);

        $modulOther = LmsModulAjar::create([
            'unit_pendidikan_id' => $unit->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester_id' => $semester->id,
            'kurikulum_id' => $kurikulum->id,
            'mata_pelajaran_id' => $subject->id,
            'guru_id' => $otherTeacherEmp->id,
            'kelas_id' => $kelas->id,
            'kode_modul' => 'MA-7-02',
            'judul_modul' => 'Fiqih Ibadah',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 4,
            'status' => 'Publish',
        ]);

        $lmsService = app(LmsMateriService::class);

        // Teacher only sees own module
        $teacherOptions = $lmsService->opsi($teacherUser);
        $teacherModulIds = $teacherOptions['modul_ajar']->pluck('id')->all();
        $this->assertContains($modulOwn->id, $teacherModulIds);
        $this->assertNotContains($modulOther->id, $teacherModulIds);

        // User with academic.manage sees all modules
        $academicManager = User::factory()->create();
        $academicManager->givePermissionTo('academic.manage');

        $managerOptions = $lmsService->opsi($academicManager);
        $managerModulIds = $managerOptions['modul_ajar']->pluck('id')->all();
        $this->assertContains($modulOwn->id, $managerModulIds);
        $this->assertContains($modulOther->id, $managerModulIds);
    }

    public function test_worship_attendance_service_unmasks_private_status_via_permission(): void
    {
        $detail = new WorshipAttendanceDetail([
            'attendance_status' => 'haid',
            'is_private' => true,
            'notes' => 'Sedang berhalangan syar’i',
        ]);

        $service = app(WorshipAttendanceService::class);

        // User without permission: masked to 'uzur'
        $regularUser = User::factory()->create();
        $maskedData = $service->formatDetailForUser($detail, $regularUser);
        $this->assertEquals('uzur', $maskedData['attendance_status']);
        $this->assertEquals("Uzur Syar'i", $maskedData['notes']);

        // User with permission: unmasked
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo('worship_attendance.private_status.view');
        $unmaskedData = $service->formatDetailForUser($detail, $authorizedUser);
        $this->assertEquals('haid', $unmaskedData['attendance_status']);
        $this->assertEquals('Sedang berhalangan syar’i', $unmaskedData['notes']);
    }

    public function test_delete_request_service_enforces_sod_and_permission(): void
    {
        $service = app(DeleteRequestService::class);

        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();

        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $approver->givePermissionTo('approval.manage');

        $deleteRequest = $service->createDeleteRequest(
            requester: $requester,
            targetTable: 'students',
            targetId: $student1->id,
            targetLabel: 'Siswa Test',
            reason: 'Data duplikat pendaftaran'
        );

        $approved = $service->approveDeleteRequest($approver, $deleteRequest->id);
        $this->assertEquals('approved', $approved->status);

        // Requester cannot approve own request (SoD)
        $requesterApprover = User::factory()->create();
        $requesterApprover->givePermissionTo('approval.manage');
        $ownRequest = $service->createDeleteRequest(
            requester: $requesterApprover,
            targetTable: 'students',
            targetId: $student2->id,
            targetLabel: 'Siswa Test 2',
            reason: 'Salah input'
        );

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->approveDeleteRequest($requesterApprover, $ownRequest->id);
    }

    public function test_dynamic_navigation_alignment_for_canonical_ui_roles(): void
    {
        $navService = app(NavigationService::class);

        // 1. Super Admin sees all modules
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $saModules = $navService->getModulesForUser($superAdmin, 'web');
        $this->assertGreaterThanOrEqual(7, count($saModules));

        // 2. Guru sees dashboard, akademik, lms, presensi, mutabaah
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $guruModules = collect($navService->getModulesForUser($guru, 'web'))->pluck('id')->all();
        $this->assertContains('dashboard', $guruModules);
        $this->assertContains('lms', $guruModules);
        $this->assertNotContains('pengaturan', $guruModules);

        // 3. Tata Usaha sees dashboard, master-data, akademik, presensi, laporan
        $tu = User::factory()->create();
        $tu->assignRole('Tata Usaha');
        $tuModules = collect($navService->getModulesForUser($tu, 'web'))->pluck('id')->all();
        $this->assertContains('dashboard', $tuModules);
        $this->assertContains('master-data', $tuModules);
        $this->assertNotContains('pengaturan', $tuModules);

        // 4. Pilot Role: Wakil Humas dan Program Khusus sees lap-alumni and dash-monitoring
        $humasRole = Role::firstOrCreate(['name' => 'Wakil Humas dan Program Khusus', 'guard_name' => 'web']);
        $humasRole->givePermissionTo([
            'dashboard.view',
            'kesiswaan.kelulusan_per_tahun',
            'kesiswaan.alumni_tujuan_lanjut',
            'kesiswaan.data_lengkap_siswa',
            'student.view',
            'report.student.view',
            'report.attendance.view',
        ]);
        $humasUser = User::factory()->create();
        $humasUser->assignRole('Wakil Humas dan Program Khusus');

        $humasModules = $navService->getModulesForUser($humasUser, 'web');
        $humasMenuIds = collect($humasModules)->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-monitoring', $humasMenuIds);
        $this->assertContains('lap-alumni', $humasMenuIds);

        // 5. Pilot Role: Bendahara sees dash-tu, dash-monitoring and master-kelas
        $bendaharaRole = Role::firstOrCreate(['name' => 'Bendahara', 'guard_name' => 'web']);
        $bendaharaRole->givePermissionTo([
            'dashboard.view',
            'dashboard.tata-usaha.view',
            'kesiswaan.kelas_rombel',
            'student.view',
            'report.student.view',
            'approval.manage',
        ]);
        $bendaharaUser = User::factory()->create();
        $bendaharaUser->assignRole('Bendahara');

        $bendaharaModules = $navService->getModulesForUser($bendaharaUser, 'web');
        $bendaharaMenuIds = collect($bendaharaModules)->flatMap(fn ($m) => $m['items'])->pluck('id')->all();
        $this->assertContains('dash-tu', $bendaharaMenuIds);
        $this->assertContains('dash-monitoring', $bendaharaMenuIds);
        $this->assertContains('master-kelas', $bendaharaMenuIds);
    }
}
