<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step13R_ADashboardRefactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_primary_roles_access_their_respective_dashboards_without_error(): void
    {
        $roleLogins = [
            'SuperAdmin' => ['email' => 'superadmin@school-erp.local', 'endpoint' => '/api/dashboard/super-admin'],
            'Yayasan' => ['email' => 'yayasan@school-erp.local', 'endpoint' => '/api/foundation/dashboard'],
            'Kepsek' => ['email' => 'kepsek@school-erp.local', 'endpoint' => '/api/dashboard/kepala-sekolah'],
            'TU' => ['email' => 'tu@school-erp.local', 'endpoint' => '/api/dashboard/tata-usaha'],
            'Guru' => ['email' => 'guru@school-erp.local', 'endpoint' => '/api/teacher/dashboard'],
            'WaliKelas' => ['email' => 'wali.kelas@school-erp.local', 'endpoint' => '/api/dashboard/wali-kelas'],
            'GuruTahfizh' => ['email' => 'guru.tahfizh@school-erp.local', 'endpoint' => '/api/dashboard/guru-tahfizh'],
            'Musyrif' => ['email' => 'musyrif@school-erp.local', 'endpoint' => '/api/dashboard/guru-tahfizh'],
            'Parent' => ['email' => 'orangtua@school-erp.local', 'endpoint' => '/api/portal/children'],
            'Student' => ['email' => 'siswa@school-erp.local', 'endpoint' => '/api/portal/dashboard'],
        ];

        foreach ($roleLogins as $role => $config) {
            $user = User::where('email', $config['email'])->first();
            $this->assertNotNull($user, "User for role {$role} not found");

            $response = $this->actingAs($user, 'sanctum')->getJson($config['endpoint']);
            $response->assertOk();
        }
    }

    public function test_sidebar_color_branding_and_print_integrity_remains_frozen(): void
    {
        $siteSettings = \App\Models\SiteSetting::query()->first();
        // Dynamic DB branding should exist
        $this->assertNotNull($siteSettings);
        $this->assertSame('#0E5C44', $siteSettings->sidebar_color);

        $employee = \App\Models\Employee::where('niy', 'TEST-NIY-17')->first();
        $this->assertNotNull($employee);

        $qr = \App\Models\QrCredential::where('employee_id', $employee->id)->active()->first();
        $this->assertNotNull($qr);
    }
}
