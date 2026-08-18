<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Repositories\Eloquent\LmsUjianRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SESI 12 — CBT AUTO-TIMEOUT SCHEDULER.
 *
 * Sesi CBT yang melewati batas waktu (waktu_mulai + durasi_menit*60) harus
 * disubmit otomatis berstatus 'timeout', jawaban objektif dinilai, esai
 * pending review manual, dan prosesnya idempoten (tidak menggandakan nilai).
 */
class CbtAutoTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private LmsUjian $ujian;

    private Student $siswa;

    private LmsUjianSesi $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        $ay = AcademicYear::create(['name' => '2026/2027', 'is_active' => true]);
        $sem = Semester::create(['academic_year_id' => $ay->id, 'name' => 'Ganjil', 'sequence' => 1, 'is_active' => true]);
        $unit = EducationUnit::create(['name' => 'Unit CBT', 'code' => 'UCBT', 'is_active' => true]);
        $guruEmp = Employee::create([
            'unit_id' => $unit->id, 'education_unit_id' => $unit->id,
            'nama_lengkap' => 'Guru CBT', 'niy' => 'CBT-0001', 'status' => 'Aktif', 'is_active' => true,
        ]);
        $kelas = Kelas::create([
            'unit_pendidikan_id' => $unit->id, 'tahun_ajaran_id' => $ay->id, 'semester_id' => $sem->id,
            'jenjang' => 'SMP', 'tingkat' => 8, 'kode_kelas' => 'CBT-8A', 'nama_kelas' => '8A CBT',
            'wali_kelas_id' => $guruEmp->id, 'status' => 'Aktif',
        ]);
        $this->siswa = Student::create([
            'education_unit_id' => $unit->id, 'kelas_id' => $kelas->id,
            'full_name' => 'Siswa CBT', 'nis' => 'CBT-1001', 'gender' => 'male', 'is_active' => true,
        ]);

        $kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-CBT', 'nama_kurikulum' => 'Kurikulum CBT', 'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $unit->id, 'jenjang' => 'SMP',
            'tahun_ajaran_id' => $ay->id, 'tanggal_mulai' => '2025-07-01', 'status' => true,
        ]);
        $subject = Subject::create(['name' => 'Matematika', 'code' => 'MTK-CBT', 'education_unit_id' => $unit->id, 'is_active' => true]);

        $kisi = LmsKisiKisi::create([
            'kurikulum_id' => $kurikulum->id, 'mata_pelajaran_id' => $subject->id,
            'kelas_id' => $kelas->id, 'semester_id' => $sem->id, 'tahun_ajaran_id' => $ay->id,
            'guru_id' => $guruEmp->id, 'judul_kisi' => 'Kisi CBT', 'jenis_ujian' => 'UH',
            'jumlah_soal' => 2, 'alokasi_waktu_menit' => 30, 'status' => true,
        ]);

        $soalPg = LmsBankSoal::create([
            'kisi_kisi_id' => $kisi->id, 'mata_pelajaran_id' => $subject->id,
            'kode_soal' => 'CBT-PG-1', 'pertanyaan' => '2 + 2 = ?', 'tipe_soal' => 'pg',
            'opsi_a' => '3', 'opsi_b' => '4', 'kunci_jawaban' => 'B', 'poin' => 50, 'status' => true,
        ]);
        LmsBankSoal::create([
            'kisi_kisi_id' => $kisi->id, 'mata_pelajaran_id' => $subject->id,
            'kode_soal' => 'CBT-ESAI-1', 'pertanyaan' => 'Jelaskan variabel!', 'tipe_soal' => 'esai',
            'poin' => 50, 'status' => true,
        ]);

        $this->ujian = LmsUjian::create([
            'kisi_kisi_id' => $kisi->id, 'kelas_id' => $kelas->id, 'semester_id' => $sem->id,
            'guru_id' => $guruEmp->id, 'judul_ujian' => 'Ujian CBT Timeout',
            'waktu_mulai' => now()->subMinutes(5), 'waktu_selesai' => now()->addHours(2),
            'durasi_menit' => 1, 'nilai_kkm' => 70, 'tampilkan_nilai_langsung' => true, 'status' => 'published',
        ]);

        $this->sesi = LmsUjianSesi::create([
            'ujian_id' => $this->ujian->id, 'siswa_id' => $this->siswa->id,
            'waktu_mulai' => now(), 'status' => 'proses',
        ]);

        $repo = app(LmsUjianRepository::class);
        $repo->saveJawabanSesi($this->sesi->id, [
            ['soal_id' => $soalPg->id, 'jawaban_dipilih' => 'B'],
            ['soal_id' => LmsBankSoal::where('kode_soal', 'CBT-ESAI-1')->first()->id, 'jawaban_esai' => 'Variabel adalah wadah data.'],
        ]);
    }

    private function repo(): LmsUjianRepository
    {
        return app(LmsUjianRepository::class);
    }

    /** Paksa sesi melewati deadline (durasi_menit=1, mulai 5 menit lalu). */
    private function expireSesi(): void
    {
        $this->sesi->update(['waktu_mulai' => now()->subMinutes(5)]);
    }

    public function test_expired_processing_exam_is_auto_submitted(): void
    {
        $this->expireSesi();
        $result = $this->repo()->autoSubmitExpiredSessions();

        $this->assertSame(1, $result['expired']);
        $this->assertSame(1, $result['submitted']);

        $sesi = $this->sesi->fresh(['jawaban']);
        $this->assertSame('timeout', $sesi->status);
        $this->assertNotNull($sesi->waktu_selesai);
        $this->assertSame(50.0, (float) $sesi->nilai_raw);
        $this->assertSame(50.0, (float) $sesi->nilai_final);
        $this->assertSame(1, $sesi->jumlah_benar);
    }

    public function test_timeout_job_is_idempotent(): void
    {
        $this->expireSesi();
        $first = $this->repo()->autoSubmitExpiredSessions();
        $this->assertSame(1, $first['submitted']);

        $second = $this->repo()->autoSubmitExpiredSessions();
        $this->assertSame(0, $second['expired']);
        $this->assertSame(0, $second['submitted']);

        $this->assertSame(50.0, (float) $this->sesi->fresh()->nilai_final);
        $this->assertSame(1, $this->sesi->fresh(['jawaban'])->jawaban->where('is_correct', true)->count());
    }

    public function test_manual_submit_and_timeout_do_not_duplicate(): void
    {
        $this->repo()->finalizeSesiUjian($this->sesi->id);
        $this->assertSame('selesai', $this->sesi->fresh()->status);

        $result = $this->repo()->autoSubmitExpiredSessions();
        $this->assertSame(0, $result['expired']);
        $this->assertSame(0, $result['submitted']);

        $this->assertSame(50.0, (float) $this->sesi->fresh()->nilai_final);
        $this->assertSame('selesai', $this->sesi->fresh()->status);
    }

    public function test_objective_answers_are_graded_once(): void
    {
        $this->expireSesi();
        $this->repo()->autoSubmitExpiredSessions();

        $pgJawaban = $this->sesi->fresh(['jawaban'])->jawaban->where('is_correct', true)->first();
        $this->assertNotNull($pgJawaban);
        $this->assertSame(50.0, (float) $pgJawaban->poin_didapat);
    }

    public function test_essay_answers_remain_pending(): void
    {
        $this->expireSesi();
        $this->repo()->autoSubmitExpiredSessions();

        $esaiJawaban = $this->sesi->fresh(['jawaban'])->jawaban->where('is_correct', null)->first();
        $this->assertNotNull($esaiJawaban);
        $this->assertSame(0.0, (float) $esaiJawaban->poin_didapat);
        $this->assertSame('Variabel adalah wadah data.', $esaiJawaban->jawaban_esai);
    }

    public function test_non_expired_attempt_is_not_touched(): void
    {
        // Sesi di fixture masih 'proses' dengan waktu_mulai = now() (belum lewat).
        $result = $this->repo()->autoSubmitExpiredSessions();

        $this->assertSame(0, $result['expired']);
        $this->assertSame(0, $result['submitted']);
        $this->assertSame('proses', $this->sesi->fresh()->status);
    }

    public function test_finished_attempt_is_not_touched(): void
    {
        $this->repo()->finalizeSesiUjian($this->sesi->id, 'timeout');
        $this->assertSame('timeout', $this->sesi->fresh()->status);

        $result = $this->repo()->autoSubmitExpiredSessions();
        $this->assertSame(0, $result['expired']);
        $this->assertSame(0, $result['submitted']);
    }

    public function test_timeout_event_does_not_expose_answer_keys(): void
    {
        $this->expireSesi();
        $this->repo()->autoSubmitExpiredSessions();

        $hasil = $this->repo()->getHasilUjian($this->ujian->id);
        $json = json_encode($hasil);
        $this->assertStringNotContainsString('kunci_jawaban', (string) $json);
        $this->assertStringNotContainsString('kunci', (string) $json);
        $this->assertSame(1, $hasil['ringkasan']['total_peserta']);
    }

    public function test_command_reports_counts_without_soal_details(): void
    {
        $this->expireSesi();
        $exitCode = \Illuminate\Support\Facades\Artisan::call('cbt:auto-timeout');

        $this->assertSame(0, $exitCode);
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('disubmit otomatis: 1', $output);
        $this->assertStringNotContainsString('kunci_jawaban', $output);
        $this->assertStringNotContainsString('Jelaskan variabel', $output);
        $this->assertSame('timeout', $this->sesi->fresh()->status);
    }
}
