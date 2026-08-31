<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\User;
use Database\Seeders\DefaultRoleUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DefaultRoleUserSeeder::class);
    }

    public function test_super_admin_can_crud_login_account_and_reset_password(): void
    {
        $admin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/hak-akses/users', [
            'name' => 'Guru Baru',
            'email' => 'guru.baru@example.test',
            'phone' => '08123456789',
            'role' => 'Guru',
            'is_active' => true,
            'password' => 'GuruBaru@2026!',
            'password_confirmation' => 'GuruBaru@2026!',
        ])->assertCreated()->json('data');

        $user = User::findOrFail($created['id']);
        $this->assertTrue(Hash::check('GuruBaru@2026!', $user->password));
        $this->assertTrue($user->hasRole('Guru'));

        $this->putJson("/api/hak-akses/users/{$user->id}", [
            'name' => 'Guru Baru Diperbarui',
            'email' => 'guru.baru@example.test',
            'phone' => null,
            'role' => 'Tata Usaha',
            'is_active' => false,
        ])->assertOk();
        $this->assertTrue($user->fresh()->hasRole('Tata Usaha'));
        $this->assertFalse($user->fresh()->is_active);

        $this->putJson("/api/hak-akses/users/{$user->id}/password", [
            'password' => 'PasswordBaru@2026!',
            'password_confirmation' => 'PasswordBaru@2026!',
        ])->assertOk();
        $this->assertTrue(Hash::check('PasswordBaru@2026!', $user->fresh()->password));

        $this->deleteJson("/api/hak-akses/users/{$user->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_school_principal_and_administration_can_update_username_and_password_in_their_unit(): void
    {
        $unit = EducationUnit::factory()->create();

        foreach (['kepsek@school-erp.local', 'tu@school-erp.local'] as $index => $managerEmail) {
            $manager = User::where('email', $managerEmail)->firstOrFail();
            $target = User::where('email', $index === 0 ? 'guru@school-erp.local' : 'guru.tahfizh@school-erp.local')->firstOrFail();
            $manager->employee()->update(['unit_id' => $unit->id]);
            $target->employee()->update(['unit_id' => $unit->id]);
            Sanctum::actingAs($manager);

            $newEmail = "akun.unit.{$index}@example.test";
            $this->putJson("/api/hak-akses/users/{$target->id}", [
                'name' => "Akun Unit {$index}",
                'email' => $newEmail,
                'role' => $target->getRoleNames()->first(),
            ])->assertOk();

            $this->assertSame($newEmail, $target->fresh()->email);

            $password = "PasswordUnit@2026{$index}!";
            $this->putJson("/api/hak-akses/users/{$target->id}/password", [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertOk();
            $this->assertTrue(Hash::check($password, $target->fresh()->password));
        }
    }

    public function test_unit_manager_cannot_update_a_global_account(): void
    {
        Sanctum::actingAs(User::where('email', 'kepsek@school-erp.local')->firstOrFail());
        $superAdmin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();

        $this->putJson("/api/hak-akses/users/{$superAdmin->id}/password", [
            'password' => 'TidakBoleh@2026!',
            'password_confirmation' => 'TidakBoleh@2026!',
        ])->assertForbidden();
    }

    public function test_admin_cannot_disable_or_delete_own_account(): void
    {
        $admin = User::where('email', 'superadmin@school-erp.local')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson("/api/hak-akses/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => null,
            'role' => 'Super Admin',
            'is_active' => false,
        ])->assertUnprocessable();

        $this->deleteJson("/api/hak-akses/users/{$admin->id}")->assertUnprocessable();
    }

    public function test_weak_password_is_rejected(): void
    {
        Sanctum::actingAs(User::where('email', 'superadmin@school-erp.local')->firstOrFail());

        $this->postJson('/api/hak-akses/users', [
            'name' => 'Akun Lemah',
            'email' => 'lemah@example.test',
            'role' => 'Guru',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
