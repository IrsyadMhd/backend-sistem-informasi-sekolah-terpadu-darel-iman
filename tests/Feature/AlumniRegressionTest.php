<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlumniRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    private function createUnit(string $name = 'Unit Test SMA'): EducationUnit
    {
        return EducationUnit::create([
            'id' => (string) Str::uuid(),
            'code' => 'UNIT-'.rand(100, 999),
            'nama' => $name,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        return $user;
    }

    public function test_alumni_index_with_all_true_fetches_all_records_above_200(): void
    {
        $user = $this->createSuperAdmin();
        $unit = $this->createUnit();

        $studentsData = [];
        $now = now();

        // Buat 215 records alumni
        for ($i = 1; $i <= 215; $i++) {
            $studentsData[] = [
                'id' => (string) Str::uuid(),
                'unit_id' => $unit->id,
                'nis' => 'ALM-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nisn' => '999'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'full_name' => "Alumni Siswa {$i}",
                'gender' => $i % 2 === 0 ? 'P' : 'L',
                'is_active' => false,
                'metadata' => json_encode([
                    'is_alumni' => true,
                    'status_alumni' => 'Tamat',
                    'tahun_lulus' => '2025',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Student::insert($studentsData);

        // 1. Permintaan default tanpa all=true: paginasi default (maks 50)
        $paginatedResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/alumni');

        $paginatedResponse->assertOk()
            ->assertJsonPath('total', 215)
            ->assertJsonPath('per_page', 50);
        $this->assertCount(50, $paginatedResponse->json('data'));

        // 2. Permintaan dengan all=true: harus mengambil SELURUH 215 rekaman
        $allResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/alumni?all=true');

        $allResponse->assertOk()
            ->assertJsonPath('total', 215)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 1)
            ->assertJsonPath('per_page', 215);

        $this->assertCount(215, $allResponse->json('data'));
    }

    public function test_alumni_filter_tahun_lulus_and_unit_id_combined(): void
    {
        $user = $this->createSuperAdmin();
        $unitA = $this->createUnit('SMA Islam Dar El-Iman');
        $unitB = $this->createUnit('SMP Islam Dar El-Iman');

        // Unit A, Tahun 2025: 3 siswa
        for ($i = 1; $i <= 3; $i++) {
            Student::create([
                'id' => (string) Str::uuid(),
                'unit_id' => $unitA->id,
                'nis' => 'UA-25-'.$i,
                'full_name' => "Alumni A 2025 {$i}",
                'gender' => 'L',
                'is_active' => false,
                'metadata' => [
                    'is_alumni' => true,
                    'status_alumni' => 'Tamat',
                    'tahun_lulus' => '2025',
                ],
            ]);
        }

        // Unit A, Tahun 2024: 2 siswa
        for ($i = 1; $i <= 2; $i++) {
            Student::create([
                'id' => (string) Str::uuid(),
                'unit_id' => $unitA->id,
                'nis' => 'UA-24-'.$i,
                'full_name' => "Alumni A 2024 {$i}",
                'gender' => 'L',
                'is_active' => false,
                'metadata' => [
                    'is_alumni' => true,
                    'status_alumni' => 'Tamat',
                    'tahun_lulus' => '2024',
                ],
            ]);
        }

        // Unit B, Tahun 2025: 4 siswa
        for ($i = 1; $i <= 4; $i++) {
            Student::create([
                'id' => (string) Str::uuid(),
                'unit_id' => $unitB->id,
                'nis' => 'UB-25-'.$i,
                'full_name' => "Alumni B 2025 {$i}",
                'gender' => 'L',
                'is_active' => false,
                'metadata' => [
                    'is_alumni' => true,
                    'status_alumni' => 'Tamat',
                    'tahun_lulus' => '2025',
                ],
            ]);
        }

        // Query filter Unit A + Tahun 2025
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/alumni?unit_id={$unitA->id}&tahun_lulus=2025&all=true");

        $response->assertOk()
            ->assertJsonPath('total', 3);
        $this->assertCount(3, $response->json('data'));

        foreach ($response->json('data') as $row) {
            $this->assertEquals($unitA->id, $row['unit_id']);
            $this->assertEquals('2025', $row['metadata']['tahun_lulus']);
        }
    }

    public function test_active_students_without_alumni_status_excluded_from_rekap(): void
    {
        $user = $this->createSuperAdmin();
        $unit = $this->createUnit();

        // 1. Siswa aktif normal (tidak boleh masuk alumni)
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'AKTIF-001',
            'full_name' => 'Siswa Aktif Belajar',
            'gender' => 'L',
            'is_active' => true,
            'metadata' => ['status_siswa' => 'aktif'],
        ]);

        // 2. Siswa non-aktif tapi BUKAN karena tamat/lulus/mutasi (misal cuti/nonaktif sementara)
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'NONAKTIF-001',
            'full_name' => 'Siswa Nonaktif Sementara',
            'gender' => 'L',
            'is_active' => false,
            'metadata' => ['alasan' => 'cuti'],
        ]);

        // 3. Siswa Alumni yang sah
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'ALUMNI-001',
            'full_name' => 'Siswa Alumni Sah',
            'gender' => 'L',
            'is_active' => false,
            'metadata' => [
                'is_alumni' => true,
                'status_alumni' => 'Tamat',
                'tahun_lulus' => '2025',
                'status_lanjutan' => 'Kuliah',
                'tujuan_kelulusan' => 'Universitas Andalas',
            ],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/alumni?all=true');

        $response->assertOk()
            ->assertJsonPath('total', 1);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('ALUMNI-001', $data[0]['nis']);
        $this->assertEquals('Siswa Alumni Sah', $data[0]['full_name']);

        $statsResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/alumni/stats');

        $statsResponse->assertOk()
            ->assertJsonPath('data.total_alumni', 1);
    }

    public function test_alumni_aggregation_matches_backend_stats_and_filtered_index(): void
    {
        $user = $this->createSuperAdmin();
        $unit = $this->createUnit();

        // 2 Kuliah
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'KLH-1',
            'full_name' => 'Alumni Kuliah 1',
            'gender' => 'L',
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'tahun_lulus' => '2025', 'tujuan_kelulusan' => 'Universitas Indonesia', 'status_lanjutan' => 'kuliah'],
        ]);
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'KLH-2',
            'full_name' => 'Alumni Kuliah 2',
            'gender' => 'P',
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'tahun_lulus' => '2025', 'tujuan_kelulusan' => 'ITB Kampus Ganesha', 'status_lanjutan' => 'kuliah'],
        ]);

        // 1 Bekerja
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'KRJ-1',
            'full_name' => 'Alumni Kerja 1',
            'gender' => 'L',
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'tahun_lulus' => '2025', 'tujuan_kelulusan' => 'PT Semen Padang', 'status_lanjutan' => 'bekerja'],
        ]);

        // 1 Pesantren
        Student::create([
            'id' => (string) Str::uuid(),
            'unit_id' => $unit->id,
            'nis' => 'PST-1',
            'full_name' => 'Alumni Pesantren 1',
            'gender' => 'L',
            'is_active' => false,
            'metadata' => ['is_alumni' => true, 'tahun_lulus' => '2025', 'tujuan_kelulusan' => "Ma'had Darussunnah", 'status_lanjutan' => 'pesantren'],
        ]);

        // Ambil stats
        $statsResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/alumni/stats?unit_id={$unit->id}");

        $statsResponse->assertOk()
            ->assertJsonPath('data.total_alumni', 4)
            ->assertJsonPath('data.lanjut_studi', 4);

        // Ambil index dengan tujuan_kelulusan=kuliah
        $kuliahResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/alumni?unit_id={$unit->id}&tujuan_kelulusan=kuliah&all=true");

        $kuliahResponse->assertOk()
            ->assertJsonPath('total', 2);
        $this->assertCount(2, $kuliahResponse->json('data'));

        // Ambil index dengan tujuan_kelulusan=bekerja
        $kerjaResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/alumni?unit_id={$unit->id}&tujuan_kelulusan=bekerja&all=true");

        $kerjaResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertCount(1, $kerjaResponse->json('data'));

        // Ambil index dengan tujuan_kelulusan=pesantren
        $pesantrenResponse = $this->actingAs($user, 'sanctum')
            ->getJson("/api/alumni?unit_id={$unit->id}&tujuan_kelulusan=pesantren&all=true");

        $pesantrenResponse->assertOk()
            ->assertJsonPath('total', 1);
        $this->assertCount(1, $pesantrenResponse->json('data'));

        // Verifikasi total kategori sama persis dengan total alumni
        $this->assertEquals(
            $statsResponse->json('data.total_alumni'),
            $kuliahResponse->json('total') + $kerjaResponse->json('total') + $pesantrenResponse->json('total')
        );
    }
}
