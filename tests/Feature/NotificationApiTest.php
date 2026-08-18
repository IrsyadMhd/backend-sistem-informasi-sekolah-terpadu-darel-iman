<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Notification;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_and_mark_notifications(): void
    {
        $ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);

        $user = User::factory()->create();

        $notif = Notification::create([
            'academic_year_id' => $ay->id,
            'semester_id' => $sem->id,
            'month' => now()->month,
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'title' => 'Ujian Dimulai',
            'body' => 'Jadwal ujian matematika telah dibuka',
            'channel' => 'web',
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/notifications');

        $response->assertStatus(200);

        $unreadResponse = $this->actingAs($user)
            ->getJson('/api/notifications/unread-count');

        $unreadResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        $readResponse = $this->actingAs($user)
            ->postJson("/api/notifications/{$notif->id}/read");

        $readResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotNull($notif->fresh()->read_at);

    }
}
