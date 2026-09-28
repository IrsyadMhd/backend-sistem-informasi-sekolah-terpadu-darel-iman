<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class StudentNoteDemoSeeder extends Seeder
{
    /**
     * Seed catatan buku penghubung untuk anak-anak dari orangtua@dareliman.sch.id.
     * Menggunakan data database riil dan relasi terverifikasi, bukan hardcode di UI.
     */
    public function run(): void
    {
        $parentUser = User::where('email', 'orangtua@dareliman.sch.id')->first();
        if (! $parentUser) {
            $this->command?->warn('User orangtua@dareliman.sch.id tidak ditemukan.');
            return;
        }

        $parent = ParentModel::where('user_id', $parentUser->id)->first();
        if (! $parent) {
            $this->command?->warn('Parent record untuk orangtua@dareliman.sch.id tidak ditemukan.');
            return;
        }

        $students = Student::where('parent_id', $parent->id)->get();
        if ($students->isEmpty()) {
            $this->command?->warn('Siswa untuk orangtua@dareliman.sch.id tidak ditemukan.');
            return;
        }

        $academicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::where('is_active', true)->first() ?? Semester::first();
        $teachers = Teacher::all();

        if ($teachers->isEmpty()) {
            $this->command?->warn('Data guru belum tersedia.');
            return;
        }

        $guruTahfizh = $teachers->firstWhere('full_name', 'Guru Tahfizh Test') 
            ?? $teachers->firstWhere('full_name', 'Ust. Rahmat Hidayat, Lc.') 
            ?? $teachers->first();

        $guruBK = $teachers->firstWhere('full_name', 'Guru BK Test') 
            ?? $teachers->firstWhere('full_name', 'Ustadzah Fatimah Azzahra, S.Ag.') 
            ?? $teachers->skip(1)->first() 
            ?? $teachers->first();

        $waliKelas = $teachers->firstWhere('full_name', 'Wali Kelas Test') 
            ?? $teachers->firstWhere('full_name', 'Ust. Ahmad Dahlan, S.Pd.') 
            ?? $teachers->skip(2)->first() 
            ?? $teachers->first();

        $guruUmum = $teachers->firstWhere('full_name', 'Guru Test') 
            ?? $teachers->firstWhere('full_name', 'Ust. Hidayatullah, M.Pd.') 
            ?? $teachers->skip(3)->first() 
            ?? $teachers->first();

        // Bersihkan catatan lama siswa-siswa ini agar seeder idempotent
        StudentNote::whereIn('student_id', $students->pluck('id'))->forceDelete();

        $now = Carbon::now();

        foreach ($students as $student) {
            $isPondok = str_contains(strtolower($student->name), 'pondok') || str_contains(strtolower($student->educationUnit?->name ?? ''), 'abu ja\'far');
            $isSMP = str_contains(strtolower($student->name), 'smp') || str_contains(strtolower($student->educationUnit?->name ?? ''), 'smp');
            $isSMA = str_contains(strtolower($student->name), 'sma') || str_contains(strtolower($student->educationUnit?->name ?? ''), 'sma');

            if ($isPondok) {
                // ── DATA BUKU PENGHUBUNG: SISWA PONDOK PESANTREN ──
                $notesData = [
                    [
                        'category' => 'Tahfizh',
                        'title' => 'Peningkatan Kelancaran Mutaba\'ah Ziyadah Juz 29',
                        'content' => 'Alhamdulillah ananda ' . $student->name . ' telah menyelesaikan setoran ziyadah Surah Al-Mulk hingga Al-Qalam dengan makharijul huruf dan tajwid yang sangat baik. Mohon didoakan agar ananda istiqomah menjaga hafalan saat libur kepulangan.',
                        'date' => $now->copy()->subDays(1)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $guruTahfizh->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Ibadah',
                        'title' => 'Istiqomah Sholat Tahajjud & Puasa Sunnah Senin-Kamis',
                        'content' => 'Ananda sangat antusias dalam pembiasaan qiyamul lail berjamaah di masjid asrama dan konsisten menjalankan puasa sunnah. Sikap tawadhu dan keteladanan ananda patut diapresiasi.',
                        'date' => $now->copy()->subDays(3)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $waliKelas->id,
                        'signed' => true,
                        'follow_up' => 'Alhamdulillah, syukron jazakumullah khair ustadz atas bimbingan ruhaniyah di asrama. InsyaAllah kami dari keluarga akan selalu mendoakan dan memotivasi ananda.',
                    ],
                    [
                        'category' => 'Perilaku',
                        'title' => 'Kerapian Lemari & Kebersihan Kamar Asrama',
                        'content' => 'Saat inspeksi kebersihan kamar asrama pekan ini, ranjang dan loker pakaian ananda terlihat sangat rapi dan wangi. Sikap disiplin menjaga kebersihan lingkungan asrama ini sangat membanggakan.',
                        'date' => $now->copy()->subDays(5)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $guruUmum->id,
                        'signed' => true,
                        'follow_up' => 'Alhamdulillah, terima kasih banyak ustadz atas perhatian dan evaluasinya.',
                    ],
                    [
                        'category' => 'Kesehatan',
                        'title' => 'Pemeriksaan Kesehatan Berkala UKS Asrama',
                        'content' => 'Ananda sempat mengeluhkan flu ringan pada awal pekan dan telah diberikan vitamin serta waktu istirahat yang cukup di UKS Pondok. Kondisi saat ini sudah pulih 100% dan kembali aktif beraktivitas normal.',
                        'date' => $now->copy()->subDays(7)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $waliKelas->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Kedisiplinan',
                        'title' => 'Tepat Waktu Masuk Halaqah Al-Qur\'an Ba\'da Ashar',
                        'content' => 'Ananda selalu hadir 10 menit sebelum adzan Ashar berkumandang dan mempersiapkan mushaf Al-Qur\'an dengan tertib di halaqah.',
                        'date' => $now->copy()->subDays(10)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $guruTahfizh->id,
                        'signed' => true,
                        'follow_up' => 'Barakallahu fiik ustadz.',
                    ],
                ];
            } elseif ($isSMP) {
                // ── DATA BUKU PENGHUBUNG: SISWA SMPIT ──
                $notesData = [
                    [
                        'category' => 'Prestasi',
                        'title' => 'Apresiasi Juara 2 Olimpiade Sains Matematika Kota Padang',
                        'content' => 'Selamat kepada ananda ' . $student->name . ' atas prestasinya meraih Juara 2 dalam ajang Olimpiade Sains Matematika tingkat Kota. Kerja keras dan semangat belajar ananda membuahkan hasil yang membanggakan sekolah.',
                        'date' => $now->copy()->subDays(2)->toDateString(),
                        'priority' => 'high',
                        'teacher_id' => $guruUmum->id,
                        'signed' => true,
                        'follow_up' => 'Alhamdulillah bi ni\'matihi tatimmus shaalihaat. Terima kasih kepada seluruh ustadz/ustadzah atas bimbingan dan doa restunya.',
                    ],
                    [
                        'category' => 'Akademik',
                        'title' => 'Evaluasi Formatif Matematika & Proyek Sains',
                        'content' => 'Nilai asesmen formatif materi Aljabar ananda mencapai 95 (Sangat Baik). Ananda juga aktif membantu teman sekelompok dalam menyelesaikan tugas laboratorium IPA.',
                        'date' => $now->copy()->subDays(4)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $waliKelas->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Kedisiplinan',
                        'title' => 'Pengingat Membawa Perlengkapan Praktikum Robotika',
                        'content' => 'Mohon bantuan orang tua untuk mengingatkan ananda agar membawa modul praktikum robotika dan perlengkapan coding pada hari Kamis pekan depan.',
                        'date' => $now->copy()->subDays(6)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $guruUmum->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Konseling',
                        'title' => 'Sesi Konseling Minat Bakat & Pengembangan Diri',
                        'content' => 'Berdasarkan hasil asesmen bakat minat di ruang BK, ananda menunjukkan potensi yang sangat kuat di bidang logika analitis dan sains terapan. Disarankan untuk mengikuti klub olimpiade binaan sekolah.',
                        'date' => $now->copy()->subDays(9)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $guruBK->id,
                        'signed' => true,
                        'follow_up' => 'Baik ustadz/ustadzah BK, kami sangat mendukung ananda untuk bergabung di klub olimpiade sekolah.',
                    ],
                    [
                        'category' => 'Tahfizh',
                        'title' => 'Tasmi\' Hafalan Surah Al-Mursalat',
                        'content' => 'Ananda memperdengarkan tasmi\' Surah Al-Mursalat di hadapan musyrif dengan fashahah yang baik dan lancar tanpa kekeliruan.',
                        'date' => $now->copy()->subDays(12)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $guruTahfizh->id,
                        'signed' => true,
                        'follow_up' => 'Jazakallahu khair ustadz.',
                    ],
                ];
            } else {
                // ── DATA BUKU PENGHUBUNG: SISWA SMAIT ──
                $notesData = [
                    [
                        'category' => 'Akademik',
                        'title' => 'Hasil Try Out UTBK-SNBT Gelombang 1',
                        'content' => 'Ananda ' . $student->name . ' memperoleh skor try out skolastik 725, menempatkan ananda di peringkat 3 teratas jenjang SMAIT. Fokus berikutnya adalah penguatan Penalaran Matematika dan Literasi Bahasa Inggris.',
                        'date' => $now->copy()->subDays(1)->toDateString(),
                        'priority' => 'high',
                        'teacher_id' => $waliKelas->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Tahfizh',
                        'title' => 'Pencapaian Tasmi\' 5 Juz Sekali Duduk (Mumtaz)',
                        'content' => 'MasyaAllah Tabarakallah, ananda berhasil menuntaskan ujian Tasmi\' Al-Qur\'an 5 Juz bil ghaib sekali duduk dengan predikat Mumtaz. Semoga Al-Qur\'an menjadi syafa\'at bagi ananda dan keluarga tercinta di akhirat kelak.',
                        'date' => $now->copy()->subDays(3)->toDateString(),
                        'priority' => 'high',
                        'teacher_id' => $guruTahfizh->id,
                        'signed' => true,
                        'follow_up' => 'Alhamdulillah, terima kasih tak terhingga kepada ustadz pembina tahfizh atas bimbingan dan kesabarannya mendidik ananda.',
                    ],
                    [
                        'category' => 'Kedisiplinan',
                        'title' => 'Keaktifan dan Kepemimpinan di Organisasi Santri (OSIS)',
                        'content' => 'Ananda menunjukkan jiwa kepemimpinan yang amanah sebagai koordinator divisi ibadah pada kegiatan Dauroh Ilmiyah Santri.',
                        'date' => $now->copy()->subDays(5)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $waliKelas->id,
                        'signed' => true,
                        'follow_up' => 'Semoga menjadi sarana melatih kepemimpinan dan tanggung jawab ananda.',
                    ],
                    [
                        'category' => 'Konseling',
                        'title' => 'Konsultasi Perencanaan Studi Lanjut & Pilihan Jurusan PTN',
                        'content' => 'Ananda telah berkonsultasi mengenai peminatan program studi Kedokteran dan Teknik Informatika di PTN rujukan. Kami sarankan orang tua ikut mendiskusikan portofolio prestasi ananda di rumah.',
                        'date' => $now->copy()->subDays(8)->toDateString(),
                        'priority' => 'medium',
                        'teacher_id' => $guruBK->id,
                        'signed' => false,
                        'follow_up' => null,
                    ],
                    [
                        'category' => 'Ibadah',
                        'title' => 'Pelatihan Khutbah & Kultum Ba\'da Dzuhur',
                        'content' => 'Ananda tampil membawakan kultum bertema "Adab Menuntut Ilmu Menurut Salafush Shalih" dengan dalil shahih dan intonasi yang lugas di hadapan jamaah santri SMAIT.',
                        'date' => $now->copy()->subDays(11)->toDateString(),
                        'priority' => 'low',
                        'teacher_id' => $guruUmum->id,
                        'signed' => true,
                        'follow_up' => 'Alhamdulillah, terima kasih ustadz atas kesempatan yang diberikan kepada ananda.',
                    ],
                ];
            }

            // Simpan catatan ke database
            foreach ($notesData as $item) {
                $content = $item['content'];
                $hash = StudentNote::contentHash($content);

                $note = StudentNote::create([
                    'student_id' => $student->id,
                    'teacher_id' => $item['teacher_id'],
                    'education_unit_id' => $student->education_unit_id,
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semester->id,
                    'date' => $item['date'],
                    'category' => $item['category'],
                    'title' => $item['title'],
                    'content' => $content,
                    'priority' => $item['priority'],
                    'follow_up' => $item['follow_up'],
                    'visible_to_parent' => true,
                    'visible_to_student' => true,
                    'signed_by_user_id' => $item['signed'] ? $parentUser->id : null,
                    'signed_at' => $item['signed'] ? Carbon::parse($item['date'])->setTime(19, 30) : null,
                    'signature_content_hash' => $item['signed'] ? $hash : null,
                ]);
            }
        }

        // ── HUBUNGKAN WALI KELAS RESMI KE KELAS SANTRI ──
        \App\Models\Kelas::where('id', '01a0bcf7-cbe4-7088-b433-a1b2542c2e71')->update(['wali_kelas_id' => '01a0c38f-e4fe-7029-9231-f8fffd166561']);
        \App\Models\Kelas::where('id', '01a0bcfd-7fef-71bf-9556-32a51b1efce2')->update(['wali_kelas_id' => '2700beaf-8eff-484f-ba18-046994520c99']);
        \App\Models\Kelas::where('id', '01a0bcfd-7f78-7006-9dcc-07bd2e2b1936')->update(['wali_kelas_id' => 'a0b295e0-01e4-4eaf-9cf1-4df8e1e64f7b']);

        // ── SEED INITIAL CHAT CONVERSATIONS (PORTAL MESSAGES) ──
        \App\Models\PortalMessage::whereIn('student_id', $students->pluck('id'))->delete();

        foreach ($students as $student) {
            $isPondok = str_contains(strtolower($student->name), 'pondok') || str_contains(strtolower($student->educationUnit?->name ?? ''), 'abu ja\'far');
            $isSMP = str_contains(strtolower($student->name), 'smp') || str_contains(strtolower($student->educationUnit?->name ?? ''), 'smp');

            if ($isPondok) {
                // Chat dengan Wali Kelas Test
                \App\Models\PortalMessage::create([
                    'student_id' => $student->id,
                    'sender_user_id' => $waliKelas->user_id,
                    'recipient_user_id' => $parentUser->id,
                    'message' => 'Assalamu\'alaikum Warahmatullah, bapak/ibu wali santri. Alhamdulillah perkembangan tahfizh dan adab ananda di asrama sangat baik pekan ini.',
                    'read_at' => Carbon::now()->subHours(5),
                    'created_at' => Carbon::now()->subHours(5),
                ]);
                \App\Models\PortalMessage::create([
                    'student_id' => $student->id,
                    'sender_user_id' => $parentUser->id,
                    'recipient_user_id' => $waliKelas->user_id,
                    'message' => 'Wa\'alaikumussalam Warahmatullah ustadz. Alhamdulillah, syukron atas kabar baiknya. Kami titipkan ananda ya ustadz.',
                    'read_at' => Carbon::now()->subHours(4),
                    'created_at' => Carbon::now()->subHours(4),
                ]);
                \App\Models\PortalMessage::create([
                    'student_id' => $student->id,
                    'sender_user_id' => $waliKelas->user_id,
                    'recipient_user_id' => $parentUser->id,
                    'message' => 'Na\'am bapak/ibu, mohon terus didoakan agar ananda istiqomah dalam thalabul \'ilmi.',
                    'read_at' => null, // Unread message
                    'created_at' => Carbon::now()->subMinutes(30),
                ]);
            } elseif ($isSMP) {
                // Chat dengan Wali Kelas SMP (Ust. Budi Santoso)
                $waliSMPUser = User::where('email', 'wali24@dareliman.sch.id')->first();
                if ($waliSMPUser) {
                    \App\Models\PortalMessage::create([
                        'student_id' => $student->id,
                        'sender_user_id' => $waliSMPUser->id,
                        'recipient_user_id' => $parentUser->id,
                        'message' => 'Assalamu\'alaikum bapak/ibu. Selamat atas pencapaian ananda yang berhasil meraih juara 2 olimpiade matematika tingkat kota!',
                        'read_at' => Carbon::now()->subDay(),
                        'created_at' => Carbon::now()->subDay(),
                    ]);
                    \App\Models\PortalMessage::create([
                        'student_id' => $student->id,
                        'sender_user_id' => $parentUser->id,
                        'recipient_user_id' => $waliSMPUser->id,
                        'message' => 'Wa\'alaikumussalam ustadz. Alhamdulillah berkat bimbingan para asatidzah di sekolah.',
                        'read_at' => Carbon::now()->subHours(20),
                        'created_at' => Carbon::now()->subHours(20),
                    ]);
                }
            } else {
                // Chat dengan Wali Kelas SMA (Ust. Ahmad Fauzi)
                $waliSMAUser = User::where('email', 'wali27@dareliman.sch.id')->first();
                if ($waliSMAUser) {
                    \App\Models\PortalMessage::create([
                        'student_id' => $student->id,
                        'sender_user_id' => $waliSMAUser->id,
                        'recipient_user_id' => $parentUser->id,
                        'message' => 'Assalamu\'alaikum bapak/ibu wali murid. Berikut kami lampirkan rekap nilai try out UTBK ananda yang masuk 3 besar tingkat sekolah.',
                        'read_at' => null, // Unread message
                        'created_at' => Carbon::now()->subHours(2),
                    ]);
                }
            }
        }

        $totalNotes = StudentNote::whereIn('student_id', $students->pluck('id'))->count();
        $totalMessages = \App\Models\PortalMessage::whereIn('student_id', $students->pluck('id'))->count();
        $this->command?->info("Berhasil membuat {$totalNotes} catatan buku penghubung dan {$totalMessages} pesan chat untuk anak-anak dari orangtua@dareliman.sch.id.");
    }
}
