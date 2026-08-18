<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LmsPengumpulanTugasSeeder extends Seeder
{
    public function run(): void
    {
        $penugasans = LmsPenugasan::all();
        $students = Student::orderBy('id')->get();
        $gurus = Employee::orderBy('id')->get();
        $adminUser = User::orderBy('id')->first();

        if ($penugasans->isEmpty() || $students->isEmpty()) {
            return;
        }

        $guru = $gurus->first();

        $statuses = [
            [
                'status' => 'dinilai',
                'jawaban' => 'Tugas telah selesai dikerjakan sesuai petunjuk dan petunjuk praktikum.',
                'file' => 'https://example.com/tugas_fisika_ahmad.pdf',
                'link' => 'https://github.com/student/fisika-lab',
                'nilai' => 95.0,
                'catatan' => 'Sangat baik, penjelasan rumus dan grafik sangat detail.',
            ],
            [
                'status' => 'dikumpulkan',
                'jawaban' => 'Berikut link penyelesaian tugas dan lembar analisa data.',
                'file' => 'https://example.com/tugas_analisis_fatimah.pdf',
                'link' => 'https://docs.google.com/document/d/sample',
                'nilai' => null,
                'catatan' => null,
            ],
            [
                'status' => 'terlambat',
                'jawaban' => 'Mohon maaf Ustadz kumpul terlambat karena ada kendala jaringan.',
                'file' => 'https://example.com/tugas_terlambat_zayd.pdf',
                'link' => null,
                'nilai' => 80.0,
                'catatan' => 'Tetap dinilai meski terlambat 1 jam. Perbaiki kedisiplinan.',
            ],
            [
                'status' => 'revisi',
                'jawaban' => 'Draft awal tugas analisis.',
                'file' => 'https://example.com/draft_tugas.pdf',
                'link' => null,
                'nilai' => 65.0,
                'catatan' => 'Tolong lengkapi kesimpulan dan lampiran grafik pada bab 3.',
            ],
        ];

        foreach ($penugasans as $pIdx => $penugasan) {
            $sampleStudents = $students->take(4);
            foreach ($sampleStudents as $sIdx => $student) {
                $preset = $statuses[$sIdx % count($statuses)];

                LmsPengumpulanTugas::firstOrCreate(
                    [
                        'penugasan_id' => $penugasan->id,
                        'siswa_id' => $student->id,
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'jawaban_teks' => $preset['jawaban'],
                        'file_path' => $preset['file'],
                        'url_link' => $preset['link'],
                        'status' => $preset['status'],
                        'waktu_kumpul' => now()->subDays(rand(1, 4)),
                        'nilai_guru' => $preset['nilai'],
                        'catatan_guru' => $preset['catatan'],
                        'waktu_dinilai' => $preset['nilai'] ? now()->subHours(rand(1, 12)) : null,
                        'dinilai_oleh' => $preset['nilai'] && $guru ? $guru->id : null,
                        'created_by' => $adminUser ? $adminUser->id : null,
                    ]
                );
            }
        }
    }
}
