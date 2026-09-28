<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsPenugasan;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsRapor;
use App\Models\LmsUjian;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateItem;
use App\Models\PengumumanSekolah;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\TahfizhDailyLog;
use App\Models\TahfizhRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulasiTahunanRamadhanSeeder extends Seeder
{
    /**
     * Kumpulan Surah untuk Simulasi Tahfizh
     */
    private const SURAH_LIST = [
        ['nomor' => 78, 'nama' => 'An-Naba', 'ayat' => 40, 'juz' => 30],
        ['nomor' => 79, 'nama' => 'An-Nazi\'at', 'ayat' => 46, 'juz' => 30],
        ['nomor' => 80, 'nama' => '\'Abasa', 'ayat' => 42, 'juz' => 30],
        ['nomor' => 81, 'nama' => 'At-Takwir', 'ayat' => 29, 'juz' => 30],
        ['nomor' => 82, 'nama' => 'Al-Infitar', 'ayat' => 19, 'juz' => 30],
        ['nomor' => 83, 'nama' => 'Al-Muthaffifin', 'ayat' => 36, 'juz' => 30],
        ['nomor' => 84, 'nama' => 'Al-Insyiqaq', 'ayat' => 25, 'juz' => 30],
        ['nomor' => 85, 'nama' => 'Al-Buruj', 'ayat' => 22, 'juz' => 30],
        ['nomor' => 86, 'nama' => 'At-Tariq', 'ayat' => 17, 'juz' => 30],
        ['nomor' => 87, 'nama' => 'Al-A\'la', 'ayat' => 19, 'juz' => 30],
        ['nomor' => 88, 'nama' => 'Al-Ghasyiyah', 'ayat' => 26, 'juz' => 30],
        ['nomor' => 89, 'nama' => 'Al-Fajr', 'ayat' => 30, 'juz' => 30],
        ['nomor' => 90, 'nama' => 'Al-Balad', 'ayat' => 20, 'juz' => 30],
        ['nomor' => 91, 'nama' => 'Asy-Syams', 'ayat' => 15, 'juz' => 30],
        ['nomor' => 92, 'nama' => 'Al-Lail', 'ayat' => 21, 'juz' => 30],
        ['nomor' => 93, 'nama' => 'Ad-Duha', 'ayat' => 11, 'juz' => 30],
        ['nomor' => 94, 'nama' => 'Asy-Syarh', 'ayat' => 8, 'juz' => 30],
        ['nomor' => 95, 'nama' => 'At-Tin', 'ayat' => 8, 'juz' => 30],
        ['nomor' => 96, 'nama' => 'Al-\'Alaq', 'ayat' => 19, 'juz' => 30],
        ['nomor' => 97, 'nama' => 'Al-Qadr', 'ayat' => 5, 'juz' => 30],
        ['nomor' => 98, 'nama' => 'Al-Bayyinah', 'ayat' => 8, 'juz' => 30],
        ['nomor' => 99, 'nama' => 'Az-Zalzalah', 'ayat' => 8, 'juz' => 30],
        ['nomor' => 100, 'nama' => 'Al-\'Adiyat', 'ayat' => 11, 'juz' => 30],
        ['nomor' => 101, 'nama' => 'Al-Qari\'ah', 'ayat' => 11, 'juz' => 30],
        ['nomor' => 102, 'nama' => 'At-Takasur', 'ayat' => 8, 'juz' => 30],
        ['nomor' => 103, 'nama' => 'Al-\'Asr', 'ayat' => 3, 'juz' => 30],
        ['nomor' => 104, 'nama' => 'Al-Humazah', 'ayat' => 9, 'juz' => 30],
        ['nomor' => 105, 'nama' => 'Al-Fil', 'ayat' => 5, 'juz' => 30],
        ['nomor' => 106, 'nama' => 'Quraisy', 'ayat' => 4, 'juz' => 30],
        ['nomor' => 107, 'nama' => 'Al-Ma\'un', 'ayat' => 7, 'juz' => 30],
        ['nomor' => 108, 'nama' => 'Al-Kausar', 'ayat' => 3, 'juz' => 30],
        ['nomor' => 109, 'nama' => 'Al-Kafirun', 'ayat' => 6, 'juz' => 30],
        ['nomor' => 110, 'nama' => 'An-Nasr', 'ayat' => 3, 'juz' => 30],
        ['nomor' => 111, 'nama' => 'Al-Lahab', 'ayat' => 5, 'juz' => 30],
        ['nomor' => 112, 'nama' => 'Al-Ikhlas', 'ayat' => 4, 'juz' => 30],
        ['nomor' => 113, 'nama' => 'Al-Falaq', 'ayat' => 5, 'juz' => 30],
        ['nomor' => 114, 'nama' => 'An-Nas', 'ayat' => 6, 'juz' => 30],
    ];

    public function run(): void
    {
        // 0. PENGAMAN KETAT PRODUCTION (Safety Guard)
        if (app()->environment('production')) {
            $this->command?->error('================================================================');
            $this->command?->error('BAHAYA: SimulasiTahunanRamadhanSeeder DITOLAK di environment PRODUCTION!');
            $this->command?->error('Seeder ini hanya untuk lingkungan staging, local, development, atau testing.');
            $this->command?->error('================================================================');
            return;
        }

        $this->command?->info('=== MEMULAI SEEDER SIMULASI TAHUNAN 1 JULI 2026 - 1 JULI 2027 ===');

        // 1. Validasi Konteks Akademik 2026/2027
        $academicYear = AcademicYear::where('name', '2026/2027')->first()
            ?? AcademicYear::where('is_active', true)->first();

        if (!$academicYear) {
            $academicYear = AcademicYear::create([
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
            ]);
        }

        $semesterGanjil = Semester::where('academic_year_id', $academicYear->id)
            ->where('sequence', 1)
            ->first() ?? Semester::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'sequence' => 1],
                ['name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]
            );

        $semesterGenap = Semester::where('academic_year_id', $academicYear->id)
            ->where('sequence', 2)
            ->first() ?? Semester::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'sequence' => 2],
                ['name' => 'Genap', 'start_date' => '2027-01-01', 'end_date' => '2027-06-30', 'is_active' => false]
            );

        // Ambil User Penerbit untuk Pengumuman & Log
        $publisher = User::where('email', 'superadmin@dareliman.sch.id')->first()
            ?? User::where('is_active', true)->first()
            ?? User::first();

        // 2. Agenda Kalender Akademik & Ramadhan 1448 H (2026 - 2027)
        $this->seedKalenderAkademikRamadhan($publisher);

        // 3. Setup Agenda & Template Mutabaah Ramadhan
        $this->setupMutabaahRamadhanItems($academicYear, $semesterGenap);

        // 4. Presensi Terpartisi Juli s/d Desember 2026 & Milestone Semester 2 (2027)
        $this->seedPresensiTahunanTerpartisi($academicYear, $semesterGanjil, $semesterGenap);

        // 5. Penugasan LMS, Pengumpulan & Nilai Siswa (Juli - Desember 2026 & 2027)
        $this->seedPenugasanDanNilaiLms($academicYear, $semesterGanjil, $semesterGenap);

        // 6. Rapor PAS Semester 1 (Desember 2026) & PAT (Juni 2027)
        $this->seedRaporPasDanPat($academicYear, $semesterGanjil, $semesterGenap);

        // 7. Setoran Tahfizh Intensif & Ramadhan 1448 H
        $this->seedTahfizhSetoranTahunan($academicYear, $semesterGanjil, $semesterGenap);

        // 8. Mutabaah Yaumiyyah Harian & Amalan Ramadhan
        $this->seedMutabaahYaumiyyahTahunan($academicYear, $semesterGanjil, $semesterGenap);

        $this->command?->info('=== SIMULASI TAHUNAN 1 JULI 2026 - 1 JULI 2027 SELESAI DENGAN SUKSES! ===');
    }

    /**
     * 2. Kalender Akademik Resmi: Juli 2026 - Juli 2027 Termasuk Agenda Ramadhan 1448 H
     */
    private function seedKalenderAkademikRamadhan(?User $publisher): void
    {
        $this->command?->info('  [1/7] Menyiapkan Agenda Kalender Akademik & Ramadhan 1448 H...');

        $agendas = [
            // Semester 1 (Ganjil 2026)
            [
                'judul' => 'Awal Tahun Ajaran Baru 2026/2027 & Masa Pengenalan Lingkungan Sekolah (MPLS)',
                'kategori' => 'mulai_kbm',
                'mulai' => '2026-07-13',
                'selesai' => '2026-07-17',
                'prioritas' => 1,
                'deskripsi' => 'Pembukaan resmi KBM tahun ajaran 2026/2027 dan MPLS bagi seluruh santri dan siswa baru TKIT, SDIT, TAUD, MIT, SMPIT, SMAIT, dan Ponpes.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penilaian Tengah Semester (PTS) Ganjil Tahun Ajaran 2026/2027',
                'kategori' => 'ujian',
                'mulai' => '2026-09-14',
                'selesai' => '2026-09-25',
                'prioritas' => 1,
                'deskripsi' => 'Pelaksanaan evaluasi tengah semester 1 (PTS) berbasis CBT online dan asesmen tertulis untuk seluruh jenjang pendidikan.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penilaian Akhir Semester (PAS) Ganjil Tahun Ajaran 2026/2027',
                'kategori' => 'ujian',
                'mulai' => '2026-12-01',
                'selesai' => '2026-12-12',
                'prioritas' => 1,
                'deskripsi' => 'Pelaksanaan Penilaian Akhir Semester 1 secara serentak di seluruh unit sekolah terpadu Dar El-Iman.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penyerahan Laporan Hasil Belajar (Rapor PAS) Semester Ganjil 2026/2027',
                'kategori' => 'terima_rapor',
                'mulai' => '2026-12-18',
                'selesai' => '2026-12-18',
                'prioritas' => 1,
                'deskripsi' => 'Penerimaan Rapor semester 1 oleh orang tua/wali murid secara tatap muka dan publikasi digital di Portal Siswa & Orang Tua.',
                'target' => 'all',
            ],
            [
                'judul' => 'Libur Akhir Semester Ganjil Tahun Ajaran 2026/2027',
                'kategori' => 'libur_semester',
                'mulai' => '2026-12-21',
                'selesai' => '2027-01-02',
                'prioritas' => 2,
                'deskripsi' => 'Periode libur semester 1 bagi seluruh peserta didik. Masuk kembali hari Senin, 4 Januari 2027.',
                'target' => 'all',
            ],

            // Semester 2 & PERIODE RAMADHAN 1448 H (2027)
            [
                'judul' => 'Awal KBM Semester Genap Tahun Ajaran 2026/2027',
                'kategori' => 'mulai_kbm',
                'mulai' => '2027-01-04',
                'selesai' => '2027-01-04',
                'prioritas' => 1,
                'deskripsi' => 'Hari pertama efektif kegiatan belajar mengajar Semester Genap T.A. 2026/2027.',
                'target' => 'all',
            ],
            [
                'judul' => '1 Ramadhan 1448 H: Awal Puasa Ramadhan & Penyesuaian Jam Belajar Madrasah',
                'kategori' => 'kegiatan',
                'mulai' => '2027-02-08',
                'selesai' => '2027-02-08',
                'prioritas' => 1,
                'deskripsi' => 'Marhaban Ya Ramadhan 1448 H. KBM selama bulan suci Ramadhan disesuaikan mulai pukul 08.00 s/d 12.30 WIB dengan fokus penguatan adab dan ibadah.',
                'target' => 'all',
            ],
            [
                'judul' => 'Pesantren Kilat (Sanlat) Ramadhan Terpadu 1448 H',
                'kategori' => 'kegiatan',
                'mulai' => '2027-02-08',
                'selesai' => '2027-02-13',
                'prioritas' => 1,
                'deskripsi' => 'Program intensif keislaman Ramadhan: Fiqih Ibadah Puasa, Sirah Nabawiyah, Tahsin & Tadarus Al-Qur\'an, serta penanaman akhlaqul karimah.',
                'target' => 'all',
            ],
            [
                'judul' => 'Peringatan Nuzulul Qur\'an 17 Ramadhan 1448 H & Doa Bersama',
                'kategori' => 'kegiatan',
                'mulai' => '2027-02-24',
                'selesai' => '2027-02-24',
                'prioritas' => 2,
                'deskripsi' => 'Kajian akbar peringatan Nuzulul Qur\'an dan khataman Al-Qur\'an bersama seluruh santri, guru, dan pegawai.',
                'target' => 'all',
            ],
            [
                'judul' => 'Program I\'tikaf & Qiyamul Lail 10 Malam Terakhir Ramadhan 1448 H',
                'kategori' => 'kegiatan',
                'mulai' => '2027-02-28',
                'selesai' => '2027-03-08',
                'prioritas' => 1,
                'deskripsi' => 'Fasilitasi I\'tikaf, shalat tarawih, tahajud, dan tadarus di Masjid Yayasan untuk santri asrama dan civitas.',
                'target' => 'all',
            ],
            [
                'judul' => 'Posko Pengumpulan & Penyaluran Zakat Fitrah serta Fidyah 1448 H',
                'kategori' => 'kegiatan',
                'mulai' => '2027-03-01',
                'selesai' => '2027-03-05',
                'prioritas' => 2,
                'deskripsi' => 'Penerimaan dan pendistribusian beras zakat fitrah dari civitas sekolah kepada kaum dhu\'afa dan mustahik di sekitar sekolah.',
                'target' => 'all',
            ],
            [
                'judul' => 'Hari Raya Idul Fitri 1 Syawal 1448 H: Taqabbalallahu Minna Wa Minkum',
                'kategori' => 'libur_sekolah',
                'mulai' => '2027-03-09',
                'selesai' => '2027-03-10',
                'prioritas' => 1,
                'deskripsi' => 'Selamat Hari Raya Idul Fitri 1448 H. Semoga amal ibadah puasa dan kebaikan kita diterima Allah Ta\'ala.',
                'target' => 'all',
            ],
            [
                'judul' => 'Libur Hari Raya Idul Fitri 1448 H & Cuti Bersama Civitas',
                'kategori' => 'libur_sekolah',
                'mulai' => '2027-03-09',
                'selesai' => '2027-03-19',
                'prioritas' => 1,
                'deskripsi' => 'Libur panjang hari raya Idul Fitri 1448 H. Seluruh kegiatan KBM ditiadakan dan aktif kembali hari Senin, 22 Maret 2027.',
                'target' => 'all',
            ],
            [
                'judul' => 'KBM Aktif Efektif Pasca Idul Fitri & Halal Bihalal Sekolah',
                'kategori' => 'mulai_kbm',
                'mulai' => '2027-03-22',
                'selesai' => '2027-03-22',
                'prioritas' => 2,
                'deskripsi' => 'Silaturrahim Halal Bihalal awal masuk sekolah dan pelaksanaan KBM reguler.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penilaian Akhir Tahun (PAT) Kenaikan Kelas Tahun Ajaran 2026/2027',
                'kategori' => 'ujian',
                'mulai' => '2027-06-07',
                'selesai' => '2027-06-18',
                'prioritas' => 1,
                'deskripsi' => 'Pelaksanaan evaluasi akhir tahun (PAT) penentu kenaikan kelas dan kelulusan santri/siswa tingkat akhir.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penyerahan Rapor Kenaikan Kelas & Wisuda Santri Angkatan 2027',
                'kategori' => 'terima_rapor',
                'mulai' => '2027-06-25',
                'selesai' => '2027-06-25',
                'prioritas' => 1,
                'deskripsi' => 'Penerimaan Buku Laporan Pendidikan (Rapor Kenaikan Kelas) dan prosesi wisuda tahfizh.',
                'target' => 'all',
            ],
            [
                'judul' => 'Penutupan Tahun Ajaran 2026/2027 & Transisi PPDB 2027/2028',
                'kategori' => 'libur_semester',
                'mulai' => '2027-07-01',
                'selesai' => '2027-07-01',
                'prioritas' => 2,
                'deskripsi' => 'Batas akhir resmi penutupan kalender akademik 2026/2027 dan pembukaan gerbang tahun ajaran baru.',
                'target' => 'all',
            ],
        ];

        foreach ($agendas as $ag) {
            $existing = PengumumanSekolah::where('judul_pengumuman', $ag['judul'])->first();
            $dataTambahan = [
                'kategori' => $ag['kategori'],
                'target_unit' => $ag['target'],
                'target_unit_name' => 'Semua Unit Terpadu',
                'event' => [
                    'start_at' => $ag['mulai'],
                    'end_at' => $ag['selesai'],
                ],
                'color' => $ag['kategori'] === 'libur_sekolah' ? 'rose' : ($ag['kategori'] === 'ujian' ? 'purple' : ($ag['kategori'] === 'mulai_kbm' ? 'emerald' : 'sky')),
            ];

            if ($existing) {
                $existing->update([
                    'isi_pengumuman' => $ag['deskripsi'],
                    'mulai_tampil' => $ag['mulai'] . ' 00:00:00',
                    'selesai_tampil' => $ag['selesai'] . ' 23:59:59',
                    'data_tambahan' => $dataTambahan,
                ]);
            } else {
                PengumumanSekolah::create([
                    'id' => (string) Str::uuid(),
                    'judul_pengumuman' => $ag['judul'],
                    'isi_pengumuman' => $ag['deskripsi'],
                    'target_peran' => 'all',
                    'mulai_tampil' => $ag['mulai'] . ' 00:00:00',
                    'selesai_tampil' => $ag['selesai'] . ' 23:59:59',
                    'prioritas' => $ag['prioritas'],
                    'status_aktif' => true,
                    'id_penerbit' => $publisher?->id,
                    'data_tambahan' => $dataTambahan,
                ]);
            }
        }
    }

    /**
     * 3. Amalan Mutabaah Khusus Ramadhan 1448 H
     */
    private function setupMutabaahRamadhanItems(AcademicYear $ay, Semester $semGenap): void
    {
        $this->command?->info('  [2/7] Mengonfigurasi Indikator Amalan Mutaba\'ah Ramadhan...');

        $ramadhanAgendaItems = [
            ['code' => 'PUASA-RAMADHAN', 'name' => 'Puasa Ramadhan', 'instruction' => 'Laksanakan puasa wajib Ramadhan dengan penuh keimanan.'],
            ['code' => 'TARAWIH-WITIR', 'name' => 'Sholat Tarawih & Witir', 'instruction' => 'Laksanakan shalat tarawih dan witir berjamaah di masjid.'],
            ['code' => 'TADARUS-RAMADHAN', 'name' => 'Tadarus & Khatam Al-Qur\'an', 'instruction' => 'Membaca minimal 1 juz per hari untuk mengkhatamkan Al-Qur\'an.'],
            ['code' => 'SEDEKAH-RAMADHAN', 'name' => 'Sedekah Subuh & Infaq Ramadhan', 'instruction' => 'Berinfaq dan bersedekah di waktu subuh selama Ramadhan.'],
            ['code' => 'ITIKAF-MALAM', 'name' => 'I\'tikaf 10 Malam Terakhir', 'instruction' => 'Menghidupkan malam mencari keutamaan Lailatul Qadar.'],
        ];

        $ibadahCat = DB::table('mutabaah_categories')->where('name', 'like', '%Ibadah%')->first()
            ?? DB::table('mutabaah_categories')->first();

        foreach ($ramadhanAgendaItems as $idx => $rag) {
            $existing = DB::table('mutabaah_agenda_items')->where('code', $rag['code'])->first();
            if (!$existing) {
                DB::table('mutabaah_agenda_items')->insert([
                    'id' => (string) Str::uuid(),
                    'category_id' => $ibadahCat?->id,
                    'name' => $rag['name'],
                    'code' => $rag['code'],
                    'description' => $rag['instruction'],
                    'input_type' => 'checklist',
                    'is_active' => true,
                    'sort_order' => 20 + $idx,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * 4. Presensi Terpartisi: Juli s/d Desember 2026 & Milestone Semester 2
     */
    private function seedPresensiTahunanTerpartisi(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('  [3/7] Mengisi Data Presensi Terpartisi (Juli - Desember 2026)...');

        $students = Student::where('is_active', true)->take(120)->get();
        if ($students->isEmpty()) {
            $students = Student::take(100)->get();
        }

        $teachers = Employee::where('status', 'Aktif')->take(35)->get();

        $attendanceDays = [
            // Juli 2026 (m07)
            ['date' => '2026-07-14', 'month' => 7, 'sem' => $semGanjil],
            ['date' => '2026-07-21', 'month' => 7, 'sem' => $semGanjil],
            ['date' => '2026-07-28', 'month' => 7, 'sem' => $semGanjil],
            // Agustus 2026 (m08)
            ['date' => '2026-08-04', 'month' => 8, 'sem' => $semGanjil],
            ['date' => '2026-08-11', 'month' => 8, 'sem' => $semGanjil],
            ['date' => '2026-08-18', 'month' => 8, 'sem' => $semGanjil],
            ['date' => '2026-08-25', 'month' => 8, 'sem' => $semGanjil],
            // September 2026 (m09)
            ['date' => '2026-09-01', 'month' => 9, 'sem' => $semGanjil],
            ['date' => '2026-09-15', 'month' => 9, 'sem' => $semGanjil],
            ['date' => '2026-09-22', 'month' => 9, 'sem' => $semGanjil],
            ['date' => '2026-09-29', 'month' => 9, 'sem' => $semGanjil],
            // Oktober 2026 (m10)
            ['date' => '2026-10-06', 'month' => 10, 'sem' => $semGanjil],
            ['date' => '2026-10-13', 'month' => 10, 'sem' => $semGanjil],
            ['date' => '2026-10-20', 'month' => 10, 'sem' => $semGanjil],
            ['date' => '2026-10-27', 'month' => 10, 'sem' => $semGanjil],
            // November 2026 (m11)
            ['date' => '2026-11-03', 'month' => 11, 'sem' => $semGanjil],
            ['date' => '2026-11-10', 'month' => 11, 'sem' => $semGanjil],
            ['date' => '2026-11-17', 'month' => 11, 'sem' => $semGanjil],
            ['date' => '2026-11-24', 'month' => 11, 'sem' => $semGanjil],
            // Desember 2026 (m12 - s.d. Rapor)
            ['date' => '2026-12-01', 'month' => 12, 'sem' => $semGanjil],
            ['date' => '2026-12-08', 'month' => 12, 'sem' => $semGanjil],
            ['date' => '2026-12-15', 'month' => 12, 'sem' => $semGanjil],
            // Semester 2 Milestones
            ['date' => '2027-01-12', 'month' => 1, 'sem' => $semGenap],
            ['date' => '2027-02-16', 'month' => 2, 'sem' => $semGenap], // Ramadhan
            ['date' => '2027-03-23', 'month' => 3, 'sem' => $semGenap], // Pasca Idul Fitri
            ['date' => '2027-04-13', 'month' => 4, 'sem' => $semGenap],
            ['date' => '2027-05-11', 'month' => 5, 'sem' => $semGenap],
            ['date' => '2027-06-08', 'month' => 6, 'sem' => $semGenap], // PAT
        ];

        $batchRecords = [];

        foreach ($attendanceDays as $ad) {
            $attDate = $ad['date'];
            $monthNum = $ad['month'];
            $semObj = $ad['sem'];

            // Cek apakah tanggal ini sudah ada data
            $alreadySeeded = DB::table('attendances')
                ->where('attendance_date', $attDate)
                ->where('academic_year_id', $ay->id)
                ->exists();

            if ($alreadySeeded) {
                continue;
            }

            // Presensi Siswa
            foreach ($students as $sIdx => $st) {
                $statusRoll = ($sIdx + $monthNum) % 30;
                $status = ($statusRoll === 0) ? 'Izin' : (($statusRoll === 1) ? 'Sakit' : 'Hadir');
                $isRamadhan = ($attDate >= '2027-02-08' && $attDate <= '2027-03-08');
                $checkIn = $attDate . ($isRamadhan ? ' 07:45:00' : ' 07:05:00');
                $checkOut = $attDate . ($isRamadhan ? ' 12:30:00' : ' 15:45:00');

                $batchRecords[] = [
                    'id' => (string) Str::uuid(),
                    'academic_year_id' => $ay->id,
                    'semester_id' => $semObj->id,
                    'month' => $monthNum,
                    'attendance_date' => $attDate,
                    'student_id' => $st->id,
                    'employee_id' => null,
                    'class_id' => $st->kelas_id,
                    'unit_pendidikan_id' => $st->unit_id,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $status === 'Hadir' ? $checkOut : null,
                    'status' => $status,
                    'tipe_presensi' => 'gerbang',
                    'attendance_method' => ($sIdx % 2 === 0) ? 'RFID' : 'QR',
                    'location' => 'Gerbang Sekolah Utama',
                    'metadata' => json_encode(['method' => 'RFID Tap', 'period' => $isRamadhan ? 'Ramadhan' : 'Reguler']),
                    'created_at' => $checkIn,
                    'updated_at' => $checkOut,
                ];
            }

            // Presensi Pegawai/Guru
            foreach ($teachers as $tIdx => $tc) {
                $isLate = ($tIdx + $monthNum) % 15 === 0;
                $tCheckIn = $attDate . ($isLate ? ' 07:25:00' : ' 06:50:00');
                $tCheckOut = $attDate . ' 16:00:00';

                $batchRecords[] = [
                    'id' => (string) Str::uuid(),
                    'academic_year_id' => $ay->id,
                    'semester_id' => $semObj->id,
                    'month' => $monthNum,
                    'attendance_date' => $attDate,
                    'student_id' => null,
                    'employee_id' => $tc->id,
                    'class_id' => null,
                    'unit_pendidikan_id' => $tc->unit_id,
                    'check_in_time' => $tCheckIn,
                    'check_out_time' => $tCheckOut,
                    'status' => $isLate ? 'TERLAMBAT' : 'HADIR',
                    'tipe_presensi' => 'pegawai',
                    'attendance_method' => 'RFID',
                    'location' => 'Presensi Fingerprint/RFID Gate',
                    'metadata' => json_encode(['method' => 'RFID', 'notes' => 'Presensi Harian']),
                    'created_at' => $tCheckIn,
                    'updated_at' => $tCheckOut,
                ];
            }

            // Flush chunk jika sudah melebihi 250 baris
            if (count($batchRecords) >= 250) {
                DB::table('attendances')->insert($batchRecords);
                $batchRecords = [];
            }
        }

        if (!empty($batchRecords)) {
            DB::table('attendances')->insert($batchRecords);
        }
    }

    /**
     * 5. Penugasan LMS, Pengumpulan & Nilai Siswa (Juli - Desember 2026)
     */
    private function seedPenugasanDanNilaiLms(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('  [4/7] Menyiapkan Penugasan LMS & Penilaian Siswa...');

        $kelasList = Kelas::where('status', 'aktif')->take(6)->get();
        if ($kelasList->isEmpty()) {
            $kelasList = Kelas::take(6)->get();
        }

        $teachers = Employee::where('status', 'Aktif')->take(6)->get();

        $tugasTemplates = [
            ['judul' => 'Tugas 1: Adab Menuntut Ilmu & Menghormati Guru', 'tipe' => 'Individu', 'bobot' => 15, 'tgl' => '2026-08-10', 'dl' => '2026-08-20'],
            ['judul' => 'Tugas 2: Kaidah Tajwid & Hukum Nun Mati / Tanwin', 'tipe' => 'Individu', 'bobot' => 15, 'tgl' => '2026-09-05', 'dl' => '2026-09-15'],
            ['judul' => 'Tugas 3: Ringkasan Sirah Nabawiyah & Perjuangan Sahabat', 'tipe' => 'Kelompok', 'bobot' => 20, 'tgl' => '2026-10-10', 'dl' => '2026-10-22'],
            ['judul' => 'Tugas 4: Fiqih Ibadah & Tata Cara Shalat Khusyuk', 'tipe' => 'Individu', 'bobot' => 20, 'tgl' => '2026-11-05', 'dl' => '2026-11-18'],
            ['judul' => 'Tugas Akhir Semester: Proyek Akhlak & Adab Keseharian', 'tipe' => 'Proyek', 'bobot' => 30, 'tgl' => '2026-11-25', 'dl' => '2026-12-05'],
        ];

        foreach ($kelasList as $kIdx => $kls) {
            $teacher = $teachers[$kIdx % count($teachers)] ?? $teachers->first();
            $subject = Subject::where('unit_pendidikan_id', $kls->unit_pendidikan_id)->first() ?? Subject::first();

            if (!$teacher || !$subject) continue;

            $students = Student::where('kelas_id', $kls->id)->take(15)->get();

            foreach ($tugasTemplates as $tIdx => $tt) {
                $penugasan = LmsPenugasan::firstOrCreate(
                    [
                        'kelas_id' => $kls->id,
                        'judul_tugas' => $tt['judul'] . ' - ' . $kls->nama_kelas,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'mata_pelajaran_id' => $subject->id,
                        'guru_id' => $teacher->id,
                        'semester_id' => $semGanjil->id,
                        'tahun_ajaran_id' => $ay->id,
                        'deskripsi' => 'Silakan kerjakan tugas dengan teliti dan kumpulkan sebelum batas waktu berakhir.',
                        'tipe_tugas' => $tt['tipe'],
                        'nilai_maksimal' => 100,
                        'bobot_persen' => $tt['bobot'],
                        'tanggal_mulai' => $tt['tgl'],
                        'deadline' => $tt['dl'],
                        'is_published' => true,
                    ]
                );

                // Buat Pengumpulan Tugas dari Siswa
                foreach ($students as $sIdx => $st) {
                    $score = 80 + (($sIdx * 3 + $tIdx * 4) % 19); // 80 - 98
                    $isLate = ($sIdx % 7 === 0);
                    $submitDate = Carbon::parse($tt['dl'])->subDays($isLate ? -1 : 2)->toDateTimeString();

                    LmsPengumpulanTugas::firstOrCreate(
                        [
                            'penugasan_id' => $penugasan->id,
                            'siswa_id' => $st->id,
                        ],
                        [
                            'jawaban_teks' => "Telah selesai dikerjakan sesuai petunjuk guru. Mohon bimbingan dan koreksinya.",
                            'file_path' => null,
                            'url_link' => null,
                            'status' => $isLate ? 'terlambat' : 'tepat_waktu',
                            'waktu_kumpul' => $submitDate,
                            'nilai_guru' => $score,
                            'catatan_guru' => $score >= 90 ? 'Mumtaz! Jawaban sangat rapi dan komprehensif.' : 'Bagus sekali, terus tingkatkan pemahaman materi.',
                            'waktu_dinilai' => Carbon::parse($submitDate)->addDays(1)->toDateTimeString(),
                            'dinilai_oleh' => $teacher->id,
                        ]
                    );
                }
            }
        }
    }

    /**
     * 6. Rapor PAS Semester 1 (Desember 2026) & PAT (Juni 2027)
     */
    private function seedRaporPasDanPat(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('  [5/7] Menerbitkan Rapor PAS (Desember 2026) & Evaluasi Tahunan...');

        $students = Student::where('is_active', true)->take(150)->get();
        if ($students->isEmpty()) {
            $students = Student::take(100)->get();
        }

        $teachers = Employee::where('status', 'Aktif')->get();

        foreach ($students as $sIdx => $st) {
            $wali = $teachers->where('unit_id', $st->unit_id)->first() ?? $teachers->first();
            $avgScore = 83.5 + ($sIdx % 14); // 83.5 - 96.5
            $totalHadir = 105 - ($sIdx % 5);
            $totalIzin = ($sIdx % 4);
            $totalSakit = ($sIdx % 3);

            $notesPas = [
                'Alhamdulillah, ananda menunjukkan perkembangan adab, akhlaq, dan tahfizh yang sangat menggembirakan selama Semester 1.',
                'Sangat aktif dalam KBM, hafalan Al-Qur\'an lancar dengan makhraj yang baik. Pertahankan di semester berikutnya.',
                'Ananda memiliki semangat belajar yang tinggi dan disiplin shalat berjamaah yang baik.',
                'Prestasi akademik sangat baik, terus dimaksimalkan potensi kepemimpinan dan hafalan Al-Qur\'an.',
            ];

            // Rapor PAS (Desember 2026)
            LmsRapor::updateOrCreate(
                [
                    'siswa_id' => $st->id,
                    'semester_id' => $semGanjil->id,
                    'tahun_ajaran_id' => $ay->id,
                ],
                [
                    'kelas_id' => $st->kelas_id,
                    'guru_wali_id' => $wali?->id,
                    'total_nilai' => $avgScore * 8,
                    'rata_rata' => $avgScore,
                    'peringkat_kelas' => ($sIdx % 30) + 1,
                    'total_siswa_kelas' => 30,
                    'total_mapel' => 8,
                    'mapel_lulus' => 8,
                    'mapel_tidak_lulus' => 0,
                    'total_hari_efektif' => 110,
                    'total_hadir' => $totalHadir,
                    'total_izin' => $totalIzin,
                    'total_sakit' => $totalSakit,
                    'total_alpha' => 0,
                    'catatan_wali_kelas' => $notesPas[$sIdx % count($notesPas)],
                    'catatan_kepala_sekolah' => 'Barakallahu fiikum. Pertahankan prestasi dan tingkatkan ketakwaan kepada Allah Ta\'ala.',
                    'status_rapor' => 'terbit',
                    'tanggal_terbit' => '2026-12-18',
                    'sudah_dilihat_ortu' => true,
                ]
            );

            // Rapor Kenaikan Kelas PAT (Juni 2027)
            LmsRapor::updateOrCreate(
                [
                    'siswa_id' => $st->id,
                    'semester_id' => $semGenap->id,
                    'tahun_ajaran_id' => $ay->id,
                ],
                [
                    'kelas_id' => $st->kelas_id,
                    'guru_wali_id' => $wali?->id,
                    'total_nilai' => ($avgScore + 1.5) * 8,
                    'rata_rata' => $avgScore + 1.5,
                    'peringkat_kelas' => (($sIdx + 2) % 30) + 1,
                    'total_siswa_kelas' => 30,
                    'total_mapel' => 8,
                    'mapel_lulus' => 8,
                    'mapel_tidak_lulus' => 0,
                    'total_hari_efektif' => 115,
                    'total_hadir' => 110 - ($sIdx % 4),
                    'total_izin' => ($sIdx % 3),
                    'total_sakit' => ($sIdx % 2),
                    'total_alpha' => 0,
                    'catatan_wali_kelas' => 'Selamat! Berdasarkan hasil evaluasi belajar 1 tahun penuh, ananda dinyatakan NAIK KELAS dengan predikat Mumtaz.',
                    'catatan_kepala_sekolah' => 'Keputusan: Dinyatakan NAIK KELAS ke jenjang berikutnya. Semoga semakin berprestasi.',
                    'status_rapor' => 'terbit',
                    'tanggal_terbit' => '2027-06-25',
                    'sudah_dilihat_ortu' => true,
                ]
            );
        }
    }

    /**
     * 7. Setoran Tahfizh Harian & Periode Ramadhan
     */
    private function seedTahfizhSetoranTahunan(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('  [6/7] Menyiapkan Setoran Tahfizh Tahunan & Periode Ramadhan...');

        $students = Student::where('is_active', true)->take(100)->get();
        if ($students->isEmpty()) {
            $students = Student::take(80)->get();
        }

        $teachers = \App\Models\Teacher::all();
        if ($teachers->isEmpty()) {
            $teachers = Employee::where('status', 'Aktif')->take(10)->get();
        }

        $tahfizhDates = [
            // Semester 1 (Juli - Desember 2026)
            ['date' => '2026-07-25', 'type' => 'Ziyadah', 'sem' => $semGanjil],
            ['date' => '2026-08-20', 'type' => 'Murajaah', 'sem' => $semGanjil],
            ['date' => '2026-09-18', 'type' => 'Ziyadah', 'sem' => $semGanjil],
            ['date' => '2026-10-22', 'type' => 'Murajaah', 'sem' => $semGanjil],
            ['date' => '2026-11-19', 'type' => 'Ziyadah', 'sem' => $semGanjil],
            ['date' => '2026-12-10', 'type' => 'Ujian', 'sem' => $semGanjil], // Ujian PAS Tahfizh
            // Semester 2 Termasuk Ramadhan 1448 H (Februari 2027)
            ['date' => '2027-01-20', 'type' => 'Ziyadah', 'sem' => $semGenap],
            ['date' => '2027-02-15', 'type' => 'Tasmi', 'sem' => $semGenap], // Tasmi Ramadhan
            ['date' => '2027-02-26', 'type' => 'Murajaah', 'sem' => $semGenap], // Khataman Ramadhan
            ['date' => '2027-04-20', 'type' => 'Ziyadah', 'sem' => $semGenap],
            ['date' => '2027-05-20', 'type' => 'Murajaah', 'sem' => $semGenap],
            ['date' => '2027-06-12', 'type' => 'Ujian', 'sem' => $semGenap], // Ujian PAT Tahfizh
        ];

        $surahPool = self::SURAH_LIST;

        foreach ($students as $sIdx => $st) {
            $teacher = $teachers[$sIdx % count($teachers)] ?? $teachers->first();

            foreach ($tahfizhDates as $tIdx => $td) {
                $surah = $surahPool[($sIdx + $tIdx * 3) % count($surahPool)];
                $depositDate = $td['date'];
                $isRamadhan = ($depositDate >= '2027-02-08' && $depositDate <= '2027-03-08');

                $ayahStart = 1;
                $ayahEnd = min($surah['ayat'], 10 + ($sIdx % 15));

                $exists = TahfizhRecord::where('student_id', $st->id)
                    ->where('deposit_date', $depositDate)
                    ->where('surah_name', $surah['nama'])
                    ->exists();

                if ($exists) continue;

                TahfizhRecord::create([
                    'academic_year_id' => $ay->id,
                    'semester_id' => $td['sem']->id,
                    'deposit_date' => $depositDate,
                    'student_id' => $st->id,
                    'class_id' => $st->kelas_id,
                    'teacher_id' => $teacher?->id,
                    'surah_name' => $surah['nama'],
                    'ayah_start' => $ayahStart,
                    'ayah_end' => $ayahEnd,
                    'line_count' => $ayahEnd - $ayahStart + 1,
                    'status' => 'approved',
                    'notes' => $isRamadhan ? 'Setoran spesial program Tasmi\' Ramadhan 1448 H.' : null,
                    'metadata' => json_encode([
                        'type' => $td['type'],
                        'surah_number' => $surah['nomor'],
                        'juz' => $surah['juz'],
                        'kelancaran' => ($sIdx % 5 === 0) ? 'Jayyid Jiddan' : 'Mumtaz',
                        'program' => $isRamadhan ? 'Semarak Ramadhan 1448 H' : 'Reguler',
                    ]),
                ]);
            }
        }
    }

    /**
     * 8. Mutabaah Yaumiyyah Harian & Periode Ramadhan 1448 H
     */
    private function seedMutabaahYaumiyyahTahunan(AcademicYear $ay, Semester $semGanjil, Semester $semGenap): void
    {
        $this->command?->info('  [7/7] Mengisi Rekap Mutaba\'ah Yaumiyyah Harian & Amalan Ramadhan...');

        $sampleStudents = Student::where('is_active', true)->take(30)->get();
        if ($sampleStudents->isEmpty()) {
            $sampleStudents = Student::take(20)->get();
        }

        $template = MutabaahTemplate::first() ?? MutabaahTemplate::where('is_active', true)->first();
        $supervisor = MutabaahSupervisorAssignment::first();

        // Fallback: Jika belum ada supervisor assignment, buatkan otomatis
        if (!$supervisor && $template) {
            $employee = Employee::where('status', 'Aktif')->first();
            $unit = EducationUnit::first();

            if ($employee && $unit) {
                $supervisor = MutabaahSupervisorAssignment::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'education_unit_id' => $unit->id,
                        'academic_year_id' => $ay->id,
                        'semester_id' => $semGanjil->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'supervisor_type' => 'pembimbing',
                        'template_id' => $template->id,
                        'start_date' => $ay->start_date ?? '2026-07-01',
                        'end_date' => $ay->end_date ?? '2027-06-30',
                        'is_primary' => true,
                        'can_input' => true,
                        'can_edit' => true,
                        'can_finalize' => true,
                        'can_view_report' => true,
                        'status' => 'active',
                    ]
                );
            }
        }

        // Guard: Lewati jika master template atau supervisor belum siap
        if (!$template || !$supervisor) {
            $this->command?->warn('  ⚠ MutabaahTemplate atau MutabaahSupervisorAssignment belum tersedia, melewati seeding mutabaah.');
            return;
        }

        $mutabaahDates = [
            // Semester 1 (Juli - Desember 2026)
            ['date' => '2026-07-20', 'sem' => $semGanjil, 'score' => 90.0],
            ['date' => '2026-08-17', 'sem' => $semGanjil, 'score' => 92.5],
            ['date' => '2026-09-09', 'sem' => $semGanjil, 'score' => 88.0],
            ['date' => '2026-10-14', 'sem' => $semGanjil, 'score' => 95.0],
            ['date' => '2026-11-18', 'sem' => $semGanjil, 'score' => 94.0],
            ['date' => '2026-12-14', 'sem' => $semGanjil, 'score' => 96.0],
            // Ramadhan 1448 H (Februari - Maret 2027)
            ['date' => '2027-02-10', 'sem' => $semGenap, 'score' => 98.0], // Hari ke-3 Ramadhan
            ['date' => '2027-02-20', 'sem' => $semGenap, 'score' => 100.0], // Pertengahan Ramadhan
            ['date' => '2027-03-02', 'sem' => $semGenap, 'score' => 98.5], // 10 Malam Terakhir
            ['date' => '2027-04-14', 'sem' => $semGenap, 'score' => 92.0],
            ['date' => '2027-05-18', 'sem' => $semGenap, 'score' => 94.0],
            ['date' => '2027-06-15', 'sem' => $semGenap, 'score' => 95.0],
        ];

        foreach ($sampleStudents as $sIdx => $st) {
            foreach ($mutabaahDates as $md) {
                $actDate = $md['date'];
                $isRamadhan = ($actDate >= '2027-02-08' && $actDate <= '2027-03-08');

                $header = MutabaahDailyHeader::firstOrCreate(
                    [
                        'student_id' => $st->id,
                        'activity_date' => $actDate,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'template_id' => $template->id,
                        'supervisor_assignment_id' => $supervisor->id,
                        'education_unit_id' => $st->unit_id ?? $supervisor->education_unit_id,
                        'kelas_id' => $st->kelas_id,
                        'academic_year_id' => $ay->id,
                        'semester_id' => $md['sem']->id,
                        'status' => 'parent_signed',
                        'total_items' => $isRamadhan ? 10 : 7,
                        'good_count' => $isRamadhan ? 9 : 6,
                        'less_count' => 1,
                        'not_done_count' => 0,
                        'score' => $md['score'],
                        'supervisor_notes' => $isRamadhan ? 'Alhamdulillah, puasa dan shalat tarawih tuntas dikerjakan.' : 'Amalan yaumiyyah terlaksana dengan baik.',
                        'finalized_at' => $actDate . ' 21:00:00',
                    ]
                );
            }
        }
    }
}
