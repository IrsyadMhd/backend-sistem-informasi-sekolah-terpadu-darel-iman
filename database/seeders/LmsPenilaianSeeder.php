<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class LmsPenilaianSeeder extends Seeder
{
    public function run(): void
    {
        $siswa = Student::orderBy('id')->first();
        $kelas = Kelas::orderBy('id')->first();
        $subject = Subject::orderBy('id')->first();
        $semester = Semester::orderBy('id')->first();
        $academicYear = AcademicYear::orderBy('id')->first();

        if (! $siswa || ! $subject || ! $semester) {
            return;
        }

        $scoreAssignment = 85.0;
        $scoreQuiz = 80.0;
        $scoreMidterm = 88.0;
        $scoreFinal = 90.0;

        $bobotTugas = 20.0;
        $bobotUh = 25.0;
        $bobotUts = 25.0;
        $bobotUas = 30.0;

        $totalWeight = $bobotTugas + $bobotUh + $bobotUts + $bobotUas;
        $finalScore = round(
            (($scoreAssignment * $bobotTugas) + ($scoreQuiz * $bobotUh) + ($scoreMidterm * $bobotUts) + ($scoreFinal * $bobotUas)) / $totalWeight,
            2
        );

        StudentGrade::updateOrCreate(
            [
                'student_id' => $siswa->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $academicYear?->id,
                'semester_id' => $semester->id,
            ],
            [
                'kelas_id' => $kelas?->id,
                'score_assignment' => $scoreAssignment,
                'score_quiz' => $scoreQuiz,
                'score_midterm' => $scoreMidterm,
                'score_final' => $scoreFinal,
                'final_score' => $finalScore,
                'grade_letter' => StudentGrade::getGradeLetter($finalScore),
                'is_passed' => $finalScore >= 75.0,
                'notes' => 'Prestasi sangat baik dalam pengerjaan CBT dan tugas LMS.',
                'metadata' => [
                    'bobot_tugas' => $bobotTugas,
                    'bobot_uh' => $bobotUh,
                    'bobot_uts' => $bobotUts,
                    'bobot_uas' => $bobotUas,
                    'nilai_kkm' => 75.0,
                    'synced_at' => now()->toIso8601String(),
                ],
            ]
        );
    }
}
