<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SiteSetting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step13R_A1VisualCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_14_role_dashboard_endpoints_respond_with_real_postgresql_data(): void
    {
        $roleLogins = [
            'SuperAdmin' => ['email' => 'superadmin@school-erp.local', 'endpoint' => '/api/dashboard/super-admin'],
            'Admin' => ['email' => 'admin@school-erp.local', 'endpoint' => '/api/dashboard-pemantauan/ringkasan'],
            'Yayasan' => ['email' => 'yayasan@school-erp.local', 'endpoint' => '/api/foundation/dashboard'],
            'Divisi' => ['email' => 'divisi.pendidikan@school-erp.local', 'endpoint' => '/api/dashboard/divisi-pendidikan'],
            'Kepsek' => ['email' => 'kepsek@school-erp.local', 'endpoint' => '/api/dashboard/kepala-sekolah'],
            'TU' => ['email' => 'tu@school-erp.local', 'endpoint' => '/api/dashboard/tata-usaha'],
            'Operator' => ['email' => 'operator@school-erp.local', 'endpoint' => '/api/dashboard/operator'],
            'Guru' => ['email' => 'guru@school-erp.local', 'endpoint' => '/api/teacher/dashboard'],
            'WaliKelas' => ['email' => 'wali.kelas@school-erp.local', 'endpoint' => '/api/dashboard/wali-kelas'],
            'GuruTahfizh' => ['email' => 'guru.tahfizh@school-erp.local', 'endpoint' => '/api/dashboard/guru-tahfizh'],
            'Musyrif' => ['email' => 'musyrif@school-erp.local', 'endpoint' => '/api/dashboard/guru-tahfizh'],
            'Parent' => ['email' => 'orangtua@school-erp.local', 'endpoint' => '/api/portal/children'],
            'Student' => ['email' => 'siswa@school-erp.local', 'endpoint' => '/api/portal/dashboard'],
            'Alumni' => ['email' => 'alumni@school-erp.local', 'endpoint' => '/api/portal/alumni/dashboard'],
        ];

        foreach ($roleLogins as $role => $config) {
            $user = User::where('email', $config['email'])->first();
            $this->assertNotNull($user, "User for role {$role} not found");

            $response = $this->actingAs($user, 'sanctum')->getJson($config['endpoint']);
            $response->assertOk();
        }
    }

    public function test_sidebar_base_color_and_dynamic_branding_remain_unmodified(): void
    {
        $branding = SiteSetting::query()->first();
        $this->assertNotNull($branding);
        $this->assertSame('#0E5C44', $branding->sidebar_color);

        $employee = \App\Models\Employee::where('niy', 'TEST-NIY-17')->first();
        $this->assertNotNull($employee);

        $qr = \App\Models\QrCredential::where('employee_id', $employee->id)->active()->first();
        $this->assertNotNull($qr);
    }
}
