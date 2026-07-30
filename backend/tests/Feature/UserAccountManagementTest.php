<?php

namespace Tests\Feature;

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

    public function test_non_super_admin_cannot_manage_login_accounts(): void
    {
        Sanctum::actingAs(User::where('email', 'kepsek@school-erp.local')->firstOrFail());

        $this->getJson('/api/hak-akses/users')->assertForbidden();
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
