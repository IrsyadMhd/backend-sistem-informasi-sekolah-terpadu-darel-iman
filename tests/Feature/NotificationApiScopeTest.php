<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Notification;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * SESI 12 — SCOPE API NOTIFIKASI.
 *
 * Notifikasi bersifat privat per pengguna (recipient scope): satu akun tidak
 * boleh melihat/menandai milik akun lain. Sumber kebenaran tunggal memakai
 * notifiable_id (skema kanonik), termasuk pada kanal portal guru.
 */
class NotificationApiScopeTest extends TestCase
{
    use RefreshDatabase;

    private function activateAcademicContext(): array
    {
        $ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);

        return [$ay, $sem];
    }

    public function test_user_cannot_read_or_mark_other_user_notification(): void
    {
        [$ay, $sem] = $this->activateAcademicContext();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $notification = Notification::deliver(
            userId: $owner->id,
            title: 'Rahasia',
            body: 'Milik owner.',
            channel: 'general',
        );

        $this->actingAs($intruder)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($intruder)
            ->postJson('/api/notifications/'.$notification->id.'/read')
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_filters_search_type_and_is_read_work(): void
    {
        $this->activateAcademicContext();
        $user = User::factory()->create();

        Notification::deliver(userId: $user->id, title: 'Ujian Matematika', body: 'Mulai pukul 08.00.', channel: 'exam');
        Notification::deliver(userId: $user->id, title: 'Tugas IPA', body: 'Kumpul Jumat.', channel: 'assignment');
        Notification::deliver(userId: $user->id, title: 'Pesan Guru', body: 'Sampai jumpa besok.', channel: 'chat');

        $this->actingAs($user)->getJson('/api/notifications?type=exam')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'exam');

        $this->actingAs($user)->getJson('/api/notifications?search=IPA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tugas IPA');

        $this->actingAs($user)->getJson('/api/notifications?is_read=false')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $first = Notification::query()->first();
        $first->update(['read_at' => now()]);

        $this->actingAs($user)->getJson('/api/notifications?is_read=false')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)->getJson('/api/notifications?is_read=true')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_mark_all_read_only_touches_own_notifications(): void
    {
        $this->activateAcademicContext();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Notification::deliver(userId: $userA->id, title: 'Untuk A', body: 'x', channel: 'general');
        Notification::deliver(userId: $userB->id, title: 'Untuk B', body: 'y', channel: 'general');

        $this->actingAs($userA)->postJson('/api/notifications/mark-all-read')
            ->assertOk();

        $this->assertSame(1, Notification::query()->where('notifiable_id', $userA->id)->whereNotNull('read_at')->count());
        $this->assertSame(0, Notification::query()->where('notifiable_id', $userB->id)->whereNotNull('read_at')->count());
    }

    public function test_pagination_limits_per_page(): void
    {
        $this->activateAcademicContext();
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            Notification::deliver(userId: $user->id, title: 'Pesan '.$i, body: 'b', channel: 'general');
        }

        $this->actingAs($user)->getJson('/api/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('per_page', 2);
    }

    public function test_teacher_portal_notifications_use_canonical_recipient_scope(): void
    {
        $this->activateAcademicContext();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        Role::firstOrCreate(['name' => 'Guru', 'guard_name' => 'web']);

        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $other = User::factory()->create();

        Notification::deliver(userId: $guru->id, title: 'Notifikasi Guru', body: 'Hanya milik guru.', channel: 'web');
        Notification::deliver(userId: $other->id, title: 'Notifikasi Lain', body: 'Milik akun lain.', channel: 'web');

        // Endpoint portal guru sebelumnya memakai kolom user_id (tidak ada di
        // skema kanonik) sehingga tidak pernah mengembalikan data. Sekarang
        // memakai notifiable_id via userQuery().
        $this->actingAs($guru)
            ->getJson('/api/teacher/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Notifikasi Guru')
            ->assertJsonMissing(['title' => 'Notifikasi Lain']);
    }
}
