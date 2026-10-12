<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\MasterKurikulum;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder Khusus:
 * Mengisi Kisi-kisi (lms_kisi_kisi) dan Bank Soal (lms_bank_soal)
 * untuk Unit Boarding (PONPES-PA) dan Unit Fullday (SDIT-01).
 */
class SampleKisiKisiBankSoalSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tahun Ajaran & Semester Aktif
        $academicYear = AcademicYear::where('is_active', true)->first()
            ?? AcademicYear::first();
        $semester = Semester::where('academic_year_id', $academicYear?->id)->where('is_active', true)->first()
            ?? Semester::first();

        // Kurikulum
        $kurikulumSmp = MasterKurikulum::where('jenjang', 'SMP')->first()
            ?? MasterKurikulum::first();
        $kurikulumSd = MasterKurikulum::where('jenjang', 'SD')->first()
            ?? MasterKurikulum::first();

        // =========================================================================
        // 1. UNIT BOARDING: PONPES Putra Dar el-Iman (PONPES-PA)
        // =========================================================================
        $boardingUnit = EducationUnit::where('code', 'PONPES-PA')->first();
        $kelasBoarding = Kelas::where('kode_kelas', 'PONPES-7A')->first();

        $mapelTahfizhPonpes = Subject::where('kode_mapel', 'MAPEL-THF-PONPES-7')->first();
        $mapelBarabPonpes = Subject::where('kode_mapel', 'MAPEL-BAR-PONPES-7')->first();
        $mapelFiqihPonpes = Subject::where('kode_mapel', 'MAPEL-FQH-PONPES-7')->first();

        $guruTahfizhPonpes = Employee::where('email', 'guru.tahfizh.ponpes@dareliman.sch.id')->first();
        $guruBarabPonpes = Employee::where('email', 'guru.barab.ponpes@dareliman.sch.id')->first();
        $guruWaliPonpes = Employee::where('email', 'wali.ponpes7a@dareliman.sch.id')->first();

        $kisiKisiBoarding = [
            // Paket 1: Tahfizh & Tajwid
            [
                'subject' => $mapelTahfizhPonpes,
                'guru' => $guruTahfizhPonpes,
                'judul' => 'Kisi-Kisi Penilaian Tengah Semester (PTS) Tahfizh & Tajwid VII-A',
                'jenis_ujian' => 'PTS',
                'alokasi' => 90,
                'kd' => 'Memahami hukum nun sukun, tanwin, mim sukun serta makharijul huruf Juz 30',
                'level' => 'L2 (Aplikasi)',
                'distribusi' => ['mudah' => 30, 'sedang' => 50, 'sukar' => 20],
                'soal' => [
                    [
                        'kode' => 'SOAL-THF-7A-01',
                        'pertanyaan' => 'Apabila nun sukun (نْ) bertemu dengan huruf ba (ب), maka hukum tajwid yang berlaku adalah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Idzhar Halqi',
                        'opsi_b' => 'Iqlab',
                        'opsi_c' => 'Idgham Bighunnah',
                        'opsi_d' => 'Ikhfa Haqiqi',
                        'opsi_e' => 'Idgham Bilaghunnah',
                        'kunci' => 'B',
                        'pembahasan' => 'Iqlab terjadi apabila nun sukun atau tanwin bertemu dengan huruf Ba (ب), bunyinya ditukar menjadi Mim disertai dengung.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Peserta didik dapat mengidentifikasi hukum bacaan Iqlab dengan benar.',
                    ],
                    [
                        'kode' => 'SOAL-THF-7A-02',
                        'pertanyaan' => 'Huruf-huruf Idzhar Halqi berjumlah 6, yaitu...',
                        'tipe' => 'pg',
                        'opsi_a' => 'ء, هـ, ع, ح, غ, خ',
                        'opsi_b' => 'ي, ن, م, و, ر, ل',
                        'opsi_c' => 'ت, ث, د, ذ, ز, س',
                        'opsi_d' => 'ق, ط, ب, ج, د, ك',
                        'opsi_e' => 'ف, ق, ك, ل, م, ن',
                        'kunci' => 'A',
                        'pembahasan' => 'Huruf Idzhar Halqi keluar dari tenggorokan (Halq) yaitu Hamzah, Ha, Ain, Ha, Ghain, dan Kha.',
                        'poin' => 10.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Peserta didik dapat menyebutkan enam huruf Idzhar Halqi.',
                    ],
                    [
                        'kode' => 'SOAL-THF-7A-03',
                        'pertanyaan' => 'Lanjutan ayat dari QS. An-Naba ayat 1: "عَمَّ يَتَسَآءَلُونَ" adalah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'الَّذِى هُمْ فِيهِ مُخْتَلِفُونَ',
                        'opsi_b' => 'عَنِ ٱلنَّبَإِ ٱلْعَظِيمِ',
                        'opsi_c' => 'كَلَّا سَيَعْلَمُونَ',
                        'opsi_d' => 'ثُمَّ كَلَّا سَيَعْلَمُونَ',
                        'opsi_e' => 'أَلَمْ نَجْعَلِ ٱلْأَرْضَ مِهَٰدًا',
                        'kunci' => 'B',
                        'pembahasan' => 'Ayat ke-2 Surah An-Naba berbunyi: عَنِ ٱلنَّبَإِ ٱلْعَظِيمِ.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Santri dapat melanjutkan potongan ayat awal Surah An-Naba.',
                    ],
                    [
                        'kode' => 'SOAL-THF-7A-04',
                        'pertanyaan' => 'Sebutkan tingkatan ghunnah yang paling sempurna (Akmal ma takun) beserta contohnya!',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'Nun dan Mim bertasydid (Musyaddadatain), contoh: إِنَّ dan ثُمَّ.',
                        'pembahasan' => 'Tingkatan ghunnah paling tinggi terjadi saat huruf nun atau mim bertasydid sempurna.',
                        'poin' => 20.0,
                        'kesulitan' => 'sukar',
                        'indikator' => 'Santri mampu menjelaskan tingkatan maratibul ghunnah.',
                    ],
                ],
            ],

            // Paket 2: Bahasa Arab & Nahwu Sharaf
            [
                'subject' => $mapelBarabPonpes,
                'guru' => $guruBarabPonpes,
                'judul' => 'Kisi-Kisi Penilaian Harian (UH) Bahasa Arab & Nahwu Sharaf Kelas VII',
                'jenis_ujian' => 'UH',
                'alokasi' => 60,
                'kd' => 'Membedakan pembagian kalam: Isim, Fi\'il, dan Huruf dalam teks bahasa Arab',
                'level' => 'L1 (Mengingat)',
                'distribusi' => ['mudah' => 40, 'sedang' => 40, 'sukar' => 20],
                'soal' => [
                    [
                        'kode' => 'SOAL-BAR-7A-01',
                        'pertanyaan' => 'Kata yang menunjukkan arti benda atau manusia dan tidak terikat oleh waktu disebut...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Fi\'il (فِعْلٌ)',
                        'opsi_b' => 'Isim (اِسْمٌ)',
                        'opsi_c' => 'Huruf (حَرْفٌ)',
                        'opsi_d' => 'Dhamir (ضَمِيرٌ)',
                        'opsi_e' => 'Zharaf (ظَرْفٌ)',
                        'kunci' => 'B',
                        'pembahasan' => 'Isim adalah kata yang memiliki makna mandiri tanpa terikat oleh dimensi waktu.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Mampu membedakan hakikat isim dan fi\'il.',
                    ],
                    [
                        'kode' => 'SOAL-BAR-7A-02',
                        'pertanyaan' => 'Di antara tanda-tanda Isim berikut ini yang paling tepat adalah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Kemarukan huruf Qad (قَدْ)',
                        'opsi_b' => 'Kemarukan huruf Sin (سَـ)',
                        'opsi_c' => 'Bisa menerima Tanwin dan Alif Lam (ال)',
                        'opsi_d' => 'Kemarukan huruf Saufa (سَوْفَ)',
                        'opsi_e' => 'Kemarukan Ta Ta\'nits Sakinah',
                        'kunci' => 'C',
                        'pembahasan' => 'Tanda-tanda isim: Al-Khafdh (kasrah), Tanwin, kemasukan Al (ال), dan huruf khafdh.',
                        'poin' => 10.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Mampu mengidentifikasi ciri-ciri tanda lafal isim.',
                    ],
                    [
                        'kode' => 'SOAL-BAR-7A-03',
                        'pertanyaan' => 'Terjemahkan kalimat berikut ke dalam Bahasa Arab yang fasih: "Santri itu sedang membaca Al-Qur\'an di masjid."',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'يَقْرَأُ الطَّالِبُ القُرْآنَ فِي المَسْجِدِ',
                        'pembahasan' => 'Menggunakan susunan jumlah fi\'liyyah yang tepat dan harakat akhir mu\'rab.',
                        'poin' => 20.0,
                        'kesulitan' => 'sukar',
                        'indikator' => 'Mampu menyusun kalimat berbahasa Arab berkaidah nahwu sharaf.',
                    ],
                ],
            ],

            // Paket 3: Fiqih Ibadah & Muamalah
            [
                'subject' => $mapelFiqihPonpes,
                'guru' => $guruWaliPonpes,
                'judul' => 'Kisi-Kisi Penilaian Akhir Semester (PAS) Fiqih Thaharah & Shalat Kelas VII',
                'jenis_ujian' => 'PAS',
                'alokasi' => 90,
                'kd' => 'Menganalisis tata cara bersuci (Thaharah) dan rukun shalat sesuai Sunnah Nabi SAW',
                'level' => 'L3 (Penalaran)',
                'distribusi' => ['mudah' => 20, 'sedang' => 50, 'sukar' => 30],
                'soal' => [
                    [
                        'kode' => 'SOAL-FQH-7A-01',
                        'pertanyaan' => 'Air yang suci zatnya dan dapat menyucikan benda lain disebut air...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Thahir Ghairu Muthahhir',
                        'opsi_b' => 'Thahur / Muthahhir',
                        'opsi_c' => 'Makruh',
                        'opsi_d' => 'Mutanajjis',
                        'opsi_e' => 'Musta\'mal',
                        'kunci' => 'B',
                        'pembahasan' => 'Air Mutlaq atau Thahur adalah air yang suci lagi menyucikan (Muthahhir).',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Mengetahui pembagian jenis air dalam thaharah.',
                    ],
                    [
                        'kode' => 'SOAL-FQH-7A-02',
                        'pertanyaan' => 'Jelaskan perbedaan mendasar antara Rukun Shalat dengan Syarat Sah Shalat!',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'Syarat sah shalat harus terpenuhi sebelum memulai shalat dan bertahan sampai selesai (misal: suci dari hadats, menghadap kiblat). Sedangkan rukun shalat adalah bagian dari gerakan/bacaan internal di dalam shalat (misal: niat, takbiratul ihram, ruku\', sujud).',
                        'pembahasan' => 'Syarat berada di luar amalan, rukun berada di dalam struktur amalan shalat.',
                        'poin' => 20.0,
                        'kesulitan' => 'sukar',
                        'indikator' => 'Mampu membedakan syarat sah dan rukun shalat.',
                    ],
                ],
            ],
        ];

        foreach ($kisiKisiBoarding as $item) {
            if (! $item['subject'] || ! $item['guru']) continue;

            $kisi = LmsKisiKisi::updateOrCreate(
                [
                    'judul_kisi' => $item['judul'],
                    'mata_pelajaran_id' => $item['subject']->id,
                ],
                [
                    'kurikulum_id' => $kurikulumSmp?->id,
                    'kelas_id' => $kelasBoarding?->id,
                    'semester_id' => $semester?->id,
                    'tahun_ajaran_id' => $academicYear?->id,
                    'guru_id' => $item['guru']->id,
                    'jenis_ujian' => $item['jenis_ujian'],
                    'jumlah_soal' => count($item['soal']),
                    'alokasi_waktu_menit' => $item['alokasi'],
                    'kompetensi_dasar' => $item['kd'],
                    'level_kognitif' => $item['level'],
                    'distribusi_bobot' => $item['distribusi'],
                    'status' => true,
                ]
            );

            // Bank Soal
            foreach ($item['soal'] as $s) {
                LmsBankSoal::updateOrCreate(
                    [
                        'kisi_kisi_id' => $kisi->id,
                        'kode_soal' => $s['kode'],
                    ],
                    [
                        'mata_pelajaran_id' => $item['subject']->id,
                        'pertanyaan' => $s['pertanyaan'],
                        'tipe_soal' => $s['tipe'],
                        'opsi_a' => $s['opsi_a'],
                        'opsi_b' => $s['opsi_b'],
                        'opsi_c' => $s['opsi_c'],
                        'opsi_d' => $s['opsi_d'],
                        'opsi_e' => $s['opsi_e'],
                        'kunci_jawaban' => $s['kunci'],
                        'pembahasan' => $s['pembahasan'],
                        'poin' => $s['poin'],
                        'tingkat_kesulitan' => $s['kesulitan'],
                        'indikator' => $s['indikator'],
                        'status' => true,
                    ]
                );
            }

            // Sync ke question_banks legacy
            $teacherId = \App\Models\Teacher::where('employee_id', $item['guru']->id)->value('id') ?? $item['guru']->id;
            $qbId = (string) Str::uuid();
            DB::table('question_banks')->updateOrInsert(
                ['title' => $item['judul']],
                [
                    'id' => $qbId,
                    'teacher_id' => $teacherId,
                    'subject_id' => $item['subject']->id,
                    'title' => $item['judul'],
                    'questions' => json_encode($item['soal']),
                    'updated_at' => now(),
                ]
            );
        }

        // =========================================================================
        // 2. UNIT FULLDAY: SDIT 1 Dar el-Iman - 50 Kota (SDIT-01)
        // =========================================================================
        $fulldayUnit = EducationUnit::where('code', 'SDIT-01')->first();
        $kelasFullday = Kelas::where('kode_kelas', 'SDIT1-1A')->first();

        $mapelPaiSd = Subject::where('kode_mapel', 'MAPEL-PAI-SD-1')->first();
        $mapelTahfizhSd = Subject::where('kode_mapel', 'MAPEL-THF-SD-1')->first();
        $mapelTematikSd = Subject::where('kode_mapel', 'MAPEL-TMT-SD-1')->first();

        $guruPaiSd = Employee::where('email', 'guru.pai.sdit1@dareliman.sch.id')->first();
        $guruTahfizhSd = Employee::where('email', 'guru.tahfizh.sdit1@dareliman.sch.id')->first();
        $guruWaliSd = Employee::where('email', 'wali.sdit1a@dareliman.sch.id')->first();

        $kisiKisiFullday = [
            // Paket 1: PAI SD
            [
                'subject' => $mapelPaiSd,
                'guru' => $guruPaiSd,
                'judul' => 'Kisi-Kisi Penilaian Harian (UH) PAI Kelas 1 - Mengenal Rukun Iman & Huruf Hijaiyah',
                'jenis_ujian' => 'UH',
                'alokasi' => 60,
                'kd' => 'Menyebutkan rukun iman dan melafalkan huruf-huruf hijaiyah berharakat',
                'level' => 'L1 (Mengingat)',
                'distribusi' => ['mudah' => 50, 'sedang' => 40, 'sukar' => 10],
                'soal' => [
                    [
                        'kode' => 'SOAL-PAI-1A-01',
                        'pertanyaan' => 'Rukun Iman yang pertama adalah beriman kepada...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Malaikat',
                        'opsi_b' => 'Allah SWT',
                        'opsi_c' => 'Kitab-kitab Allah',
                        'opsi_d' => 'Hari Kiamat',
                        'opsi_e' => 'Rasul-rasul Allah',
                        'kunci' => 'B',
                        'pembahasan' => 'Rukun Iman ada 6, yang pertama adalah Iman kepada Allah SWT.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Siswa dapat menyebutkan Rukun Iman yang pertama.',
                    ],
                    [
                        'kode' => 'SOAL-PAI-1A-02',
                        'pertanyaan' => 'Huruf hijaiyah "ب" jika diberi harakat fathah (ـَ) dibaca...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Bi',
                        'opsi_b' => 'Bu',
                        'opsi_c' => 'Ba',
                        'opsi_d' => 'Ban',
                        'opsi_e' => 'Ab',
                        'kunci' => 'C',
                        'pembahasan' => 'Harakat fathah berbunyi "A", sehingga huruf Ba berharakat fathah dibaca "Ba".',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Siswa dapat membaca huruf hijaiyah berharakat fathah.',
                    ],
                    [
                        'kode' => 'SOAL-PAI-1A-03',
                        'pertanyaan' => 'Sebutkan 3 contoh ciptaan Allah SWT yang ada di langit dan bumi!',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'Matahari, bulan, bintang, gunung, pohon, manusia, dan hewan.',
                        'pembahasan' => 'Siswa menyebutkan makhluk ciptaan Allah.',
                        'poin' => 20.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Siswa dapat memberikan contoh ciptaan Allah SWT.',
                    ],
                ],
            ],

            // Paket 2: Tahfizh SDIT
            [
                'subject' => $mapelTahfizhSd,
                'guru' => $guruTahfizhSd,
                'judul' => 'Kisi-Kisi Penilaian Tengah Semester (PTS) Tahfizh Surah Pendek Kelas 1-A',
                'jenis_ujian' => 'PTS',
                'alokasi' => 60,
                'kd' => 'Hafalan dan tartil Surah Al-Fatihah, An-Naas, Al-Falaq, dan Al-Ikhlas',
                'level' => 'L2 (Aplikasi)',
                'distribusi' => ['mudah' => 40, 'sedang' => 40, 'sukar' => 20],
                'soal' => [
                    [
                        'kode' => 'SOAL-THF-1A-01',
                        'pertanyaan' => 'Surah Al-Ikhlas menceritakan tentang keesaan Allah SWT. Ayat pertama berbunyi...',
                        'tipe' => 'pg',
                        'opsi_a' => 'ٱللَّهُ ٱلصَّمَدُ',
                        'opsi_b' => 'قُلْ هُوَ ٱللَّهُ أَحَدٌ',
                        'opsi_c' => 'لَمْ يَلِدْ وَلَمْ يُولَدْ',
                        'opsi_d' => 'وَلَمْ يَكُن لَّهُۥ كُفُوًا أَحَدٌۢ',
                        'opsi_e' => 'قُلْ أَعُوذُ بِرَبِّ ٱلنَّاسِ',
                        'kunci' => 'B',
                        'pembahasan' => 'Ayat pertama QS. Al-Ikhlas adalah: قُلْ هُوَ ٱللَّهُ أَحَدٌ.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Siswa dapat melafalkan ayat pertama Surah Al-Ikhlas.',
                    ],
                    [
                        'kode' => 'SOAL-THF-1A-02',
                        'pertanyaan' => 'Lanjutan ayat: "مِن شَرِّ مَا خَلَقَ" terdapat dalam surah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'An-Naas',
                        'opsi_b' => 'Al-Falaq',
                        'opsi_c' => 'Al-Kautsar',
                        'opsi_d' => 'Al-Lahab',
                        'opsi_e' => 'Al-Kafirun',
                        'kunci' => 'B',
                        'pembahasan' => 'Ayat kedua dari Surah Al-Falaq berbunyi: مِن شَرِّ مَا خَلَقَ.',
                        'poin' => 10.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Siswa dapat mengenali ayat dari Surah Al-Falaq.',
                    ],
                    [
                        'kode' => 'SOAL-THF-1A-03',
                        'pertanyaan' => 'Tuliskan adab yang baik saat membaca dan menghafal Al-Qur\'an!',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'Berwudhu (suci dari hadats), duduk rapi menghadap kiblat, memulai dengan ta\'awwudz dan basmalah, serta tidak bercanda.',
                        'pembahasan' => 'Menghormati kesucian kalamullah Al-Qur\'an.',
                        'poin' => 20.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Siswa memahami adab tilawah dan tahfizh Al-Qur\'an.',
                    ],
                ],
            ],

            // Paket 3: Tematik SDIT
            [
                'subject' => $mapelTematikSd,
                'guru' => $guruWaliSd,
                'judul' => 'Kisi-Kisi Penilaian Akhir Semester (PAS) Tematik & Pendidikan Pancasila Kelas 1',
                'jenis_ujian' => 'PAS',
                'alokasi' => 90,
                'kd' => 'Menerapkan aturan kehidupan sehari-hari di rumah dan sekolah, serta sikap saling menghargai',
                'level' => 'L2 (Aplikasi)',
                'distribusi' => ['mudah' => 30, 'sedang' => 50, 'sukar' => 20],
                'soal' => [
                    [
                        'kode' => 'SOAL-TMT-1A-01',
                        'pertanyaan' => 'Sebelum makan bersama keluarga di rumah, sikap baik yang harus kita lakukan adalah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Langsung mengambil makanan yang paling besar',
                        'opsi_b' => 'Mencuci tangan dan berdoa bersama',
                        'opsi_c' => 'Bermain telepon genggam sambil makan',
                        'opsi_d' => 'Meninggalkan meja makan',
                        'opsi_e' => 'Berbicara keras-keras',
                        'kunci' => 'B',
                        'pembahasan' => 'Mencuci tangan dan membaca doa adalah adab makan yang baik dan sehat.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Siswa memahami adab dan tata tertib di rumah.',
                    ],
                    [
                        'kode' => 'SOAL-TMT-1A-02',
                        'pertanyaan' => 'Lambang negara Indonesia adalah...',
                        'tipe' => 'pg',
                        'opsi_a' => 'Burung Garuda',
                        'opsi_b' => 'Harimau Sumatera',
                        'opsi_c' => 'Bunga Melati',
                        'opsi_d' => 'Pohon Beringin',
                        'opsi_e' => 'Bendera Merah Putih',
                        'kunci' => 'A',
                        'pembahasan' => 'Lambang negara kesatuan Republik Indonesia adalah Garuda Pancasila.',
                        'poin' => 10.0,
                        'kesulitan' => 'mudah',
                        'indikator' => 'Siswa mengenal lambang negara Garuda Pancasila.',
                    ],
                    [
                        'kode' => 'SOAL-TMT-1A-03',
                        'pertanyaan' => 'Ceritakan bagaimana cara kamu membantu teman kelas yang pensilnya tertinggal di rumah!',
                        'tipe' => 'esai',
                        'opsi_a' => null, 'opsi_b' => null, 'opsi_c' => null, 'opsi_d' => null, 'opsi_e' => null,
                        'kunci' => 'Meminjamkan pensil cadangan dengan ikhlas dan tersenyum agar teman bisa belajar bersama.',
                        'pembahasan' => 'Cerminan sikap tolong-menolong dan persaudaraan di lingkungan sekolah SIT.',
                        'poin' => 20.0,
                        'kesulitan' => 'sedang',
                        'indikator' => 'Siswa menunjukkan sikap tolong-menolong antar sesama teman.',
                    ],
                ],
            ],
        ];

        foreach ($kisiKisiFullday as $item) {
            if (! $item['subject'] || ! $item['guru']) continue;

            $kisi = LmsKisiKisi::updateOrCreate(
                [
                    'judul_kisi' => $item['judul'],
                    'mata_pelajaran_id' => $item['subject']->id,
                ],
                [
                    'kurikulum_id' => $kurikulumSd?->id,
                    'kelas_id' => $kelasFullday?->id,
                    'semester_id' => $semester?->id,
                    'tahun_ajaran_id' => $academicYear?->id,
                    'guru_id' => $item['guru']->id,
                    'jenis_ujian' => $item['jenis_ujian'],
                    'jumlah_soal' => count($item['soal']),
                    'alokasi_waktu_menit' => $item['alokasi'],
                    'kompetensi_dasar' => $item['kd'],
                    'level_kognitif' => $item['level'],
                    'distribusi_bobot' => $item['distribusi'],
                    'status' => true,
                ]
            );

            // Bank Soal
            foreach ($item['soal'] as $s) {
                LmsBankSoal::updateOrCreate(
                    [
                        'kisi_kisi_id' => $kisi->id,
                        'kode_soal' => $s['kode'],
                    ],
                    [
                        'mata_pelajaran_id' => $item['subject']->id,
                        'pertanyaan' => $s['pertanyaan'],
                        'tipe_soal' => $s['tipe'],
                        'opsi_a' => $s['opsi_a'],
                        'opsi_b' => $s['opsi_b'],
                        'opsi_c' => $s['opsi_c'],
                        'opsi_d' => $s['opsi_d'],
                        'opsi_e' => $s['opsi_e'],
                        'kunci_jawaban' => $s['kunci'],
                        'pembahasan' => $s['pembahasan'],
                        'poin' => $s['poin'],
                        'tingkat_kesulitan' => $s['kesulitan'],
                        'indikator' => $s['indikator'],
                        'status' => true,
                    ]
                );
            }

            // Sync ke question_banks legacy
            $teacherId = \App\Models\Teacher::where('employee_id', $item['guru']->id)->value('id') ?? $item['guru']->id;
            $qbId = (string) Str::uuid();
            DB::table('question_banks')->updateOrInsert(
                ['title' => $item['judul']],
                [
                    'id' => $qbId,
                    'teacher_id' => $teacherId,
                    'subject_id' => $item['subject']->id,
                    'title' => $item['judul'],
                    'questions' => json_encode($item['soal']),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info("Berhasil mengisi Kisi-kisi dan Bank Soal untuk PONPES-PA (Boarding) dan SDIT-01 (Fullday)!");
    }
}
