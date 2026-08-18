<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsKisiKisi;
use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ModulAjarSeeder extends Seeder
{
    public function run(): void
    {
        $unit = EducationUnit::first();
        $tahun = AcademicYear::first();
        $semester = Semester::first();
        $kurikulum = MasterKurikulum::first();
        $subject = Subject::first();
        $guru = Employee::first();
        $kelas = Kelas::first();
        $cp = CapaianPembelajaran::first();
        $tp = TujuanPembelajaran::first();

        // Create fallback entities if missing for safe seeding
        if (! $tahun) {
            $tahun = AcademicYear::create([
                'id' => (string) Str::uuid(),
                'tahun' => '2025/2026',
                'status' => true,
            ]);
        }

        if (! $semester) {
            $semester = Semester::create([
                'id' => (string) Str::uuid(),
                'academic_year_id' => $tahun->id,
                'name' => 'Ganjil',
                'sequence' => 1,
                'start_date' => '2025-07-01',
                'end_date' => '2025-12-31',
                'is_active' => true,
            ]);
        }

        if (! $kurikulum) {
            $kurikulum = MasterKurikulum::create([
                'id' => (string) Str::uuid(),
                'kode_kurikulum' => 'KM-2024',
                'nama_kurikulum' => 'Kurikulum Merdeka 2024',
                'versi' => '2024.1',
                'status' => true,
            ]);
        }

        if (! $subject) {
            $subject = Subject::create([
                'id' => (string) Str::uuid(),
                'unit_pendidikan_id' => $unit?->id,
                'kurikulum_id' => $kurikulum->id,
                'kode_mapel' => 'PAI-01',
                'nama_mapel' => 'Pendidikan Agama Islam & Budi Pekerti',
                'kelompok_mapel' => 'Kelompok A',
                'kategori' => 'Wajib',
                'jenjang' => 'SMA',
                'jam_pelajaran' => 3,
                'kkm' => 75.00,
                'status' => true,
            ]);
        }

        if (! $guru) {
            $guru = Employee::create([
                'id' => (string) Str::uuid(),
                'niy' => '199001012022011001',
                'nama_lengkap' => 'Ustadz Ahmad Al-Farisi, S.Pd.I',
                'email' => 'ahmad.farisi@sekolah.sch.id',
                'jenis_kelamin' => 'L',
            ]);
        }

        if (! $kelas) {
            $kelas = Kelas::create([
                'id' => (string) Str::uuid(),
                'unit_pendidikan_id' => $unit?->id,
                'tahun_ajaran_id' => $tahun->id,
                'semester_id' => $semester->id,
                'kode_kelas' => 'K-X-1',
                'nama_kelas' => 'X-IPA-1',
                'jenjang' => 'SMA',
                'tingkat' => 10,
                'status' => true,
            ]);
        }

        if (! $cp) {
            $cp = CapaianPembelajaran::create([
                'id' => (string) Str::uuid(),
                'kurikulum_id' => $kurikulum->id,
                'mata_pelajaran_id' => $subject->id,
                'kode_cp' => 'CP-PAI-X-01',
                'nama_cp' => 'Memahami Al-Qur\'an dan Hadits tentang Toleransi & Kerukunan',
                'fase' => 'Fase E',
                'kelas_target' => 'Kelas 10',
                'urutan' => 1,
                'status' => true,
            ]);
        }

        if (! $tp) {
            $tp = TujuanPembelajaran::create([
                'id' => (string) Str::uuid(),
                'cp_id' => $cp->id,
                'kode_tp' => 'TP-PAI-X-01.1',
                'nama_tp' => 'Menganalisis kandungan QS. Al-Hujurat: 13 tentang Keberagaman dan Persaudaraan',
                'alokasi_waktu_jp' => 4,
                'urutan' => 1,
                'status' => true,
            ]);
        }

        $dummyModuls = [
            [
                'kode_modul' => 'MA-PAI-10-01',
                'judul_modul' => 'Toleransi & Indahnya Keberagaman dalam Islam',
                'fase' => 'Fase E',
                'semester' => 'Ganjil',
                'alokasi_waktu_jp' => 6,
                'status' => 'published',
                'versi' => '1.0',
            ],
            [
                'kode_modul' => 'MA-PAI-10-02',
                'judul_modul' => 'Menjauhi Pergaulan Bebas dan Zina Menurut Islam',
                'fase' => 'Fase E',
                'semester' => 'Ganjil',
                'alokasi_waktu_jp' => 4,
                'status' => 'published',
                'versi' => '1.1',
            ],
            [
                'kode_modul' => 'MA-MTK-10-01',
                'judul_modul' => 'Eksponen dan Logaritma dalam Kehidupan Sehari-hari',
                'fase' => 'Fase E',
                'semester' => 'Ganjil',
                'alokasi_waktu_jp' => 8,
                'status' => 'draft',
                'versi' => '1.0',
            ],
            [
                'kode_modul' => 'MA-BIN-10-01',
                'judul_modul' => 'Mengungkap Fakta Alam Secara Objektif lewat Teks Laporan Hasil Observasi',
                'fase' => 'Fase E',
                'semester' => 'Ganjil',
                'alokasi_waktu_jp' => 6,
                'status' => 'draft',
                'versi' => '1.0',
            ],
            [
                'kode_modul' => 'MA-BIG-10-01',
                'judul_modul' => 'Descriptive Text: Great Athletes & Inspiration Figures',
                'fase' => 'Fase E',
                'semester' => 'Ganjil',
                'alokasi_waktu_jp' => 6,
                'status' => 'archived',
                'versi' => '0.9',
            ],
        ];

        foreach ($dummyModuls as $dm) {
            $modul = LmsModulAjar::updateOrCreate(
                ['kode_modul' => $dm['kode_modul']],
                [
                    'unit_pendidikan_id' => $unit?->id,
                    'tahun_ajaran_id' => $tahun->id,
                    'semester_id' => $semester->id,
                    'kurikulum_id' => $kurikulum->id,
                    'mata_pelajaran_id' => $subject->id,
                    'guru_id' => $guru->id,
                    'kelas_id' => $kelas->id,
                    'rombel_id' => $kelas->id,
                    'cp_id' => $cp->id,
                    'tp_id' => $tp->id,
                    'judul_modul' => $dm['judul_modul'],
                    'fase' => $dm['fase'],
                    'semester' => $dm['semester'],
                    'alokasi_waktu_jp' => $dm['alokasi_waktu_jp'],
                    'tujuan_pembelajaran' => 'Memahami dan mengaplikasikan materi secara kritis, mandiri, dan berkarakter.',
                    'profil_pelajar_pancasila' => 'Beriman, Bertakwa kepada Tuhan YME, Mandiri, Bernalar Kritis',
                    'target_peserta_didik' => 'Siswa kelas X Reguler (30 siswa)',
                    'model_pembelajaran' => 'Problem Based Learning (PBL)',
                    'metode_pembelajaran' => 'Diskusi Kelompok, Presentasi, Apersepsi Visual',
                    'media_pembelajaran' => 'Slide PPT Interaktif, Video Pembelajaran, Canva, LKPD',
                    'sumber_belajar' => 'Buku Cetak Kemendikbudristek & Portal LMS Sekolah',
                    'kegiatan_pendahuluan' => '1. Salam, Doa, dan Apersepsi.\n2. Guru memotivasi siswa dan menjelaskan tujuan.',
                    'kegiatan_inti' => '1. Siswa membentuk kelompok 5 orang.\n2. Membahas studi kasus riil.\n3. Presentasi karya kelompok.',
                    'kegiatan_penutup' => '1. Refleksi bersama.\n2. Kesimpulan dan Doa Penutup.',
                    'asesmen_awal' => 'Kuis diagnosis 5 soal awal.',
                    'asesmen_proses' => 'Observasi keaktifan diskusi kelompok.',
                    'asesmen_akhir' => 'Tes formatif tertulis akhir modul.',
                    'rencana_penilaian' => 'Pengetahuan (40%), Keterampilan (40%), Sikap (20%)',
                    'lampiran' => [
                        ['nama' => 'LKPD_Pertemuan_1.pdf', 'url' => 'https://example.com/lkpd1.pdf'],
                    ],
                    'status' => $dm['status'],
                    'deskripsi' => 'Modul Ajar terpadu Kurikulum Merdeka.',
                    'versi' => $dm['versi'],
                ]
            );

            // Attach CP & TP via pivot
            $modul->cps()->sync([$cp->id]);
            $modul->tps()->sync([$tp->id]);

            // Seed default LmsMateri dependency for LmsMediaSeeder
            LmsMateri::firstOrCreate(
                ['modul_ajar_id' => $modul->id, 'judul' => 'Materi Utama: '.$dm['judul_modul']],
                [
                    'mata_pelajaran_id' => $subject->id,
                    'guru_id' => $guru->id,
                    'tipe_materi' => 'teks',
                    'konten' => 'Pembahasan lengkap mengenai '.$dm['judul_modul'],
                    'urutan' => 1,
                    'is_published' => true,
                    'status' => 'aktif',
                ]
            );

            // Seed default LmsKisiKisi dependency for LmsBankSoalSeeder & LmsUjianSeeder
            LmsKisiKisi::firstOrCreate(
                ['judul_kisi' => 'Kisi-kisi Evaluasi '.$dm['judul_modul']],
                [
                    'kurikulum_id' => $kurikulum->id,
                    'mata_pelajaran_id' => $subject->id,
                    'cp_id' => $cp->id,
                    'tp_id' => $tp->id,
                    'kelas_id' => $kelas->id,
                    'semester_id' => $semester->id,
                    'tahun_ajaran_id' => $tahun->id,
                    'guru_id' => $guru->id,
                    'jenis_ujian' => 'UH',
                    'jumlah_soal' => 10,
                    'alokasi_waktu_menit' => 45,
                    'kompetensi_dasar' => 'Memahami materi modul '.$dm['judul_modul'],
                    'level_kognitif' => 'L2 (Aplikasi)',
                    'status' => true,
                ]
            );
        }
    }
}
