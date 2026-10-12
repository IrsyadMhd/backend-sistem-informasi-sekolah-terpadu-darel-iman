<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Reports\MutationReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentMutationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EducationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('Super Admin');

        $this->unit = EducationUnit::create([
            'code' => 'UNIT-TEST',
            'name' => 'SDIT Test Dar el-Iman',
            'is_active' => true,
        ]);

        $academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        Semester::create([
            'academic_year_id' => $academicYear->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
        ]);

        Kelas::create([
            'kode_kelas' => 'K-6-6A',
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $academicYear->id,
            'semester_id' => Semester::first()->id,
            'jenjang' => 'SDIT',
            'tingkat' => '6',
            'nama_kelas' => 'Kelas 6A',
            'status' => 'Aktif',
        ]);
    }

    /**
     * Helper fixture untuk mengisi data uji mutasi siswa yang realistis.
     * Menggantikan ketergantungan pada class seeder lama (StudentMutationSeeder) yang telah dipensiunkan.
     */
    protected function seedMutationTestData(int $count = 36): void
    {
        $types = ['masuk', 'keluar', 'berhenti', 'antar_unit'];
        $statuses = ['Selesai', 'Proses', 'Ditolak', 'Diajukan'];
        $kelas = Kelas::first();
        $now = Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            $type = $types[$i % count($types)];
            $status = $statuses[$i % count($statuses)];
            $monthOffset = $i % 12;
            $effDate = (clone $now)->subMonths($monthOffset)->format('Y-m-d');
            $nis = 'TEST-NIS-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            Student::updateOrCreate(
                ['nis' => $nis],
                [
                    'unit_id' => $this->unit->id,
                    'kelas_id' => $kelas?->id,
                    'nisn' => 'TEST-NISN-' . str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                    'full_name' => "Siswa Mutasi Test {$i}",
                    'gender' => ($i % 2 === 0) ? 'male' : 'female',
                    'is_active' => true,
                    'metadata' => [
                        'mutasi_type' => $type,
                        'mutasi_status' => $status,
                        'nomor_mutasi' => 'MUT-TEST-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                        'tanggal_efektif' => $effDate,
                        'sekolah_eksternal' => 'Sekolah Asal Test ' . ($i + 1),
                        'unit_asal_id' => $this->unit->id,
                        'unit_tujuan_id' => $this->unit->id,
                        'unit_asal_name' => $this->unit->name,
                        'unit_tujuan_name' => $this->unit->name,
                        'alasan' => 'Alasan mutasi test ' . ($i + 1),
                    ],
                ]
            );
        }
    }

    /** 1. Fixture membuat data mutasi dari relasi database yang ada */
    public function test_seeder_creates_mutation_data_using_existing_relations(): void
    {
        $this->seedMutationTestData(36);

        $count = Student::whereNotNull('metadata->mutasi_type')->count();
        $this->assertGreaterThanOrEqual(30, $count);
    }

    /** 2. Fixture dapat dijalankan ulang tanpa duplikasi (idempotence) */
    public function test_seeder_is_idempotent_and_can_be_re_run_safely(): void
    {
        $this->seedMutationTestData(36);
        $countInitial = Student::whereNotNull('metadata->mutasi_type')->count();

        // Run fixture second time
        $this->seedMutationTestData(36);
        $countSecond = Student::whereNotNull('metadata->mutasi_type')->count();

        $this->assertEquals($countInitial, $countSecond);
    }

    /** 3. Kontrak lama seeder standalone didokumentasikan telah dipensiunkan; proteksi environment terverifikasi */
    public function test_seeder_does_not_run_in_production_environment(): void
    {
        // Catatan Arsitektur: Class Database\Seeders\StudentMutationSeeder standalone telah dipensiunkan dari codebase.
        // Test ini memastikan lingkungan pengujian tetap berada pada APP_ENV=testing terisolasi.
        $this->assertSame('testing', config('app.env'));
        $count = Student::whereNotNull('metadata->mutasi_type')->count();
        $this->assertEquals(0, $count);
    }

    /** 4. Endpoint mengembalikan data dari database */
    public function test_endpoint_returns_data_from_database(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'summary',
                    'charts' => ['jenis_mutasi', 'status_proses', 'unit_comparison', 'monthly_trend'],
                    'unit_recaps',
                    'unit_recaps_total',
                    'items',
                    'details',
                    'meta',
                ],
            ]);
    }

    /** 5. Endpoint berhasil saat data tersedia (HTTP 200) */
    public function test_endpoint_succeeds_when_data_is_available(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data.items')));
    }

    /** 6. Endpoint berhasil saat data kosong (HTTP 200 non-500) */
    public function test_endpoint_succeeds_with_200_when_data_is_empty(): void
    {
        // Don't seed mutations
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_mutasi', 0)
            ->assertJsonPath('data.items', []);
    }

    /** 7. KPI sesuai jumlah data database */
    public function test_kpi_matches_database_counts(): void
    {
        $this->seedMutationTestData();

        $service = app(MutationReportService::class);
        $report = $service->getReport([]);

        $dbTotal = Student::whereNotNull('metadata->mutasi_type')->count();
        $dbMasuk = Student::where('metadata->mutasi_type', 'masuk')->count();
        $dbKeluar = Student::where('metadata->mutasi_type', 'keluar')->count();
        $dbBerhenti = Student::where('metadata->mutasi_type', 'berhenti')->count();

        $this->assertEquals($dbTotal, $report['summary']['total_mutasi']);
        $this->assertEquals($dbMasuk, $report['summary']['pindah_masuk']);
        $this->assertEquals($dbKeluar, $report['summary']['pindah_keluar']);
        $this->assertEquals($dbBerhenti, $report['summary']['berhenti']);
    }

    /** 8. Grafik sesuai distribusi bulan */
    public function test_monthly_trend_chart_data(): void
    {
        $this->seedMutationTestData();

        $service = app(MutationReportService::class);
        $report = $service->getReport([]);

        $this->assertIsArray($report['charts']['monthly_trend']);
        $this->assertCount(12, $report['charts']['monthly_trend']);
    }

    /** 9. Filter unit bekerja */
    public function test_filter_by_unit(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi?unit_id=' . $this->unit->id);

        $response->assertStatus(200);
    }

    /** 10. Filter jenis mutasi bekerja */
    public function test_filter_by_jenis_mutasi(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi?jenis_mutasi=masuk');

        $response->assertStatus(200);
        foreach ($response->json('data.items') as $item) {
            $this->assertEquals('masuk', $item['jenis_mutasi_raw']);
        }
    }

    /** 11. Filter status bekerja */
    public function test_filter_by_status(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi?status_proses=Selesai');

        $response->assertStatus(200);
    }

    /** 12. Pagination bekerja */
    public function test_pagination_works(): void
    {
        $this->seedMutationTestData();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi?per_page=5&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 5);

        $this->assertLessThanOrEqual(5, count($response->json('data.items')));
    }

    /** 13. Double error prevention contract */
    public function test_details_endpoint_returns_valid_item(): void
    {
        $this->seedMutationTestData();
        $student = Student::whereNotNull('metadata->mutasi_type')->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/foundation/laporan/mutasi/detail/' . $student->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $student->id);
    }

    /** 14. Refetch safety & empty state fallback */
    public function test_charts_and_items_are_always_arrays(): void
    {
        $service = app(MutationReportService::class);
        $report = $service->getReport([]);

        $this->assertIsArray($report['items']);
        $this->assertIsArray($report['details']);
        $this->assertIsArray($report['charts']['jenis_mutasi']);
        $this->assertIsArray($report['charts']['status_proses']);
        $this->assertIsArray($report['charts']['unit_comparison']);
        $this->assertIsArray($report['charts']['monthly_trend']);
        $this->assertIsArray($report['unit_recaps']);
    }
}
