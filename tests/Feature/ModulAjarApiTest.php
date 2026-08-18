<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsMateri;
use App\Models\LmsMedia;
use App\Models\LmsModulAjar;
use App\Models\LmsAktivitasBelajar;
use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Models\LmsReferensi;
use App\Models\MasterKurikulum;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Student;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ModulAjarApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected EducationUnit $unit;

    protected AcademicYear $tahun;

    protected Semester $semester;

    protected MasterKurikulum $kurikulum;

    protected Subject $subject;

    protected Employee $guru;

    protected Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin.modul@sekolah.sch.id',
        ]);
        $this->user->assignRole(Role::findOrCreate('Super Admin', 'web'));

        $this->unit = EducationUnit::create([
            'name' => 'SMP IT Antigravity',
            'code' => 'SMP-AG',
            'level' => 'SMP',
            'is_active' => true,
        ]);

        $this->tahun = AcademicYear::create([
            'name' => '2025/2026',
            'code' => '2025-2026',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'tahun' => '2025/2026',
        ]);

        $this->semester = Semester::create([
            'academic_year_id' => $this->tahun->id,
            'name' => 'Ganjil',
            'sequence' => 1,
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
            'is_active' => true,
        ]);

        $this->kurikulum = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-SMP',
            'nama_kurikulum' => 'Kurikulum Merdeka SMP',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $this->unit->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);

        $this->subject = Subject::create([
            'unit_pendidikan_id' => $this->unit->id,
            'kurikulum_id' => $this->kurikulum->id,
            'kode_mapel' => 'PAI-SMP',
            'nama_mapel' => 'Pendidikan Agama Islam',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
        ]);

        $this->guru = Employee::create([
            'niy' => '199505052022011003',
            'nama_lengkap' => 'Ustadz Hanif, S.Pd.I',
            'email' => 'hanif@sekolah.sch.id',
            'jenis_kelamin' => 'L',
            'unit_id' => $this->unit->id,
        ]);

        $this->kelas = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kode_kelas' => 'K-7-A',
            'nama_kelas' => '7-A',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => true,
        ]);
    }

    public function test_dapat_mengambil_daftar_modul_ajar(): void
    {
        LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-7-01',
            'judul_modul' => 'Al-Qur\'an dan Kelestarian Alam',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 4,
            'status' => 'Publish',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/lms/modul-ajar');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
                'statistik',
            ]);
    }

    public function test_pengguna_tanpa_peran_pembelajaran_tidak_dapat_mengakses_modul_ajar(): void
    {
        $userTanpaAkses = User::factory()->create();

        $this->actingAs($userTanpaAkses)
            ->getJson('/api/lms/modul-ajar')
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_membuka_modul_ajar_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011005',
            'nama_lengkap' => 'Guru Unit A',
            'email' => 'guru.unit-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011006',
            'nama_lengkap' => 'Guru Unit B',
            'email' => 'guru.lain@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-GURU-LAIN',
            'judul_modul' => 'Modul Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $this->actingAs($guruUser)
            ->getJson("/api/lms/modul-ajar/{$modulGuruLain->id}")
            ->assertForbidden();
    }

    public function test_portal_siswa_hanya_menampilkan_materi_yang_sudah_dipublikasikan(): void
    {
        $siswaUser = User::factory()->create();
        $siswaUser->assignRole(Role::findOrCreate('Siswa', 'web'));
        Student::create([
            'user_id' => $siswaUser->id,
            'kelas_id' => $this->kelas->id,
            'unit_id' => $this->unit->id,
            'nis' => '20250001',
            'full_name' => 'Siswa Materi',
            'gender' => 'L',
            'is_active' => true,
        ]);
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-PORTAL-MATERI',
            'judul_modul' => 'Modul Portal Materi',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Publish',
        ]);
        LmsMateri::create([
            'modul_ajar_id' => $modul->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'judul' => 'Materi Draft',
            'konten' => 'Tidak boleh tampil.',
            'status' => 'draft',
        ]);
        $published = LmsMateri::create([
            'modul_ajar_id' => $modul->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'judul' => 'Materi Published',
            'konten' => 'Boleh tampil.',
            'status' => 'published',
        ]);

        $this->actingAs($siswaUser)
            ->getJson('/api/portal/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $published->id);
    }

    public function test_pengguna_tanpa_peran_pembelajaran_tidak_dapat_mengakses_materi(): void
    {
        $userTanpaAkses = User::factory()->create();

        $this->actingAs($userTanpaAkses)
            ->getJson('/api/lms/materi')
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_membuka_materi_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011007',
            'nama_lengkap' => 'Guru Materi A',
            'email' => 'guru.materi-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011008',
            'nama_lengkap' => 'Guru Materi B',
            'email' => 'guru.materi-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-MATERI-GURU-LAIN',
            'judul_modul' => 'Modul Materi Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $materiGuruLain = LmsMateri::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'judul' => 'Materi Guru Lain',
            'konten' => 'Tidak boleh dibuka.',
            'status' => 'draft',
        ]);

        $this->actingAs($guruUser)
            ->getJson("/api/lms/materi/{$materiGuruLain->id}")
            ->assertForbidden();
    }

    public function test_pengguna_tanpa_peran_pembelajaran_tidak_dapat_mengakses_media(): void
    {
        $userTanpaAkses = User::factory()->create();

        $this->actingAs($userTanpaAkses)
            ->getJson('/api/lms/media')
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_membuka_media_pada_materi_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011009',
            'nama_lengkap' => 'Guru Media A',
            'email' => 'guru.media-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011010',
            'nama_lengkap' => 'Guru Media B',
            'email' => 'guru.media-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-MEDIA-GURU-LAIN',
            'judul_modul' => 'Modul Media Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $materiGuruLain = LmsMateri::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'judul' => 'Materi Media Guru Lain',
            'konten' => 'Tidak boleh dibuka.',
            'status' => 'draft',
        ]);
        $mediaGuruLain = LmsMedia::create([
            'materi_id' => $materiGuruLain->id,
            'nama_file' => 'materi-guru-lain.pdf',
            'tipe_file' => 'pdf',
            'path_file' => 'media_files/materi-guru-lain.pdf',
            'urutan' => 1,
        ]);

        $this->actingAs($guruUser)
            ->getJson("/api/lms/media/{$mediaGuruLain->id}")
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_menambahkan_media_ke_materi_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011020',
            'nama_lengkap' => 'Guru Media Pemilik A',
            'email' => 'guru.media-pemilik-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011021',
            'nama_lengkap' => 'Guru Media Pemilik B',
            'email' => 'guru.media-pemilik-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-MEDIA-POST-GURU-LAIN',
            'judul_modul' => 'Modul Media Post Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $materiGuruLain = LmsMateri::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'judul' => 'Materi Target Guru Lain',
            'konten' => 'Tidak boleh menerima media dari guru lain.',
            'status' => 'draft',
        ]);

        $this->actingAs($guruUser)
            ->postJson('/api/lms/media', [
                'materi_id' => $materiGuruLain->id,
                'nama_file' => 'tautan-tidak-sah',
                'tipe_file' => 'link',
                'url_eksternal' => 'https://example.test/media',
            ])
            ->assertForbidden();
    }

    public function test_daftar_media_guru_hanya_memuat_media_dari_materi_miliknya(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        $guru = Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011022',
            'nama_lengkap' => 'Guru Daftar Media A',
            'email' => 'guru.daftar-media-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011023',
            'nama_lengkap' => 'Guru Daftar Media B',
            'email' => 'guru.daftar-media-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulMilikGuru = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DAFTAR-MEDIA-A',
            'judul_modul' => 'Modul Daftar Media A',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $modulGuruLain = $modulMilikGuru->replicate(['id']);
        $modulGuruLain->fill([
            'guru_id' => $guruLain->id,
            'kode_modul' => 'MA-DAFTAR-MEDIA-B',
            'judul_modul' => 'Modul Daftar Media B',
        ]);
        $modulGuruLain->save();
        $materiMilikGuru = LmsMateri::create([
            'modul_ajar_id' => $modulMilikGuru->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Daftar A',
            'konten' => 'Milik guru A.',
            'status' => 'draft',
        ]);
        $materiGuruLain = LmsMateri::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'judul' => 'Materi Daftar B',
            'konten' => 'Milik guru B.',
            'status' => 'draft',
        ]);
        $mediaMilikGuru = LmsMedia::create([
            'materi_id' => $materiMilikGuru->id,
            'nama_file' => 'milik-guru-a.pdf',
            'tipe_file' => 'pdf',
            'urutan' => 1,
        ]);
        LmsMedia::create([
            'materi_id' => $materiGuruLain->id,
            'nama_file' => 'milik-guru-b.pdf',
            'tipe_file' => 'pdf',
            'urutan' => 1,
        ]);

        $this->actingAs($guruUser)
            ->getJson('/api/lms/media')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mediaMilikGuru->id);
    }

    public function test_media_upload_menolak_file_executable(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-UPLOAD-MEDIA',
            'judul_modul' => 'Modul Upload Media',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $materi = LmsMateri::create([
            'modul_ajar_id' => $modul->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'judul' => 'Materi Upload Media',
            'konten' => 'Menguji validasi upload.',
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/lms/media', [
                'materi_id' => $materi->id,
                'nama_file' => 'skrip-eksekusi',
                'tipe_file' => 'pdf',
                'file' => UploadedFile::fake()->create('skrip.php', 4, 'application/x-httpd-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_guru_tidak_dapat_membuka_referensi_modul_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011011',
            'nama_lengkap' => 'Guru Referensi A',
            'email' => 'guru.referensi-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011012',
            'nama_lengkap' => 'Guru Referensi B',
            'email' => 'guru.referensi-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-REFERENSI-GURU-LAIN',
            'judul_modul' => 'Modul Referensi Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $referensiGuruLain = LmsReferensi::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'judul' => 'Referensi Guru Lain',
            'url' => 'https://example.test/referensi',
            'status' => 'aktif',
        ]);

        $this->actingAs($guruUser)
            ->getJson("/api/lms/referensi/{$referensiGuruLain->id}")
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_menambahkan_referensi_ke_modul_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011024',
            'nama_lengkap' => 'Guru Referensi Pemilik A',
            'email' => 'guru.referensi-pemilik-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011025',
            'nama_lengkap' => 'Guru Referensi Pemilik B',
            'email' => 'guru.referensi-pemilik-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-REFERENSI-POST-GURU-LAIN',
            'judul_modul' => 'Modul Referensi Post Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $this->actingAs($guruUser)
            ->postJson('/api/lms/referensi', [
                'modul_ajar_id' => $modulGuruLain->id,
                'judul' => 'Referensi lintas guru',
                'url' => 'https://example.test/referensi',
                'status' => 'aktif',
            ])
            ->assertForbidden();
    }

    public function test_referensi_menolak_url_javascript(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/lms/referensi', [
                'judul' => 'Referensi URL Tidak Aman',
                'url' => 'javascript:alert(1)',
                'status' => 'aktif',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_guru_tidak_dapat_membuka_aktivitas_modul_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011013',
            'nama_lengkap' => 'Guru Aktivitas A',
            'email' => 'guru.aktivitas-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011014',
            'nama_lengkap' => 'Guru Aktivitas B',
            'email' => 'guru.aktivitas-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-AKTIVITAS-GURU-LAIN',
            'judul_modul' => 'Modul Aktivitas Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);
        $aktivitasGuruLain = LmsAktivitasBelajar::create([
            'modul_ajar_id' => $modulGuruLain->id,
            'nama_aktivitas' => 'Aktivitas Guru Lain',
            'jenis_aktivitas' => 'Inti',
            'waktu' => 15,
            'urutan' => 1,
            'status' => 'draft',
        ]);

        $this->actingAs($guruUser)
            ->getJson("/api/lms/aktivitas/{$aktivitasGuruLain->id}")
            ->assertForbidden();
    }

    public function test_guru_tidak_dapat_menambahkan_aktivitas_ke_modul_guru_lain(): void
    {
        $guruUser = User::factory()->create();
        $guruUser->assignRole(Role::findOrCreate('Guru', 'web'));
        Employee::create([
            'user_id' => $guruUser->id,
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011026',
            'nama_lengkap' => 'Guru Aktivitas Pemilik A',
            'email' => 'guru.aktivitas-pemilik-a@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $guruLain = Employee::create([
            'unit_id' => $this->unit->id,
            'niy' => '199505052022011027',
            'nama_lengkap' => 'Guru Aktivitas Pemilik B',
            'email' => 'guru.aktivitas-pemilik-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $modulGuruLain = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $guruLain->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-AKTIVITAS-POST-GURU-LAIN',
            'judul_modul' => 'Modul Aktivitas Post Guru Lain',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $this->actingAs($guruUser)
            ->postJson('/api/lms/aktivitas', [
                'modul_ajar_id' => $modulGuruLain->id,
                'nama_aktivitas' => 'Aktivitas lintas guru',
                'jenis_aktivitas' => 'Inti',
                'waktu' => 15,
                'urutan' => 1,
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    public function test_siswa_di_luar_kelas_modul_tidak_dapat_berkomentar_di_diskusi(): void
    {
        $kelasLain = Kelas::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kode_kelas' => 'K-7-B-DISKUSI',
            'nama_kelas' => '7-B Diskusi',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => true,
        ]);
        $siswaUser = User::factory()->create();
        $siswaUser->assignRole(Role::findOrCreate('Siswa', 'web'));
        Student::create([
            'user_id' => $siswaUser->id,
            'kelas_id' => $kelasLain->id,
            'unit_id' => $this->unit->id,
            'nis' => '20250002',
            'full_name' => 'Siswa Kelas Lain',
            'gender' => 'L',
            'is_active' => true,
        ]);
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DISKUSI-KELAS-A',
            'judul_modul' => 'Modul Diskusi Kelas A',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Publish',
        ]);
        $diskusi = LmsDiskusi::create([
            'modul_ajar_id' => $modul->id,
            'judul' => 'Diskusi Kelas A',
            'status' => 'aktif',
        ]);

        $this->actingAs($siswaUser)
            ->postJson("/api/lms/diskusi/{$diskusi->id}/komentar", [
                'konten' => 'Komentar dari kelas lain.',
                'peran_pengirim' => 'Guru',
            ])
            ->assertForbidden();
    }

    public function test_siswa_tidak_dapat_menghapus_komentar_siswa_lain(): void
    {
        $siswaPemilikUser = User::factory()->create();
        $siswaPemilikUser->assignRole(Role::findOrCreate('Siswa', 'web'));
        Student::create([
            'user_id' => $siswaPemilikUser->id,
            'kelas_id' => $this->kelas->id,
            'unit_id' => $this->unit->id,
            'nis' => '20250003',
            'full_name' => 'Siswa Pemilik Komentar',
            'gender' => 'L',
            'is_active' => true,
        ]);
        $siswaLainUser = User::factory()->create();
        $siswaLainUser->assignRole(Role::findOrCreate('Siswa', 'web'));
        Student::create([
            'user_id' => $siswaLainUser->id,
            'kelas_id' => $this->kelas->id,
            'unit_id' => $this->unit->id,
            'nis' => '20250004',
            'full_name' => 'Siswa Lain Komentar',
            'gender' => 'L',
            'is_active' => true,
        ]);
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DISKUSI-KOMENTAR',
            'judul_modul' => 'Modul Diskusi Komentar',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Publish',
        ]);
        $diskusi = LmsDiskusi::create([
            'modul_ajar_id' => $modul->id,
            'judul' => 'Diskusi Komentar Siswa',
            'status' => 'aktif',
        ]);
        $komentar = LmsDiskusiKomentar::create([
            'diskusi_id' => $diskusi->id,
            'user_id' => $siswaPemilikUser->id,
            'peran_pengirim' => 'Siswa',
            'konten' => 'Komentar milik siswa pertama.',
        ]);

        $this->actingAs($siswaLainUser)
            ->deleteJson("/api/lms/diskusi/{$diskusi->id}/komentar/{$komentar->id}")
            ->assertForbidden();
    }

    public function test_dapat_membuat_modul_ajar_via_api(): void
    {
        $payload = [
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-API-01',
            'judul_modul' => 'Modul Baru via API Test',
            'fase' => 'Fase D',
            'semester' => 'Ganjil',
            'alokasi_waktu_jp' => 4,
            'status' => 'Draft',
            'versi' => '1.0',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/lms/modul-ajar', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.kode_modul', 'MA-API-01')
            ->assertJsonPath('data.judul_modul', 'Modul Baru via API Test');

        $this->assertDatabaseHas('lms_modul_ajar', [
            'kode_modul' => 'MA-API-01',
        ]);
    }

    public function test_modul_ajar_rejects_a_tp_from_another_cp(): void
    {
        $cpA = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_cp' => 'CP-MODUL-A',
            'nama_cp' => 'CP Modul A',
            'status' => true,
        ]);
        $cpB = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_cp' => 'CP-MODUL-B',
            'nama_cp' => 'CP Modul B',
            'status' => true,
        ]);
        $tpB = TujuanPembelajaran::create([
            'cp_id' => $cpB->id,
            'kode_tp' => 'TP-MODUL-B',
            'nama_tp' => 'TP Modul B',
            'deskripsi' => 'TP yang bukan turunan CP pilihan.',
            'status' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/lms/modul-ajar', [
                'unit_pendidikan_id' => $this->unit->id,
                'tahun_ajaran_id' => $this->tahun->id,
                'semester_id' => $this->semester->id,
                'kurikulum_id' => $this->kurikulum->id,
                'mata_pelajaran_id' => $this->subject->id,
                'guru_id' => $this->guru->id,
                'kelas_id' => $this->kelas->id,
                'cp_id' => $cpA->id,
                'tp_id' => $tpB->id,
                'judul_modul' => 'Modul dengan relasi CP TP tidak valid',
                'fase' => 'Fase D',
                'alokasi_waktu_jp' => 2,
                'status' => 'Draft',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tp_id');
    }

    public function test_modul_ajar_options_limit_cp_and_tp_to_the_requested_context(): void
    {
        $cpA = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'kode_cp' => 'CP-OPTIONS-A',
            'nama_cp' => 'CP Options A',
            'status' => true,
        ]);
        $tpA = TujuanPembelajaran::create([
            'cp_id' => $cpA->id,
            'kode_tp' => 'TP-OPTIONS-A',
            'nama_tp' => 'TP Options A',
            'status' => true,
        ]);

        $unitB = EducationUnit::create([
            'name' => 'SMP Unit B',
            'code' => 'SMP-B',
            'level' => 'SMP',
            'is_active' => true,
        ]);
        $kurikulumB = MasterKurikulum::create([
            'kode_kurikulum' => 'KM-SMP-B',
            'nama_kurikulum' => 'Kurikulum Merdeka SMP B',
            'jenis_kurikulum' => 'SIT',
            'unit_pendidikan_id' => $unitB->id,
            'jenjang' => 'SMP',
            'tahun_ajaran_id' => $this->tahun->id,
            'tanggal_mulai' => '2025-07-01',
            'status' => true,
        ]);
        $subjectB = Subject::create([
            'unit_pendidikan_id' => $unitB->id,
            'kurikulum_id' => $kurikulumB->id,
            'kode_mapel' => 'PAI-SMP-B',
            'nama_mapel' => 'Pendidikan Agama Islam B',
            'kelompok_mapel' => 'Kelompok A',
            'kategori' => 'Wajib',
            'jenjang' => 'SMP',
            'jam_pelajaran' => 3,
            'kkm' => 75.00,
            'status' => true,
        ]);
        $semesterB = Semester::create([
            'academic_year_id' => $this->tahun->id,
            'name' => 'Genap',
            'sequence' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
        ]);
        Employee::create([
            'niy' => '199505052022011004',
            'nama_lengkap' => 'Guru Unit B',
            'email' => 'guru.unit-b@sekolah.sch.id',
            'jenis_kelamin' => 'L',
            'unit_id' => $unitB->id,
        ]);
        Kelas::create([
            'unit_pendidikan_id' => $unitB->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $semesterB->id,
            'kode_kelas' => 'K-7-B',
            'nama_kelas' => '7-B',
            'jenjang' => 'SMP',
            'tingkat' => 7,
            'status' => true,
        ]);
        $cpB = CapaianPembelajaran::create([
            'unit_pendidikan_id' => $unitB->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'kurikulum_id' => $kurikulumB->id,
            'mata_pelajaran_id' => $subjectB->id,
            'kode_cp' => 'CP-OPTIONS-B',
            'nama_cp' => 'CP Options B',
            'status' => true,
        ]);
        TujuanPembelajaran::create([
            'cp_id' => $cpB->id,
            'kode_tp' => 'TP-OPTIONS-B',
            'nama_tp' => 'TP Options B',
            'status' => true,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/lms/modul-ajar/options?'.http_build_query([
                'unit_pendidikan_id' => $this->unit->id,
                'tahun_ajaran_id' => $this->tahun->id,
                'kurikulum_id' => $this->kurikulum->id,
                'mata_pelajaran_id' => $this->subject->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.kurikulums')
            ->assertJsonPath('data.kurikulums.0.id', $this->kurikulum->id)
            ->assertJsonCount(1, 'data.subjects')
            ->assertJsonPath('data.subjects.0.id', $this->subject->id)
            ->assertJsonCount(1, 'data.teachers')
            ->assertJsonPath('data.teachers.0.id', $this->guru->id)
            ->assertJsonCount(1, 'data.classes')
            ->assertJsonPath('data.classes.0.id', $this->kelas->id)
            ->assertJsonCount(2, 'data.semesters')
            ->assertJsonFragment(['id' => $this->semester->id])
            ->assertJsonFragment(['id' => $semesterB->id])
            ->assertJsonCount(1, 'data.capaian_pembelajaran')
            ->assertJsonPath('data.capaian_pembelajaran.0.id', $cpA->id)
            ->assertJsonCount(1, 'data.tujuan_pembelajaran')
            ->assertJsonPath('data.tujuan_pembelajaran.0.id', $tpA->id);
    }

    public function test_modul_ajar_import_does_not_report_success_without_an_importer(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/lms/modul-ajar/import')
            ->assertStatus(501)
            ->assertJsonPath('status', 'error');
    }

    public function test_dapat_mempublikasikan_dan_mendapatkan_revisi(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-REV-01',
            'judul_modul' => 'Modul Revisi Test',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Draft',
        ]);

        $pubResp = $this->actingAs($this->user)
            ->postJson("/api/lms/modul-ajar/{$modul->id}/publish");

        $pubResp->assertStatus(200)
            ->assertJsonPath('data.status', 'Publish');

        $revResp = $this->actingAs($this->user)
            ->getJson("/api/lms/modul-ajar/{$modul->id}/revisions");

        $revResp->assertStatus(200)
            ->assertJsonStructure(['status', 'message', 'data']);
    }

    public function test_dapat_menduplikasi_dan_menghapus_modul_ajar(): void
    {
        $modul = LmsModulAjar::create([
            'unit_pendidikan_id' => $this->unit->id,
            'tahun_ajaran_id' => $this->tahun->id,
            'semester_id' => $this->semester->id,
            'kurikulum_id' => $this->kurikulum->id,
            'mata_pelajaran_id' => $this->subject->id,
            'guru_id' => $this->guru->id,
            'kelas_id' => $this->kelas->id,
            'kode_modul' => 'MA-DUP-01',
            'judul_modul' => 'Modul Asli',
            'fase' => 'Fase D',
            'alokasi_waktu_jp' => 2,
            'status' => 'Publish',
        ]);

        $dupResp = $this->actingAs($this->user)
            ->postJson("/api/lms/modul-ajar/{$modul->id}/duplicate");

        $dupResp->assertStatus(201)
            ->assertJsonPath('data.judul_modul', 'Modul Asli (Salinan)');

        $delResp = $this->actingAs($this->user)
            ->deleteJson("/api/lms/modul-ajar/{$modul->id}");

        $delResp->assertStatus(200);
        $this->assertSoftDeleted('lms_modul_ajar', ['id' => $modul->id]);
    }
}
