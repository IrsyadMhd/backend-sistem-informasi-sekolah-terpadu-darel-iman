<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorshipAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'AttendancePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'WorshipAttendanceSeeder']);
    }

    public function test_can_list_templates_and_generate_sessions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/worship-attendance/templates');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $sessionResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/worship-attendance/sessions?date='.today()->toDateString());

        $sessionResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_scan_recording_and_musyrif_verification(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Musyrif / Musyrifah');
        $template = WorshipAttendanceTemplate::first();
        $session = WorshipAttendanceSession::where('template_id', $template->id)
            ->whereDate('session_date', today())
            ->first();

        if (! $session) {
            $session = WorshipAttendanceSession::create([
                'id' => (string) Str::uuid(),
                'template_id' => $template->id,
                'session_date' => today()->toDateString(),
                'status' => 'opened',
            ]);
        }

        $student = Student::create([
            'id' => (string) Str::uuid(),
            'nis' => '999'.rand(10000, 99999),
            'nisn' => '999'.rand(10000, 99999),
            'nama_lengkap' => 'Santriwati Test',
            'full_name' => 'Santriwati Test',
            'jenis_kelamin' => 'P',
            'gender' => 'P',
            'is_active' => true,
        ]);

        // Scan test
        $scanRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/worship-attendance/sessions/{$session->id}/scan", [
                'student_id' => $student->id,
                'method' => 'qr',
            ]);

        $scanRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verification test with female privacy status
        $verifyRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/worship-attendance/sessions/{$session->id}/verify", [
                'student_id' => $student->id,
                'attendance_status' => 'haid',
                'notes' => 'Uzur syar\'i',
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('worship_attendance_details', [
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attendance_status' => 'haid',
            'is_private' => true,
        ]);
    }
}
