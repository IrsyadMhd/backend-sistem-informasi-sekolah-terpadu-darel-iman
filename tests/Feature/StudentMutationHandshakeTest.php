<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentMutationHandshakeTest extends TestCase
{
    use RefreshDatabase;

    protected EducationUnit $unitA;
    protected EducationUnit $unitB;
    protected AcademicYear $academicYear;
    protected Semester $semester;
    protected Kelas $kelasA;
    protected Kelas $kelasB;
    protected Kelas $kelasB2;
    protected Student $student;
    protected User $tuUserA;
    protected User $tuUserB;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $tuRole = Role::firstOrCreate(['name' => 'Tata Usaha', 'guard_name' => 'web']);
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->unitA = EducationUnit::create([
            'code' => 'UNIT-SDIT',
            'name' => 'SDIT 1 Dar el-Iman',
            'is_active' => true,
        ]);

        $this->unitB = EducationUnit::create([
            'code' => 'UNIT-SMPIT',
            'name' => 'SMPIT Dar el-Iman',
            'is_active' => true,
        ]);

        $this->academicYear = AcademicYear::create([
            'name' => '2024/2025',
            'start_date' => '2024-07-01',
            'end_date' => '2025-06-30',
            'is_active' => true,
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Semester Ganjil',
            'sequence' => 1,
            'start_date' => '2024-07-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
        ]);

        $this->kelasA = Kelas::create([
            'kode_kelas' => 'K-SDIT-6A',
            'nama_kelas' => 'Kelas 6A',
            'jenjang' => 'SDIT',
            'tingkat' => '6',
            'unit_pendidikan_id' => $this->unitA->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'status' => 'Aktif',
        ]);

        $this->kelasB = Kelas::create([
            'kode_kelas' => 'K-SMPIT-7A',
            'nama_kelas' => 'Kelas 7A',
            'jenjang' => 'SMPIT',
            'tingkat' => '7',
            'unit_pendidikan_id' => $this->unitB->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'status' => 'Aktif',
        ]);

        $this->kelasB2 = Kelas::create([
            'kode_kelas' => 'K-SMPIT-7B',
            'nama_kelas' => 'Kelas 7B',
            'jenjang' => 'SMPIT',
            'tingkat' => '7',
            'unit_pendidikan_id' => $this->unitB->id,
            'tahun_ajaran_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'status' => 'Aktif',
        ]);

        $this->student = Student::create([
            'nis' => 'SD-1001',
            'nisn' => '0012345678',
            'full_name' => 'Zaid bin Tsabit',
            'gender' => 'male',
            'unit_id' => $this->unitA->id,
            'kelas_id' => $this->kelasA->id,
            'is_active' => true,
        ]);

        // TU Unit A (Asal)
        $this->tuUserA = User::factory()->create(['email' => 'tu-sdit@dareliman.test']);
        $this->tuUserA->assignRole($tuRole);
        Employee::create([
            'user_id' => $this->tuUserA->id,
            'unit_id' => $this->unitA->id,
            'nama_lengkap' => 'Staff TU SDIT',
            'niy' => 'TU-SDIT-01',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ]);

        // TU Unit B (Tujuan)
        $this->tuUserB = User::factory()->create(['email' => 'tu-smpit@dareliman.test']);
        $this->tuUserB->assignRole($tuRole);
        Employee::create([
            'user_id' => $this->tuUserB->id,
            'unit_id' => $this->unitB->id,
            'nama_lengkap' => 'Staff TU SMPIT',
            'niy' => 'TU-SMPIT-01',
            'jenis_kelamin' => 'L',
            'status' => 'Aktif',
        ]);

        // Super Admin (Yayasan)
        $this->superAdmin = User::factory()->create(['email' => 'superadmin@dareliman.test']);
        $this->superAdmin->assignRole($superAdminRole);
    }

    /** 1. Pengajuan mutasi menggunakan payload kanonis berhasil */
    public function test_store_mutation_with_canonical_payload(): void
    {
        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'target_unit_id' => $this->unitB->id,
                'jenis_mutasi' => 'pindah_unit_internal',
                'alasan' => 'Melanjutkan jenjang pendidikan SMPIT',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('student_mutations', [
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
        ]);

        $this->student->refresh();
        $this->assertEquals('mutasi_proses', $this->student->metadata['status_siswa'] ?? null);
    }

    /** 2. Pengajuan mutasi menggunakan payload alias legacy berhasil dinormalisasi */
    public function test_store_mutation_with_legacy_aliases(): void
    {
        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'unit_tujuan_id' => $this->unitB->id,
                'jenis_mutasi' => 'pindah_unit',
                'catatan' => 'Pindah unit internal via mobile app',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('student_mutations', [
            'student_id' => $this->student->id,
            'unit_tujuan_id' => $this->unitB->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'alasan' => 'Pindah unit internal via mobile app',
            'status' => 'menunggu_persetujuan',
        ]);
    }

    /** 3. Pengajuan mutasi ditolak jika target_unit_id dan unit_tujuan_id berkonflik nilai */
    public function test_store_mutation_rejects_conflicting_target_unit_parameters(): void
    {
        $otherUnit = EducationUnit::create([
            'code' => 'UNIT-SMAIT',
            'name' => 'SMAIT Dar el-Iman',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'target_unit_id' => $this->unitB->id,
                'unit_tujuan_id' => $otherUnit->id,
                'jenis_mutasi' => 'pindah_unit_internal',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_unit_id'])
            ->assertJsonPath('message', 'Konflik parameter: target_unit_id dan unit_tujuan_id memiliki nilai berbeda.');
    }

    /** 4. Pengajuan mutasi ditolak jika jenis_mutasi tidak valid */
    public function test_store_mutation_rejects_invalid_mutation_type(): void
    {
        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'target_unit_id' => $this->unitB->id,
                'jenis_mutasi' => 'teleportasi_santri',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['jenis_mutasi']);
    }

    /** 5. Pengajuan mutasi ditolak jika siswa sudah memiliki permohonan pending (mencegah duplikat) */
    public function test_store_mutation_rejects_duplicate_pending_request(): void
    {
        StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pengajuan awal',
        ]);

        $response = $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'target_unit_id' => $this->unitB->id,
                'jenis_mutasi' => 'pindah_unit_internal',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Siswa ini masih memiliki permohonan mutasi yang sedang diproses oleh unit tujuan.');
    }

    /** 6. Persetujuan mutasi dengan payload kanonis kelas_id berhasil */
    public function test_approve_mutation_with_canonical_payload(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_id' => $this->kelasB->id,
                'catatan' => 'Diterima di kelas 7A',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $mutation->refresh();
        $this->assertEquals('disetujui', $mutation->status);
        $this->assertEquals($this->kelasB->id, $mutation->kelas_tujuan_id);

        $this->student->refresh();
        $this->assertEquals($this->unitB->id, $this->student->unit_id);
        $this->assertEquals($this->kelasB->id, $this->student->kelas_id);
        $this->assertEquals('aktif', $this->student->metadata['status_siswa'] ?? null);
        $this->assertNotEmpty($this->student->metadata['riwayat_mutasi'] ?? []);
    }

    /** 7. Persetujuan mutasi dengan alias legacy kelas_tujuan_id berhasil */
    public function test_approve_mutation_with_legacy_alias(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_tujuan_id' => $this->kelasB->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $mutation->refresh();
        $this->assertEquals('disetujui', $mutation->status);
        $this->assertEquals($this->kelasB->id, $mutation->kelas_tujuan_id);
    }

    /** 8. Persetujuan ditolak jika kelas_id dan kelas_tujuan_id berkonflik nilai */
    public function test_approve_mutation_rejects_conflicting_class_parameters(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_id' => $this->kelasB->id,
                'kelas_tujuan_id' => $this->kelasB2->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['kelas_id'])
            ->assertJsonPath('message', 'Konflik parameter: kelas_id dan kelas_tujuan_id memiliki nilai berbeda.');
    }

    /** 9. Persetujuan dengan fallback client legacy yang tidak mengirim kelas sama sekali */
    public function test_approve_mutation_legacy_fallback_when_class_omitted(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        // Legacy mobile build sends status only
        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $mutation->refresh();
        $this->assertEquals('disetujui', $mutation->status);
        $this->assertNotNull($mutation->kelas_tujuan_id);
    }

    /** 10. Persetujuan ditolak jika kelas tujuan tidak berada di unit pendidikan tujuan */
    public function test_approve_mutation_rejects_class_not_in_target_unit(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_id' => $this->kelasA->id, // Kelas unit A, bukan unit B
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Kelas yang dipilih tidak sesuai dengan unit pendidikan tujuan.');
    }

    /** 11. Persetujuan ditolak jika status mutasi bukan menunggu_persetujuan (transisi ilegal) */
    public function test_approve_mutation_rejects_illegal_state_transition(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'kelas_tujuan_id' => $this->kelasB->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'disetujui',
            'alasan' => 'Sudah disetujui sebelumnya',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_id' => $this->kelasB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', "Pengajuan mutasi ini tidak dapat disetujui karena statusnya sudah 'disetujui'.");
    }

    /** 12. Penolakan mutasi dengan payload kanonis catatan_penolakan berhasil */
    public function test_reject_mutation_with_canonical_payload(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/reject", [
                'catatan_penolakan' => 'Kuota jenjang kelas 7 sudah penuh',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $mutation->refresh();
        $this->assertEquals('ditolak', $mutation->status);
        $this->assertEquals('Kuota jenjang kelas 7 sudah penuh', $mutation->catatan_penolakan);

        $this->student->refresh();
        $this->assertEquals($this->unitA->id, $this->student->unit_id);
        $this->assertEquals($this->kelasA->id, $this->student->kelas_id);
        $this->assertEquals('aktif', $this->student->metadata['status_siswa'] ?? null);
        $this->assertEquals('Ditolak', $this->student->metadata['mutasi_status'] ?? null);
    }

    /** 13. Penolakan mutasi dengan masing-masing alias legacy berhasil dinormalisasi */
    public function test_reject_mutation_with_each_legacy_alias(): void
    {
        // Alias 1: alasan_penolakan (web-dashboard & mobile DataManagement)
        $mut1 = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah 1',
        ]);

        $resp1 = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mut1->id}/reject", [
                'alasan_penolakan' => 'Alasan via web dashboard',
            ]);
        $resp1->assertStatus(200);
        $mut1->refresh();
        $this->assertEquals('Alasan via web dashboard', $mut1->catatan_penolakan);

        // Alias 2: catatan (mobile MutasiSiswaScreen legacy)
        $mut2 = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah 2',
        ]);

        $resp2 = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mut2->id}/reject", [
                'catatan' => 'Catatan via mobile app',
            ]);
        $resp2->assertStatus(200);
        $mut2->refresh();
        $this->assertEquals('Catatan via mobile app', $mut2->catatan_penolakan);

        // Alias 3: alasan
        $mut3 = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah 3',
        ]);

        $resp3 = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mut3->id}/reject", [
                'alasan' => 'Alasan ringkas penolakan',
            ]);
        $resp3->assertStatus(200);
        $mut3->refresh();
        $this->assertEquals('Alasan ringkas penolakan', $mut3->catatan_penolakan);
    }

    /** 14. Penolakan ditolak jika terdapat konflik nilai antar alias */
    public function test_reject_mutation_rejects_conflicting_rejection_reasons(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/reject", [
                'catatan_penolakan' => 'Alasan versi A',
                'alasan_penolakan' => 'Alasan versi B yang berbeda',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['catatan_penolakan'])
            ->assertJsonPath('message', 'Konflik parameter: field alasan penolakan dikirim dengan nilai yang berbeda.');
    }

    /** 15. Penolakan ditolak jika alasan penolakan kosong */
    public function test_reject_mutation_requires_reason(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        $response = $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['catatan_penolakan']);
    }

    /** 16. Otorisasi: guest ditolak 401 dan TU lintas unit yang tidak berwenang ditolak 403 */
    public function test_unauthenticated_and_cross_unit_unauthorized_access(): void
    {
        $mutation = StudentMutation::create([
            'student_id' => $this->student->id,
            'unit_asal_id' => $this->unitA->id,
            'unit_tujuan_id' => $this->unitB->id,
            'kelas_asal_id' => $this->kelasA->id,
            'jenis_mutasi' => 'pindah_unit_internal',
            'status' => 'menunggu_persetujuan',
            'alasan' => 'Pindah jenjang',
        ]);

        // Unauthenticated (Guest)
        $this->postJson("/api/student-mutations/{$mutation->id}/approve", [
            'kelas_id' => $this->kelasB->id,
        ])->assertStatus(401);

        // TU Unit A (asal) tidak berhak approve mutasi yang ditujukan ke Unit B
        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/approve", [
                'kelas_id' => $this->kelasB->id,
            ])->assertStatus(403);

        // TU Unit A tidak berhak reject mutasi yang ditujukan ke Unit B
        $this->actingAs($this->tuUserA, 'sanctum')
            ->postJson("/api/student-mutations/{$mutation->id}/reject", [
                'catatan_penolakan' => 'Mencoba menolak bukan unitnya',
            ])->assertStatus(403);

        // TU Unit B tidak berhak mengajukan mutasi untuk siswa yang berada di Unit A
        $this->actingAs($this->tuUserB, 'sanctum')
            ->postJson('/api/student-mutations', [
                'student_id' => $this->student->id,
                'target_unit_id' => $this->unitB->id,
                'jenis_mutasi' => 'pindah_unit_internal',
            ])->assertStatus(403);
    }
}
