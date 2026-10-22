<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CapaianPembelajaran;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsDiskusi;
use App\Models\LmsDiskusiKomentar;
use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsAhmadFarhanTahfizhSeeder extends Seeder
{
    public function run(): void
    {
        $teacherUser = User::where('email', 'ahmad.farhan@dareliman.sch.id')->first();
        if (! $teacherUser) {
            $this->command->error('User ahmad.farhan@dareliman.sch.id tidak ditemukan.');
            return;
        }

        $employee = Employee::where('user_id', $teacherUser->id)->first();
        if (! $employee) {
            $this->command->error('Employee untuk Ahmad Farhan tidak ditemukan.');
            return;
        }

        $unit = $employee->unit ?: EducationUnit::where('name', 'like', '%SDIT 1%')->first();
        if (! $unit) {
            $this->command->error('Unit pendidikan SDIT 1 tidak ditemukan.');
            return;
        }

        $subject = Subject::where('unit_pendidikan_id', $unit->id)
            ->where(function ($q) use ($employee) {
                $q->where('kode_mapel', 'MAPEL-TAHFIZH-SD')
                  ->orWhere('guru_pengampu_id', $employee->id)
                  ->orWhere('nama_mapel', 'like', '%Tahfizh%');
            })
            ->first();

        if (! $subject) {
            $subject = Subject::create([
                'id' => (string) Str::uuid(),
                'code' => 'MAPEL-TAHFIZH-SD',
                'kode_mapel' => 'MAPEL-TAHFIZH-SD',
                'name' => "Tahfizh Al-Qur'an",
                'nama_mapel' => "Tahfizh Al-Qur'an",
                'nama_singkat' => 'TAHFIZH',
                'unit_pendidikan_id' => $unit->id,
                'guru_pengampu_id' => $employee->id,
                'kelompok_mapel' => 'Kekhasan SIT',
                'kategori' => 'Tahfizh/Diniyah',
                'jenjang' => 'SD',
                'tingkat_kelas' => 'All',
                'jam_pelajaran' => 6,
                'kkm' => 80,
                'status' => true,
                'deskripsi' => "Hafalan Al-Qur'an Juz 30, 29, dan 28 serta makharijul huruf dan tajwid terpadu.",
            ]);
        } else {
            if (! $subject->guru_pengampu_id) {
                $subject->update(['guru_pengampu_id' => $employee->id]);
            }
        }

        $academicYear = AcademicYear::where('is_active', true)->first()
            ?: AcademicYear::where('name', 'like', '%2026/2027%')->first()
            ?: AcademicYear::latest()->first();

        $semesters = Semester::where('academic_year_id', $academicYear?->id)->get();
        $semGanjil = $semesters->first(fn ($s) => str_contains(strtolower($s->name), 'ganjil') || str_contains(strtolower($s->name), '1')) ?: $semesters->first();
        $semGenap = $semesters->first(fn ($s) => str_contains(strtolower($s->name), 'genap') || str_contains(strtolower($s->name), '2')) ?: $semesters->last();

        $classes = Kelas::where('unit_pendidikan_id', $unit->id)->orderBy('tingkat')->get();
        $kurikulumId = $subject->kurikulum_id;

        // 1. Pastikan Capaian Pembelajaran (CP) untuk Tahfizh Al-Qur'an SDIT 1
        $cpDefinitions = [
            [
                'fase' => 'A',
                'kelas_target' => 'Kelas 1-2',
                'kode_cp' => 'CP-THF-FASE-A',
                'nama_cp' => 'Makharijul Huruf Dasar dan Hafalan Juz 30 Surah Pendek',
                'deskripsi' => "Peserta didik mampu melafalkan makharijul huruf hijaiyah secara fasih, mengenal adab tilawah Al-Qur'an, serta menghafal Surah An-Nas sampai Al-A'la dengan baik.",
            ],
            [
                'fase' => 'B',
                'kelas_target' => 'Kelas 3-4',
                'kode_cp' => 'CP-THF-FASE-B',
                'nama_cp' => 'Hukum Tajwid Dasar dan Hafalan Juz 30 Menengah',
                'deskripsi' => "Peserta didik mampu menerapkan hukum tajwid dasar (Ghunnah, Qalqalah, Mad Thabi'i) dan menghafal Surah At-Thariq sampai An-Naba' dengan lancar dan tertib.",
            ],
            [
                'fase' => 'C',
                'kelas_target' => 'Kelas 5-6',
                'kode_cp' => 'CP-THF-FASE-C',
                'nama_cp' => 'Tajwid Lanjutan, Mutaba\'ah Hafalan Juz 29 dan Tasmi\' Juz 30',
                'deskripsi' => "Peserta didik mampu melafalkan hafalan Juz 29 secara mutqin, menerapkan hukum nun sukun, mim sukun, dan mad far'i, serta menuntaskan tasmi' Juz 30.",
            ],
        ];

        $cpModels = [];
        foreach ($cpDefinitions as $idx => $def) {
            $cp = CapaianPembelajaran::firstOrCreate(
                [
                    'mata_pelajaran_id' => $subject->id,
                    'fase' => $def['fase'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'kurikulum_id' => $kurikulumId,
                    'unit_pendidikan_id' => $unit->id,
                    'tahun_ajaran_id' => $academicYear?->id,
                    'kode_cp' => $def['kode_cp'],
                    'nama_cp' => $def['nama_cp'],
                    'deskripsi' => $def['deskripsi'],
                    'kelas_target' => $def['kelas_target'],
                    'urutan' => $idx + 1,
                    'status' => true,
                    'created_by' => $teacherUser->id,
                ]
            );
            $cpModels[$def['fase']] = $cp;
        }

        // 2. Pastikan Tujuan Pembelajaran (TP)
        $tpCatalog = [
            'A' => [
                ['kode' => 'TP-THF-A01', 'nama' => 'Melafalkan makhraj huruf hijaiyah halqiyah dan lisaniyah dengan fasih'],
                ['kode' => 'TP-THF-A02', 'nama' => 'Menghafal Surah An-Nas, Al-Falaq, dan Al-Ikhlas dengan tartil'],
                ['kode' => 'TP-THF-A03', 'nama' => 'Menerapkan adab tilawah dan doa pembuka/penutup Al-Qur\'an'],
                ['kode' => 'TP-THF-A04', 'nama' => 'Menghafal Surah Al-Lahab, An-Nashr, dan Al-Kafirun dengan mutqin'],
                ['kode' => 'TP-THF-A05', 'nama' => 'Menghafal Surah Al-Kautsar, Al-Ma\'un, dan Quraisy dengan lancar'],
                ['kode' => 'TP-THF-A06', 'nama' => 'Menghafal Surah Al-Fil, Al-Humazah, dan Al-\'Ashr serta muraja\'ah rutin'],
            ],
            'B' => [
                ['kode' => 'TP-THF-B01', 'nama' => 'Menerapkan kaidah Ghunnah Musyaddadah dan Qalqalah Kubra/Sughra'],
                ['kode' => 'TP-THF-B02', 'nama' => 'Menghafal Surah At-Takatsur, Al-Qari\'ah, dan Al-\'Adiyat secara tartil'],
                ['kode' => 'TP-THF-B03', 'nama' => 'Menghafal Surah Al-Zalzalah, Al-Bayyinah, dan Al-Qadr dengan kaidah tajwid'],
                ['kode' => 'TP-THF-B04', 'nama' => 'Menerapkan hukum Mad Thabi\'i 2 harakat dengan konsisten saat tilawah'],
                ['kode' => 'TP-THF-B05', 'nama' => 'Menghafal Surah Al-\'Alaq, At-Tin, dan Al-Insyirah dengan makhraj sempurna'],
                ['kode' => 'TP-THF-B06', 'nama' => 'Menghafal Surah Adh-Dhuha dan Asy-Syams serta muraja\'ah pekanan'],
            ],
            'C' => [
                ['kode' => 'TP-THF-C01', 'nama' => 'Menerapkan hukum Nun Sukun dan Tanwin (Idzhar, Idgham, Ikhfa, Iqlab)'],
                ['kode' => 'TP-THF-C02', 'nama' => 'Menghafal Surah Al-Balad, Al-Fajr, dan Al-Ghasyiyah dengan tajwid mutqin'],
                ['kode' => 'TP-THF-C03', 'nama' => 'Menerapkan hukum Mim Sakinah dan Ahkam Al-Madd saat membaca ayat panjang'],
                ['kode' => 'TP-THF-C04', 'nama' => 'Menghafal Surah Al-A\'la, At-Thariq, dan Al-Buruj secara tartil'],
                ['kode' => 'TP-THF-C05', 'nama' => 'Menghafal Surah Al-Insyiqaq, Al-Muthaffifin, dan Al-Infithar tanpa keraguan'],
                ['kode' => 'TP-THF-C06', 'nama' => 'Menuntaskan hafalan Surah At-Takwir, \'Abasa, An-Nazi\'at, dan An-Naba\' serta tasmi\' penuh'],
            ],
        ];

        $tpModels = [];
        foreach ($tpCatalog as $fase => $tps) {
            $cp = $cpModels[$fase];
            foreach ($tps as $u => $tpItem) {
                $tp = TujuanPembelajaran::firstOrCreate(
                    [
                        'cp_id' => $cp->id,
                        'kode_tp' => $tpItem['kode'],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'nama_tp' => $tpItem['nama'],
                        'deskripsi' => $tpItem['nama'],
                        'alokasi_waktu_jp' => 2,
                        'urutan' => $u + 1,
                        'status' => true,
                        'created_by' => $teacherUser->id,
                    ]
                );
                $tpModels[$fase][] = $tp;
            }
        }

        // 3. Buat 36 Modul Ajar (18 Semester Ganjil & 18 Semester Genap 2026/2027)
        $modulTopics = [
            // Semester Ganjil (Pekan 1 - 18, Juli - Desember 2026)
            ['pekan' => 1, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 0, 'judul' => 'Adab Mulia Bersama Al-Qur\'an dan Makharijul Huruf Halqiyah', 'tp_idx' => 0, 'surah' => 'Huruf Halqiyah (Hamzah, Ha, \'Ain, Ha, Ghain, Kha)'],
            ['pekan' => 2, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 0, 'judul' => 'Talaqqi dan Tahfizh Surah An-Nas & Al-Falaq dengan Makhraj Fashih', 'tp_idx' => 1, 'surah' => 'Surah An-Nas dan Al-Falaq'],
            ['pekan' => 3, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 0, 'judul' => 'Pemantapan Hafalan Surah Al-Ikhlas & Al-Lahab dengan Kaidah Tartil', 'tp_idx' => 1, 'surah' => 'Surah Al-Ikhlas dan Al-Lahab'],
            ['pekan' => 4, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 1, 'judul' => 'Tahfizh Surah An-Nashr & Al-Kafirun dan Penerapan Ghunnah Musyaddadah', 'tp_idx' => 3, 'surah' => 'Surah An-Nashr dan Al-Kafirun'],
            ['pekan' => 5, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 1, 'judul' => 'Tahfizh Surah Al-Kautsar & Al-Ma\'un dan Pengenalan Huruf Syafawiyah', 'tp_idx' => 4, 'surah' => 'Surah Al-Kautsar dan Al-Ma\'un'],
            ['pekan' => 6, 'sem' => 'ganjil', 'fase' => 'A', 'kelas_idx' => 1, 'judul' => 'Muraja\'ah Terpadu Surah An-Nas s/d Al-Ma\'un Berpasangan (Tasmi\' Zaujiy)', 'tp_idx' => 5, 'surah' => 'Kompilasi Surah Pendek'],
            ['pekan' => 7, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Hukum Qalqalah (Sughra dan Kubra) pada Surah Quraisy dan Al-Fil', 'tp_idx' => 0, 'surah' => 'Surah Quraisy dan Al-Fil'],
            ['pekan' => 8, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Tahfizh Surah Al-Humazah dan Al-\'Ashr dengan Ketepatan Harakat', 'tp_idx' => 1, 'surah' => 'Surah Al-Humazah dan Al-\'Ashr'],
            ['pekan' => 9, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Tahfizh Surah At-Takatsur dan Pengenalan Mad Thabi\'i 2 Harakat', 'tp_idx' => 3, 'surah' => 'Surah At-Takatsur'],
            ['pekan' => 10, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Hafalan Surah Al-Qari\'ah dan Penerapan Waqaf & Ibtida\' yang Tepat', 'tp_idx' => 1, 'surah' => 'Surah Al-Qari\'ah'],
            ['pekan' => 11, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Tahfizh Surah Al-\'Adiyat dengan Irama Tartil dan Penekanan Huruf Isti\'la', 'tp_idx' => 1, 'surah' => 'Surah Al-\'Adiyat'],
            ['pekan' => 12, 'sem' => 'ganjil', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Simulasi Penilaian Tengah Semester (PTS): Setoran Mutqin 10 Surah Pendek', 'tp_idx' => 5, 'surah' => 'Evaluasi Formatif PTS'],
            ['pekan' => 13, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Hukum Nun Sukun & Tanwin Bagian I: Idzhar Halqi dan Idgham Bighunnah', 'tp_idx' => 0, 'surah' => 'Surah Al-Zalzalah dan Al-Bayyinah'],
            ['pekan' => 14, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Tahfizh Surah Al-Bayyinah Ayat 1-8 dengan Tajwid Idgham Bilaghunnah & Iqlab', 'tp_idx' => 0, 'surah' => 'Surah Al-Bayyinah Penuh'],
            ['pekan' => 15, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Tahfizh Surah Al-Qadr dan Al-\'Alaq Ayat 1-10 dengan Ikhfa Haqiqi', 'tp_idx' => 0, 'surah' => 'Surah Al-Qadr dan Al-\'Alaq'],
            ['pekan' => 16, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah At-Tin & Al-Insyirah serta Penjelasan Sifat Huruf Hams & Jahr', 'tp_idx' => 1, 'surah' => 'Surah At-Tin dan Al-Insyirah'],
            ['pekan' => 17, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah Adh-Dhuha dan Asy-Syams dengan Pembiasaan Lagu Hijaz & Kurdi', 'tp_idx' => 1, 'surah' => 'Surah Adh-Dhuha dan Asy-Syams'],
            ['pekan' => 18, 'sem' => 'ganjil', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tasmi\' Akbar Akhir Semester Ganjil (PAS): Ujian Komprehensif Surah Pilihan', 'tp_idx' => 5, 'surah' => 'Evaluasi Sumatif PAS Semester 1'],

            // Semester Genap (Pekan 19 - 36, Januari - Juni 2027)
            ['pekan' => 19, 'sem' => 'genap', 'fase' => 'A', 'kelas_idx' => 0, 'judul' => 'Penguatan Makhraj Huruf Lisaniyah (Ta, Tha, Jim, Dal, Dzal, Ra, Zay)', 'tp_idx' => 0, 'surah' => 'Huruf Lisaniyah Dasar'],
            ['pekan' => 20, 'sem' => 'genap', 'fase' => 'A', 'kelas_idx' => 0, 'judul' => 'Muraja\'ah dan Pengayaan Surah Al-Balad Ayat 1-10 dengan Harakat Sempurna', 'tp_idx' => 2, 'surah' => 'Surah Al-Balad Bagian I'],
            ['pekan' => 21, 'sem' => 'genap', 'fase' => 'A', 'kelas_idx' => 1, 'judul' => 'Tahfizh Surah Al-Balad Ayat 11-20 dan Praktik Tilawah Berjamaah', 'tp_idx' => 4, 'surah' => 'Surah Al-Balad Bagian II'],
            ['pekan' => 22, 'sem' => 'genap', 'fase' => 'A', 'kelas_idx' => 1, 'judul' => 'Tahfizh Surah Al-Fajr Ayat 1-15 dengan Pengenalan Qalqalah pada Ayat Singkat', 'tp_idx' => 4, 'surah' => 'Surah Al-Fajr Bagian I'],
            ['pekan' => 23, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Tahfizh Surah Al-Fajr Ayat 16-30 dan Pemahaman Nilai Jiwa yang Tenang', 'tp_idx' => 1, 'surah' => 'Surah Al-Fajr Tuntas'],
            ['pekan' => 24, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Tahfizh Surah Al-Ghasyiyah Ayat 1-16 dan Pengenalan Tanda Waqaf Lazim', 'tp_idx' => 1, 'surah' => 'Surah Al-Ghasyiyah Bagian I'],
            ['pekan' => 25, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 2, 'judul' => 'Tahfizh Surah Al-Ghasyiyah Ayat 17-26 dan Tadabbur Ciptaan Allah Ta\'ala', 'tp_idx' => 1, 'surah' => 'Surah Al-Ghasyiyah Tuntas'],
            ['pekan' => 26, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Hukum Mim Sukun (Ikhfa Syafawi, Idgham Mimi, Idzhar Syafawi) pada Surah Al-A\'la', 'tp_idx' => 3, 'surah' => 'Surah Al-A\'la Ayat 1-10'],
            ['pekan' => 27, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Tahfizh Surah Al-A\'la Tuntas dan Pembiasaan Muraja\'ah Mandiri Harian', 'tp_idx' => 3, 'surah' => 'Surah Al-A\'la Penuh'],
            ['pekan' => 28, 'sem' => 'genap', 'fase' => 'B', 'kelas_idx' => 3, 'judul' => 'Simulasi Penilaian Tengah Semester Genap (PTS 2): Uji Mutqin Surah Menengah', 'tp_idx' => 5, 'surah' => 'Evaluasi Formatif PTS Genap'],
            ['pekan' => 29, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Tahfizh Surah At-Thariq dan Kaidah Mad Wajib Muttashil 4-5 Harakat', 'tp_idx' => 3, 'surah' => 'Surah At-Thariq'],
            ['pekan' => 30, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Tahfizh Surah Al-Buruj Ayat 1-11 dan Kisah Ashabul Ukhdud dalam Tilawah', 'tp_idx' => 3, 'surah' => 'Surah Al-Buruj Bagian I'],
            ['pekan' => 31, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 4, 'judul' => 'Tahfizh Surah Al-Buruj Ayat 12-22 dengan Tajwid Mad Jaiz Munfashil', 'tp_idx' => 3, 'surah' => 'Surah Al-Buruj Tuntas'],
            ['pekan' => 32, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah Al-Insyiqaq dan Penerapan Sujud Tilawah pada Ayat Sajdah', 'tp_idx' => 4, 'surah' => 'Surah Al-Insyiqaq'],
            ['pekan' => 33, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah Al-Muthaffifin Ayat 1-17 dan Peringatan Berlaku Jujur', 'tp_idx' => 4, 'surah' => 'Surah Al-Muthaffifin Bagian I'],
            ['pekan' => 34, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah Al-Muthaffifin Ayat 18-36 serta Kaidah Ra Tarqiq dan Tafkhim', 'tp_idx' => 4, 'surah' => 'Surah Al-Muthaffifin Tuntas'],
            ['pekan' => 35, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Tahfizh Surah An-Naba\' Ayat 1-20 dan Penguasaan Nafas Panjang Saat Tilawah', 'tp_idx' => 5, 'surah' => 'Surah An-Naba Bagian I'],
            ['pekan' => 36, 'sem' => 'genap', 'fase' => 'C', 'kelas_idx' => 5, 'judul' => 'Ujian Akhir Tahun (PAT Kenaikan Kelas) & Wisuda Tahfizh Juz 30 Mutqin', 'tp_idx' => 5, 'surah' => 'Ujian Komprehensif Kenaikan Kelas'],
        ];

        $createdModuls = [];
        foreach ($modulTopics as $item) {
            $fase = $item['fase'];
            $targetKelas = $classes[$item['kelas_idx']] ?? $classes->first();
            $sem = $item['sem'] === 'ganjil' ? $semGanjil : $semGenap;
            $cp = $cpModels[$fase];
            $tpsForFase = $tpModels[$fase] ?? [];
            $tp = $tpsForFase[$item['tp_idx']] ?? $tpsForFase[0];

            $semesterCode = $item['sem'] === 'ganjil' ? 'GAN' : 'GEN';
            $kodeModul = sprintf('MOD-SD1-%s-THF-P%02d-%s', $fase, $item['pekan'], strtoupper(substr(md5($item['judul']), 0, 4)));

            $modul = LmsModulAjar::firstOrCreate(
                [
                    'kode_modul' => $kodeModul,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'mata_pelajaran_id' => $subject->id,
                    'guru_id' => $employee->id,
                    'unit_pendidikan_id' => $unit->id,
                    'kurikulum_id' => $kurikulumId,
                    'tahun_ajaran_id' => $academicYear?->id,
                    'semester_id' => $sem?->id,
                    'kelas_id' => $targetKelas?->id,
                    'rombel_id' => $targetKelas?->id,
                    'cp_id' => $cp->id,
                    'tp_id' => $tp->id,
                    'fase' => $fase,
                    'semester' => $item['sem'],
                    'judul_modul' => $item['judul'],
                    'deskripsi' => sprintf('Modul pembelajaran Tahfizh Al-Qur\'an Pekan ke-%d: %s. Fokus hafalan: %s.', $item['pekan'], $item['judul'], $item['surah']),
                    'alokasi_waktu_jp' => 2,
                    'tujuan_pembelajaran' => sprintf('Siswa mampu menguasai %s dengan tartil, makhraj yang benar, dan adab tilawah qur\'ani.', $item['surah']),
                    'profil_pelajar_pancasila' => 'Beriman, Bertakwa kepada Tuhan YME, dan Berakhlak Mulia; Mandiri; Bernalar Kritis',
                    'model_pembelajaran' => 'Talaqqi, Tikrar, dan Musyafahah Terbimbing',
                    'metode_pembelajaran' => 'Drill, Setoran Mandiri, Simak Hafalan Antarteman, Bimbingan Klasikal',
                    'media_pembelajaran' => 'Mushaf Al-Qur\'an Rasm Utsmani, Buku Mutaba\'ah Hafalan, Audio Murottal Syaikh Al-Husary, Lembar Panduan Tajwid',
                    'sumber_belajar' => 'Al-Qur\'an Al-Karim, Kitab Matn Al-Jazariyyah, Modul Tahfizh SIT Yayasan Dar el-Iman',
                    'kegiatan_pendahuluan' => 'Guru membuka majelis dengan salam, doa tholabul ilmi, dan membaca surah pendek bersama-sama selama 10 menit.',
                    'kegiatan_inti' => 'Guru mendemonstrasikan makhraj ayat target (Talaqqi), siswa mengulang secara kelompok dan mandiri (Tikrar), lalu siswa menyetorkan ayat secara bergantian (Musyafahah).',
                    'kegiatan_penutup' => 'Guru memberikan umpan balik koreksi tajwid, mencatat capaian di buku mutaba\'ah, dan menutup dengan doa kafaratul majelis.',
                    'rencana_penilaian' => 'Asesmen Formatif: Observasi kefasihan makhraj dan kelancaran setoran; Asesmen Sumatif: Uji tasmi\' hafalan akhir pekan.',
                    'refleksi_guru' => 'Mengevaluasi kesiapan santri dalam mengingat ayat-ayat yang serupa (mutasyabihat) dan ketepatan tempo bacaan tahqiq/tartil.',
                    'status' => 'published',
                    'created_by' => $teacherUser->id,
                ]
            );

            $createdModuls[] = [
                'modul' => $modul,
                'pekan' => $item['pekan'],
                'surah' => $item['surah'],
            ];
        }

        // 4. Buat Materi Belajar (lms_materi) untuk setiap Modul Ajar
        $materiTypes = [
            ['tipe' => 'teks', 'tipe_materi' => 'teks', 'prefix' => 'Ringkasan Materi & Kaidah Tajwid'],
            ['tipe' => 'dokumen', 'tipe_materi' => 'dokumen', 'prefix' => 'Panduan Mutaba\'ah & Lembar Talaqqi (PDF)'],
            ['tipe' => 'link', 'tipe_materi' => 'link', 'prefix' => 'Audio Murottal Rujukan (EQuran / Murottal Online)'],
        ];

        $createdMateriCount = 0;
        foreach ($createdModuls as $modulData) {
            $m = $modulData['modul'];
            $pekan = $modulData['pekan'];
            $surah = $modulData['surah'];

            foreach ($materiTypes as $tIdx => $mType) {
                $judulMateri = sprintf('%s: %s (Pekan %d)', $mType['prefix'], $surah, $pekan);

                $konten = match ($mType['tipe']) {
                    'teks' => sprintf(
                        "<h3>Kaidah Tilawah dan Mutaba'ah Pekan ke-%d</h3><p><strong>Materi Pokok:</strong> %s</p><p><strong>Panduan Talaqqi:</strong></p><ul><li>Perhatikan makharijul huruf pada setiap ayat.</li><li>Jaga konsistensi panjang harakat mad thabi'i (2 harakat) dan ghunnah musyaddadah (2 harakat berdesir).</li><li>Ulangi ayat minimal 10 kali (metode tikrar) sebelum menyetor kepada Ustadz Ahmad Farhan.</li></ul><p><em>Barangsiapa membaca satu huruf dari Kitab Allah, maka baginya satu kebaikan dan satu kebaikan dilipatgandakan menjadi sepuluh kebaikan (HR. Tirmidzi).</em></p>",
                        $pekan,
                        $surah
                    ),
                    'dokumen' => sprintf(
                        "Lembar kerja dan buku kendali mutaba'ah hafalan pekan ke-%d untuk %s. Berisi checklist setoran harian, kolom paraf orang tua, dan catatan perbaikan tajwid dari guru.",
                        $pekan,
                        $surah
                    ),
                    'link' => sprintf('https://equran.id/surah/%d', max(78, 114 - ($pekan % 36))),
                };

                LmsMateri::firstOrCreate(
                    [
                        'modul_ajar_id' => $m->id,
                        'urutan' => $tIdx + 1,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'mata_pelajaran_id' => $subject->id,
                        'subject_id' => $subject->id,
                        'guru_id' => $employee->id,
                        'teacher_id' => $employee->id,
                        'judul' => $judulMateri,
                        'konten' => $konten,
                        'isi' => $konten,
                        'ringkasan' => sprintf('Materi penunjang pembelajaran Tahfizh pekan ke-%d materi %s.', $pekan, $surah),
                        'tipe_materi' => $mType['tipe_materi'],
                        'tipe' => $mType['tipe'],
                        'link' => $mType['tipe'] === 'link' ? $konten : null,
                        'is_published' => true,
                        'status' => 'aktif',
                        'tanggal_publish' => Carbon::now()->subMonths(3)->addWeeks($pekan),
                        'created_by' => $teacherUser->id,
                    ]
                );
                $createdMateriCount++;
            }
        }

        // 5. Buat Forum Diskusi Interaktif (lms_diskusi) dan Komentar Diskusi
        $sditStudents = Student::where('unit_id', $unit->id)->with('user')->take(10)->get();

        $diskusiTopics = [
            [
                'modul_idx' => 0,
                'judul' => 'Klinik Makhraj: Tips Membedakan Huruf Halqiyah (Hamzah vs \'Ain dan Ha vs Kha)',
                'deskripsi' => 'Assalamu\'alaikum ananda sekalian. Banyak santri yang sering tertukar antara huruf Ha (ح) halus dan Kha (خ) tebal, serta Hamzah (ء) dan \'Ain (ع). Silakan tuliskan kesulitan yang ananda rasakan saat melafalkannya di rumah agar ustadz beri bimbingan.',
                'kategori' => 'tanya_jawab',
                'q_siswa' => 'Ustadz, ketika membaca Surah Al-Falaq ayat ke-2 (مِن شَرِّ مَا خَلَقَ), huruf Kha di kata khalaqa terasa sulit keluar dari tenggorokan atas. Bagaimana posisi lidah yang benar ustadz?',
                'a_guru' => 'Wa\'alaikumussalam warahmatullah ananda. Huruf Kha (خ) keluar dari tenggorokan bagian atas (adnal halq). Posisi lidah bagian belakang sedikit terangkat (isti\'la) sehingga suaranya tebal dan ada desis angin mengalir (hams). Latih dengan mengucapkannya bersuara sukun: akh... ikh... ukh... tanpa menekan terlalu keras.',
            ],
            [
                'modul_idx' => 1,
                'judul' => 'Metode Tikrar Efektif: Berapa Kali Pengulangan Agar Hafalan Tidak Mudah Lupa?',
                'deskripsi' => 'Mari berbagi tips dan kendala mutaba\'ah hafalan mandiri. Berapa kali ananda mengulang 1 ayat sebelum pindah ke ayat berikutnya saat belajar di rumah bersama abi dan ummi?',
                'q_siswa' => 'Ustadz, saya biasanya mengulang 5 kali saja sudah hafal, tapi besok paginya sering lupa lagi awal ayatnya. Apakah pengulangannya masih kurang ustadz?',
                'a_guru' => 'Masya Allah pertanyaan bagus. Menghafal cepat itu nikmat, tapi mengikat hafalan butuh tikrar (pengulangan) minimal 20 sampai 30 kali per ayat. Setelah hafal satu ayat, gabungkan dengan ayat sebelumnya (metode rabth). Jangan buru-buru pindah halaman sebelum ayat tersebut lancar seperti membaca Surah Al-Fatihah.',
            ],
            [
                'modul_idx' => 6,
                'judul' => 'Hukum Qalqalah: Kapan Qalqalah Dibaca Kubra dan Kapan Dibaca Sughra?',
                'deskripsi' => 'Diskusi seputar penerapan huruf qalqalah (Qaf, Tha, Ba, Jim, Dal) dalam Surah Al-Fil dan Quraisy. Silakan diskusikan contoh ayat yang ananda temukan.',
                'q_siswa' => 'Ustadz, di Surah Al-Fil ayat 2 (فِي تَضْلِيلٍ) huruf Dhad apakah termasuk qalqalah juga ustadz? Kenapa tidak boleh dipantulkan?',
                'a_guru' => 'Alhamdulillah pertanyaannya kritis. Huruf Dhad (ض) BUKAN huruf qalqalah. Huruf qalqalah hanya 5 yang terkumpul dalam singkatan BA-JU-DI-THO-QO (ب ج د ط ق). Huruf Dhad memiliki sifat Istitholah (memanjang makhrajnya), jadi tidak boleh dipantulkan ya ananda.',
            ],
            [
                'modul_idx' => 12,
                'judul' => 'Persiapan Tasmi\' Juz 30: Menghadapi Ayat-Ayat Mutasyabihat yang Serupa',
                'deskripsi' => 'Ruang konsultasi setoran tasmi\' Juz 30. Tuliskan surah mana yang menurut ananda sering tertukar susunan ayatnya agar kita buatkan jembatan pengingat.',
                'q_siswa' => 'Ustadz, saya sering tertukar antara ujung ayat di Surah Al-Infithar dan Al-Muthaffifin saat tasmi\'. Ada tips agar ingat urutannya ustadz?',
                'a_guru' => 'Tipsnya adalah membuat catatan khusus pada buku mutaba\'ah ananda untuk ayat-ayat mutasyabihat. Tandai kata kuncinya dengan pensil warna, dan baca artinya agar ananda memahami alur cerita kisahnya. Tetap istiqomah muraja\'ah!',
            ],
        ];

        foreach ($diskusiTopics as $dItem) {
            $modulTarget = $createdModuls[$dItem['modul_idx']]['modul'] ?? $createdModuls[0]['modul'];

            $diskusi = LmsDiskusi::firstOrCreate(
                [
                    'modul_ajar_id' => $modulTarget->id,
                    'judul' => $dItem['judul'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'deskripsi' => $dItem['deskripsi'],
                    'kategori' => $dItem['kategori'] ?? 'tanya_jawab',
                    'tanggal_mulai' => Carbon::now()->subMonths(1),
                    'tanggal_tutup' => Carbon::now()->addMonths(5),
                    'is_pinned' => true,
                    'is_closed' => false,
                    'status' => 'aktif',
                    'created_by' => $teacherUser->id,
                ]
            );

            // Tambahkan komentar siswa
            $sampleStudent = $sditStudents->first();
            $studentUser = $sampleStudent?->user ?: $teacherUser;

            $komentarSiswa = LmsDiskusiKomentar::firstOrCreate(
                [
                    'diskusi_id' => $diskusi->id,
                    'konten' => $dItem['q_siswa'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $studentUser->id,
                    'peran_pengirim' => 'siswa',
                    'is_solution' => false,
                    'created_by' => $studentUser->id,
                ]
            );

            // Tambahkan balasan guru
            LmsDiskusiKomentar::firstOrCreate(
                [
                    'diskusi_id' => $diskusi->id,
                    'konten' => $dItem['a_guru'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'parent_id' => $komentarSiswa->id,
                    'user_id' => $teacherUser->id,
                    'peran_pengirim' => 'guru',
                    'is_solution' => true,
                    'created_by' => $teacherUser->id,
                ]
            );
        }

        $this->command->info(sprintf(
            'Berhasil men-generate data LMS untuk Ahmad Farhan: %d Modul Ajar, %d Materi Belajar, dan %d Forum Diskusi.',
            count($createdModuls),
            $createdMateriCount,
            count($diskusiTopics)
        ));
    }
}
