<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JabatanTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dapat_menambah_jabatan_baru_dengan_deskripsi(): void
    {
        $payload = [
            'kode_jabatan' => 'JBT-999',
            'nama_jabatan' => 'Bendahara Yayasan',
            'level_jabatan' => 2,
            'urutan' => 1,
            'warna' => '#3B82F6',
            'ikon' => 'UserCheck',
            'deskripsi' => 'Bertanggung jawab atas pengelolaan keuangan yayasan',
            'status' => 'Aktif',
            'tampil_struktur' => true,
            'boleh_login' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/jabatan', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nama_jabatan', 'Bendahara Yayasan')
            ->assertJsonPath('data.deskripsi', 'Bertanggung jawab atas pengelolaan keuangan yayasan');

        $this->assertDatabaseHas('positions', [
            'code' => 'JBT-999',
            'name' => 'Bendahara Yayasan',
            'description' => 'Bertanggung jawab atas pengelolaan keuangan yayasan',
        ]);
    }
}
