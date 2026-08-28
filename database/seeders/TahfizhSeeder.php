<?php

namespace Database\Seeders;

use App\Models\TahfizhDailyLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TahfizhSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Dapatkan atau buat Academic Year
        $academicYearId = DB::table('academic_years')
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->value('id');
        if (! $academicYearId) {
            $academicYearId = (string) Str::uuid();
            DB::table('academic_years')->insert([
                'id' => $academicYearId,
                'name' => '2026/2027',
                'start_date' => '2026-07-01',
                'end_date' => '2027-06-30',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Dapatkan atau buat Semester
        $semesterId = DB::table('semesters')
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('is_active')
            ->orderBy('sequence')
            ->value('id');
        if (! $semesterId) {
            $semesterId = (string) Str::uuid();
            DB::table('semesters')->insert([
                'id' => $semesterId,
                'academic_year_id' => $academicYearId,
                'name' => 'Semester Ganjil',
                'sequence' => 1,
                'start_date' => '2026-07-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Dapatkan atau buat Class di tabel classes
        $classId = DB::table('classes')
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId)
            ->orderBy('id')
            ->value('id');
        $classId ??= DB::table('classes')->orderBy('id')->value('id');
        if (! $classId) {
            $classId = (string) Str::uuid();
            DB::table('classes')->insert([
                'id' => $classId,
                'academic_year_id' => $academicYearId,
                'semester_id' => $semesterId,
                'name' => 'Kelas 7A Tahfizh',
                'level' => '7',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Dapatkan atau buat Siswa di tabel students
        $students = DB::table('students')->orderBy('id')->get();

        if ($students->isEmpty()) {
            $parents = DB::table('parents')->get();
            $sampleStudents = [
                ['full_name' => 'Muhammad Ahmad Al-Fatih', 'gender' => 'male', 'father' => 'Ahmad Fauzi', 'mother' => 'Siti Aminah', 'phone' => '081200010001', 'foto' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=250'],
                ['full_name' => 'Aisyah Humaira', 'gender' => 'female', 'father' => 'Budi Santoso', 'mother' => 'Nurlaila', 'phone' => '081200010002', 'foto' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=250'],
                ['full_name' => 'Abdullah Azzam', 'gender' => 'male', 'father' => 'Hendra Kurniawan', 'mother' => 'Fatimah', 'phone' => '081200010003', 'foto' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=250'],
                ['full_name' => 'Fatimah Az-Zahra', 'gender' => 'female', 'father' => 'Ahmad Fauzi', 'mother' => 'Siti Aminah', 'phone' => '081200010001', 'foto' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=250'],
                ['full_name' => 'Umar bin Abdul Aziz', 'gender' => 'male', 'father' => 'Budi Santoso', 'mother' => 'Nurlaila', 'phone' => '081200010002', 'foto' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=250'],
            ];

            foreach ($sampleStudents as $idx => $sData) {
                $parent = $parents->get($idx % max(1, $parents->count()));
                DB::table('students')->insert([
                    'id' => (string) Str::uuid(),
                    'parent_id' => $parent?->id,
                    'full_name' => $sData['full_name'],
                    'nis' => 'TFZ-'.(1000 + $idx + 1),
                    'nisn' => '0098'.(765400 + $idx + 1),
                    'gender' => $sData['gender'],
                    'class_id' => $classId,
                    'is_active' => true,
                    'metadata' => json_encode([
                        'foto_url' => $sData['foto'],
                        'nama_ayah' => $sData['father'],
                        'nama_ibu' => $sData['mother'],
                        'hp_ayah' => $sData['phone'],
                        'hp_ibu' => $sData['phone'],
                        'orang_tua' => [
                            'nama_ayah' => $sData['father'],
                            'nama_ibu' => $sData['mother'],
                            'no_hp' => $sData['phone'],
                        ],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $students = DB::table('students')->orderBy('id')->get();
        }

        // 5. Tanggal Senin minggu ini
        $monday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $daysData = [
            0 => [
                'day_name' => 'Senin',
                'tilawah_text' => 'QS. Al-Baqarah: 1 - 25',
                'tilawah_baris' => 15,
                'hafalan_surah_number' => 78,
                'hafalan_surah_name' => 'An-Naba\'',
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 15,
                'hafalan_baris' => 10,
                'murajaah_text' => 'Juz 29 (Surah Al-Mulk - Al-Qalam)',
                'murajaah_lembar' => 3.0,
                'audio_url' => '/storage/tahfizh_audio/sample_murajaah_senin.mp3',
                'notes_teacher' => 'Makhraj huruf ra dan dad sudah fasih, tajwid nun mati sangat baik.',
                'notes_parent' => 'Anak sudah mengulang hafalan 3 kali sebelum tidur.',
            ],
            1 => [
                'day_name' => 'Selasa',
                'tilawah_text' => 'QS. Al-Baqarah: 26 - 50',
                'tilawah_baris' => 18,
                'hafalan_surah_number' => 78,
                'hafalan_surah_name' => 'An-Naba\'',
                'hafalan_ayah_start' => 16,
                'hafalan_ayah_end' => 30,
                'hafalan_baris' => 10,
                'murajaah_text' => 'Juz 29 (Surah Al-Haqqah - Al-Ma\'arij)',
                'murajaah_lembar' => 2.5,
                'audio_url' => '/storage/tahfizh_audio/sample_murajaah_selasa.mp3',
                'notes_teacher' => 'Setoran hafalan lancar tanpa terbata-bata (mutqin).',
                'notes_parent' => 'Anak sangat antusias setoran pagi hari.',
            ],
            2 => [
                'day_name' => 'Rabu',
                'tilawah_text' => 'QS. Al-Baqarah: 51 - 75',
                'tilawah_baris' => 16,
                'hafalan_surah_number' => 78,
                'hafalan_surah_name' => 'An-Naba\'',
                'hafalan_ayah_start' => 31,
                'hafalan_ayah_end' => 40,
                'hafalan_baris' => 8,
                'murajaah_text' => 'Juz 30 (Surah An-Naba\' - An-Nazi\'at)',
                'murajaah_lembar' => 3.0,
                'audio_url' => null,
                'notes_teacher' => 'Alhamdulillah Surah An-Naba\' selesai dengan baik. Besok lanjut An-Nazi\'at.',
                'notes_parent' => 'Disimak saat bada maghrib di rumah.',
            ],
            3 => [
                'day_name' => 'Kamis',
                'tilawah_text' => 'QS. Al-Baqarah: 76 - 100',
                'tilawah_baris' => 20,
                'hafalan_surah_number' => 79,
                'hafalan_surah_name' => 'An-Nazi\'at',
                'hafalan_ayah_start' => 1,
                'hafalan_ayah_end' => 15,
                'hafalan_baris' => 12,
                'murajaah_text' => 'Juz 30 (Surah \'Abasa - At-Takwir)',
                'murajaah_lembar' => 4.0,
                'audio_url' => '/storage/tahfizh_audio/sample_murajaah_kamis.mp3',
                'notes_teacher' => 'Awal Surah An-Nazi\'at sangat lancar, mad jaiz munfasil tepat.',
                'notes_parent' => 'Sudah direkam dan diunggah audio suaranya.',
            ],
            4 => [
                'day_name' => 'Jumat',
                'tilawah_text' => 'QS. Al-Kahfi: 1 - 30 (Sunnah Jumat)',
                'tilawah_baris' => 25,
                'hafalan_surah_number' => 79,
                'hafalan_surah_name' => 'An-Nazi\'at',
                'hafalan_ayah_start' => 16,
                'hafalan_ayah_end' => 30,
                'hafalan_baris' => 10,
                'murajaah_text' => 'Juz 30 (Surah Al-Infitar - Al-Mutaffifin)',
                'murajaah_lembar' => 2.0,
                'audio_url' => null,
                'notes_teacher' => 'Barakallah, tilawah Surah Al-Kahfi terlaksana.',
                'notes_parent' => 'Mendampingi anak membaca Al-Kahfi bersama.',
            ],
            5 => [
                'day_name' => 'Sabtu',
                'tilawah_text' => 'QS. Al-Baqarah: 101 - 120',
                'tilawah_baris' => 15,
                'hafalan_surah_number' => 79,
                'hafalan_surah_name' => 'An-Nazi\'at',
                'hafalan_ayah_start' => 31,
                'hafalan_ayah_end' => 46,
                'hafalan_baris' => 10,
                'murajaah_text' => 'Murajaah Pekanan (Surah An-Naba\' & An-Nazi\'at Full)',
                'murajaah_lembar' => 5.0,
                'audio_url' => '/storage/tahfizh_audio/sample_murajaah_sabtu.mp3',
                'notes_teacher' => 'Pekanan hafalan sempurna, siap diuji saat tasmi\'.',
                'notes_parent' => 'Murajaah lengkap 2 surah di rumah.',
            ],
            6 => [
                'day_name' => 'Ahad',
                'tilawah_text' => 'QS. Al-Baqarah: 121 - 141',
                'tilawah_baris' => 16,
                'hafalan_surah_number' => null,
                'hafalan_surah_name' => null,
                'hafalan_ayah_start' => null,
                'hafalan_ayah_end' => null,
                'hafalan_baris' => 0,
                'murajaah_text' => 'Murajaah Mandiri Bersama Orang Tua (Juz 30)',
                'murajaah_lembar' => 5.0,
                'audio_url' => null,
                'notes_teacher' => 'Libur setoran baru, fokus penguatan murajaah mandiri.',
                'notes_parent' => 'Anak murajaah bersama keluarga di rumah.',
            ],
        ];

        foreach ($students as $student) {
            $studentClassId = $student->class_id ?? $classId;

            for ($i = 0; $i < 7; $i++) {
                $date = (clone $monday)->addDays($i)->toDateString();
                $dayData = $daysData[$i];

                TahfizhDailyLog::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'record_date' => $date,
                    ],
                    [
                        'class_id' => $studentClassId,
                        'day_name' => $dayData['day_name'],
                        'tilawah_text' => $dayData['tilawah_text'],
                        'tilawah_baris' => $dayData['tilawah_baris'],
                        'hafalan_surah_number' => $dayData['hafalan_surah_number'],
                        'hafalan_surah_name' => $dayData['hafalan_surah_name'],
                        'hafalan_ayah_start' => $dayData['hafalan_ayah_start'],
                        'hafalan_ayah_end' => $dayData['hafalan_ayah_end'],
                        'hafalan_baris' => $dayData['hafalan_baris'],
                        'murajaah_text' => $dayData['murajaah_text'],
                        'murajaah_lembar' => $dayData['murajaah_lembar'],
                        'audio_url' => $dayData['audio_url'],
                        'notes_teacher' => $dayData['notes_teacher'],
                        'notes_parent' => $dayData['notes_parent'],
                        'signature_teacher' => 'Guru Verified',
                        'signature_parent' => 'Ortu Verified',
                        'status' => 'submitted',
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('Seeder TahfizhDailyLog berhasil diisi untuk '.count($students).' siswa!');
    }
}
