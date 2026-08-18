<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SESI 10 CLOSURE — Rate limit login portal orang tua/siswa.
 *
 * Kedua alias route (/api/auth/login/parent-student dan
 * /api/v2/auth/login/parent-student) memakai throttle:10,1. Karena kunci
 * throttle Laravel dibangun dari domain + IP (bukan URL), kedua alias
 * berbagi satu penghitung sehingga percobaan ke-11 (melalui alias mana pun)
 * harus ditolak 429.
 */
class ParentStudentLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_parent_student_throttles_after_ten_failures_across_aliases(): void
    {
        RateLimiter::clear('api');

        $payload = [
            'portal_type' => 'parent',
            'identifier' => 'tidak-ada',
            'password' => 'salah',
        ];

        // Alias pertama: 10 percobaan gagal masih ditoleransi (401 kredensial salah).
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.7'])
                ->postJson('/api/auth/login/parent-student', $payload)
                ->assertStatus(401);
        }

        // Percobaan ke-11 lewat alias kedua → kunci throttle sama → 429.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.7'])
            ->postJson('/api/v2/auth/login/parent-student', $payload)
            ->assertStatus(429);
    }
}
