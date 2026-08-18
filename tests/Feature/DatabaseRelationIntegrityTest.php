<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\JenisUnitPendidikan;
use App\Models\Kelas;
use App\Models\LmsModulAjar;
use App\Models\LmsPresensi;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TujuanPembelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DatabaseRelationIntegrityTest
 *
 * Memverifikasi integritas relasi antar tabel setelah audit & refactor.
 * Test ini mencakup:
 * - Relasi FK yang benar antar model
 * - Konsistensi UUID vs integer PK di JenisUnitPendidikan
 * - Student kelas_id backfill dan relasi baru kelas()
 * - Kelas::siswa() via kelas_id
 * - LmsPresensi student() alias relation
 * - Student photo accessor
 * - tbl_kelas composite unique (kode kelas sama di unit berbeda)
 */
class DatabaseRelationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: buat EducationUnit tanpa factory (untuk portabilitas)
    // ─────────────────────────────────────────────────────────────────────────

    private function makeUnit(string $code, string $level = 'SD'): EducationUnit
    {
        return EducationUnit::create([
            'code'      => $code,
            'name'      => "Unit {$code}",
            'level'     => $level,
            'is_active' => true,
        ]);
    }

    private function makeYear(string $name = '2025/2026', bool $active = true): AcademicYear
    {
        return AcademicYear::create([
            'name'       => $name,
            'start_date' => '2025-07-01',
            'end_date'   => '2026-06-30',
            'is_active'  => $active,
        ]);
    }

    private function makeSemester(string $yearId, string $name = 'Ganjil', int $seq = 1): Semester
    {
        return Semester::create([
            'academic_year_id' => $yearId,
            'name'             => $name,
            'sequence'         => $seq,
            'start_date'       => '2025-07-01',
            'end_date'         => '2025-12-31',
            'is_active'        => true,
        ]);
    }

    private function makeKelas(string $unitId, string $yearId, string $semId, string $kode, string $jenjang = 'SD', string $tingkat = '1'): Kelas
    {
        return Kelas::create([
            'unit_pendidikan_id' => $unitId,
            'tahun_ajaran_id'    => $yearId,
            'semester_id'        => $semId,
            'jenjang'            => $jenjang,
            'tingkat'            => $tingkat,
            'kode_kelas'         => $kode,
            'nama_kelas'         => "Kelas {$kode}",
            'kapasitas'          => 30,
            'status'             => 'Aktif',
        ]);
    }

    /** Buat student dengan NIS unik menggunakan uniqid() */
    private function makeStudent(string $kelasId, string $unitId, string $name, string $gender = 'L'): Student
    {
        return Student::create([
            'kelas_id'  => $kelasId, // FK baru ke tbl_kelas
            // JANGAN isi class_id karena FK-nya menunjuk ke tabel `classes` (lama),
            // bukan ke `tbl_kelas`. Mengisi class_id dengan UUID dari tbl_kelas akan
            // menyebabkan FK violation di SQLite dev environment.
            'unit_id'   => $unitId,
            'nis'       => 'NIS-' . uniqid(),
            'full_name' => $name,
            'gender'    => $gender,
            'is_active' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. JenisUnitPendidikan Primary Key
    // ─────────────────────────────────────────────────────────────────────────

    public function test_jenis_unit_pendidikan_has_integer_primary_key(): void
    {
        $jenis = JenisUnitPendidikan::create([
            'kode_jenis' => 'TEST-001',
            'nama_jenis' => 'Unit Test Jenis',
            'singkatan'  => 'UTJ',
            'jenjang'    => 'SD',
            'status'     => true,
        ]);

        // Primary key harus integer, bukan UUID
        $this->assertIsInt($jenis->id);
        $this->assertGreaterThan(0, $jenis->id);

        // Kolom uuid harus auto-generated sebagai UUID string
        $this->assertNotEmpty($jenis->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $jenis->uuid
        );
    }

    public function test_jenis_unit_pendidikan_uuid_is_unique_across_records(): void
    {
        $jenis1 = JenisUnitPendidikan::create(['kode_jenis' => 'T1', 'nama_jenis' => 'Test 1', 'singkatan' => 'T1', 'jenjang' => 'SD']);
        $jenis2 = JenisUnitPendidikan::create(['kode_jenis' => 'T2', 'nama_jenis' => 'Test 2', 'singkatan' => 'T2', 'jenjang' => 'SMP']);

        $this->assertNotEquals($jenis1->uuid, $jenis2->uuid);
        $this->assertNotEquals($jenis1->id, $jenis2->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Student → Kelas Relation (CRITICAL FIX)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_student_has_kelas_relation_to_tbl_kelas(): void
    {
        $unit  = $this->makeUnit('SDI-01');
        $year  = $this->makeYear('2025/2026-A', true);
        $sem   = $this->makeSemester($year->id, 'Ganjil');
        $kelas = $this->makeKelas($unit->id, $year->id, $sem->id, '1-A-T1');

        $siswa = $this->makeStudent($kelas->id, $unit->id, 'Siswa Test');

        // Relasi kelas() harus resolve ke tbl_kelas (via Kelas model)
        $this->assertInstanceOf(Kelas::class, $siswa->kelas);
        $this->assertEquals($kelas->id, $siswa->kelas->id);
        $this->assertEquals("Kelas 1-A-T1", $siswa->kelas->nama_kelas);
    }

    public function test_student_fillable_includes_kelas_id_and_photo(): void
    {
        $student  = new Student();
        $fillable = $student->getFillable();

        $this->assertContains('kelas_id', $fillable);
        $this->assertContains('photo', $fillable);
        $this->assertContains('photo_thumb', $fillable);
    }

    public function test_student_scope_by_class_searches_kelas_id(): void
    {
        $unit  = $this->makeUnit('SDI-02');
        $year  = $this->makeYear('2025/2026-B', false);
        $sem   = $this->makeSemester($year->id, 'Genap', 2);
        $kelas = $this->makeKelas($unit->id, $year->id, $sem->id, '2-B-SCOPE');

        $this->makeStudent($kelas->id, $unit->id, 'Siswa Scope', 'P');

        // Scope harus menemukan siswa via kelas_id
        $result = Student::byClass($kelas->id)->get();
        $this->assertCount(1, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. Kelas → Siswa Relation (via kelas_id primer)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kelas_siswa_relation_uses_kelas_id(): void
    {
        $unit  = $this->makeUnit('SMPI-01', 'SMP');
        $year  = $this->makeYear('2025/2026-C', false);
        $sem   = $this->makeSemester($year->id, 'Ganjil');
        $kelas = $this->makeKelas($unit->id, $year->id, $sem->id, '7-A-SMP', 'SMP', '7');

        $this->makeStudent($kelas->id, $unit->id, 'Murid Satu', 'L');
        $this->makeStudent($kelas->id, $unit->id, 'Murid Dua', 'P');


        // kelas->siswa() harus mengambil via kelas_id
        $kelas->refresh();
        $this->assertCount(2, $kelas->siswa);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. LmsPresensi student() alias
    // ─────────────────────────────────────────────────────────────────────────

    public function test_lms_presensi_has_student_alias_relation(): void
    {
        $presensi = new LmsPresensi();

        $this->assertTrue(method_exists($presensi, 'siswa'));
        $this->assertTrue(method_exists($presensi, 'student'));

        // Kedua relasi harus menunjuk ke FK yang sama (siswa_id)
        $siswaRelation   = $presensi->siswa();
        $studentRelation = $presensi->student();

        $this->assertEquals($siswaRelation->getForeignKeyName(), $studentRelation->getForeignKeyName());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Student photo accessor
    // ─────────────────────────────────────────────────────────────────────────

    public function test_student_photo_accessor_returns_photo_column_first(): void
    {
        $student = new Student(['photo' => '/uploads/foto.jpg', 'metadata' => ['photo' => '/old/foto.jpg']]);
        $this->assertEquals('/uploads/foto.jpg', $student->photo);
    }

    public function test_student_photo_accessor_falls_back_to_metadata(): void
    {
        $student = new Student(['photo' => null, 'metadata' => ['photo' => '/meta/foto.jpg']]);
        $this->assertEquals('/meta/foto.jpg', $student->photo);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Education Unit → Jenis Unit Relation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_education_unit_has_jenis_unit_relation(): void
    {
        $jenis = JenisUnitPendidikan::create([
            'kode_jenis' => 'EU-REL',
            'nama_jenis' => 'EU Relation Test',
            'singkatan'  => 'EUR',
            'jenjang'    => 'SMP',
            'status'     => true,
        ]);

        $unit = EducationUnit::create([
            'jenis_unit_id' => $jenis->uuid, // FK ke uuid kolom, bukan integer id
            'code'          => 'EU-T1',
            'name'          => 'Education Unit Test 1',
            'level'         => 'SMP',
            'is_active'     => true,
        ]);

        $this->assertInstanceOf(JenisUnitPendidikan::class, $unit->jenisUnit);
        $this->assertEquals($jenis->id, $unit->jenisUnit->id);
        $this->assertEquals('EU-REL', $unit->jenisUnit->kode_jenis);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. tbl_kelas composite unique — kode sama di unit berbeda boleh
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kelas_kode_can_be_same_across_different_units(): void
    {
        $unit1 = $this->makeUnit('UNIT-A', 'SD');
        $unit2 = $this->makeUnit('UNIT-B', 'SMP');
        $year  = $this->makeYear('2025/2026-D', false);
        $sem   = $this->makeSemester($year->id, 'Ganjil');

        // Kode sama '1-A' di unit berbeda — harus berhasil (tidak duplicate key error)
        $kelas1 = $this->makeKelas($unit1->id, $year->id, $sem->id, '1-A', 'SD', '1');
        $kelas2 = $this->makeKelas($unit2->id, $year->id, $sem->id, '1-A', 'SMP', '7');

        $this->assertNotEquals($kelas1->id, $kelas2->id);
        $this->assertEquals('1-A', $kelas1->kode_kelas);
        $this->assertEquals('1-A', $kelas2->kode_kelas);
        $this->assertNotEquals($kelas1->unit_pendidikan_id, $kelas2->unit_pendidikan_id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Idempotent create pattern (seeder compatibility)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_jenis_unit_pendidikan_can_be_created_with_first_or_create(): void
    {
        $data = ['kode_jenis' => 'IDEM-001', 'nama_jenis' => 'Idempotent Test', 'singkatan' => 'IDM', 'jenjang' => 'SD'];

        $first  = JenisUnitPendidikan::firstOrCreate(['kode_jenis' => 'IDEM-001'], $data);
        $second = JenisUnitPendidikan::firstOrCreate(['kode_jenis' => 'IDEM-001'], $data);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, JenisUnitPendidikan::where('kode_jenis', 'IDEM-001')->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. LmsModulAjar memiliki relasi cps() dan tps() pivot
    // ─────────────────────────────────────────────────────────────────────────

    public function test_lms_modul_ajar_has_cps_and_tps_many_to_many_relations(): void
    {
        $modulAjar = new LmsModulAjar();

        $this->assertTrue(method_exists($modulAjar, 'cps'));
        $this->assertTrue(method_exists($modulAjar, 'tps'));
        $this->assertTrue(method_exists($modulAjar, 'capaianPembelajaran'));
        $this->assertTrue(method_exists($modulAjar, 'tujuanPembelajaran'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. Kelas memiliki relasi jadwal, modulAjar, modulSemester
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kelas_model_has_new_relations(): void
    {
        $kelas = new Kelas();

        $this->assertTrue(method_exists($kelas, 'siswa'));
        $this->assertTrue(method_exists($kelas, 'siswaLegacy'));
        $this->assertTrue(method_exists($kelas, 'jadwal'));
        $this->assertTrue(method_exists($kelas, 'modulAjar'));
        $this->assertTrue(method_exists($kelas, 'modulSemester'));
    }
}
