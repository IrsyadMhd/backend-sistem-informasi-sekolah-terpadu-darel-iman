<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentGradesSeeder extends Seeder
{
    /**
     * Run the database seeds for Student Grades.
     */
    public function run(): void
    {
        $academicYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $semester = Semester::where('is_active', true)->first() ?? Semester::first();
        $students = Student::all();
        $subjects = Subject::all();
        $classes = Kelas::all();

        if (!$academicYear || !$semester || $students->isEmpty() || $subjects->isEmpty()) {
            return;
        }

        $notesTemplates = [
            'Sangat menguasai materi pembelajaran, tingkatkan terus prestasimu!',
            'Menunjukkan pemahaman yang baik dalam setiap tugas dan ujian.',
            'Cukup baik, perlu ditingkatkan konsistensi dalam pengerjaan proyek.',
            'Tingkatkan fokus pada latihan soal dan pemahaman konsep dasar.',
        ];

        foreach ($students as $student) {
            $studentClass = $classes->firstWhere('id', $student->class_id) ?? $classes->first();

            foreach ($subjects as $subject) {
                // Generate realistic random grades
                $assignment = rand(75, 95);
                $quiz = rand(70, 92);
                $project = rand(80, 98);
                $midterm = rand(72, 90);
                $finalExam = rand(75, 95);

                // Weighted final score calculation (20% assignment, 15% quiz, 25% project, 20% midterm, 20% final)
                $finalScore = round(($assignment * 0.20) + ($quiz * 0.15) + ($project * 0.25) + ($midterm * 0.20) + ($finalExam * 0.20), 2);

                $gradeLetter = 'B';
                if ($finalScore >= 90) {
                    $gradeLetter = 'A';
                } elseif ($finalScore >= 80) {
                    $gradeLetter = 'B';
                } elseif ($finalScore >= 70) {
                    $gradeLetter = 'C';
                } else {
                    $gradeLetter = 'D';
                }

                $kkm = $subject->kkm ?? 75.00;
                $isPassed = $finalScore >= $kkm;
                $note = $notesTemplates[array_rand($notesTemplates)];

                DB::table('student_grades')->updateOrInsert(
                    [
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'academic_year_id' => $academicYear->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'kelas_id' => $studentClass?->id,
                        'score_assignment' => $assignment,
                        'score_quiz' => $quiz,
                        'score_project' => $project,
                        'score_midterm' => $midterm,
                        'score_final' => $finalExam,
                        'final_score' => $finalScore,
                        'grade_letter' => $gradeLetter,
                        'is_passed' => $isPassed,
                        'notes' => $note,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
