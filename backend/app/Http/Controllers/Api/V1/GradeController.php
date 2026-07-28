<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GradeController
 *
 * CRUD Nilai Siswa / Raport.
 * Endpoint: /api/v1/grades
 */
class GradeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StudentGrade::with(['student', 'subject', 'academicYear', 'semester', 'kelas']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->query('subject_id'));
        }
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }
        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }
        if ($request->boolean('lulus_only')) {
            $query->where('is_passed', true);
        }

        $perPage = (int) $request->query('per_page', 20);
        $data = $query->orderBy('student_id')->orderBy('subject_id')->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar nilai siswa berhasil diambil.',
            'data'    => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'       => 'required|uuid|exists:students,id',
            'subject_id'       => 'required|uuid|exists:subjects,id',
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id'      => 'required|uuid|exists:semesters,id',
            'kelas_id'         => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id'         => 'nullable|uuid|exists:classes,id',
            'score_assignment' => 'nullable|numeric|min:0|max:100',
            'score_quiz'       => 'nullable|numeric|min:0|max:100',
            'score_project'    => 'nullable|numeric|min:0|max:100',
            'score_midterm'    => 'nullable|numeric|min:0|max:100',
            'score_final'      => 'nullable|numeric|min:0|max:100',
            'notes'            => 'nullable|string',
            'metadata'         => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();

        // Hitung nilai akhir secara otomatis
        $grade = new StudentGrade($validated);
        $finalScore = $grade->hitungNilaiAkhir();
        $validated['final_score'] = $finalScore;
        $validated['grade_letter'] = StudentGrade::getGradeLetter($finalScore);

        // Tentukan kelulusan berdasarkan KKM dari modul_semesters (default 75)
        $kkm = 75.0;
        $validated['is_passed'] = $finalScore >= $kkm;

        $grade = StudentGrade::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Nilai siswa berhasil disimpan.',
            'data'    => $grade->load(['student', 'subject', 'semester']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $grade = StudentGrade::with([
            'student', 'subject', 'academicYear', 'semester', 'kelas', 'schoolClass',
        ])->find($id);

        if (! $grade) {
            return response()->json(['status' => 'error', 'message' => 'Nilai tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $grade]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $grade = StudentGrade::find($id);

        if (! $grade) {
            return response()->json(['status' => 'error', 'message' => 'Nilai tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'score_assignment' => 'nullable|numeric|min:0|max:100',
            'score_quiz'       => 'nullable|numeric|min:0|max:100',
            'score_project'    => 'nullable|numeric|min:0|max:100',
            'score_midterm'    => 'nullable|numeric|min:0|max:100',
            'score_final'      => 'nullable|numeric|min:0|max:100',
            'notes'            => 'nullable|string',
            'metadata'         => 'nullable|array',
        ]);

        $validated['updated_by'] = Auth::id();
        $grade->update($validated);

        // Recalculate final score
        $finalScore = $grade->fresh()->hitungNilaiAkhir();
        $grade->update([
            'final_score'  => $finalScore,
            'grade_letter' => StudentGrade::getGradeLetter($finalScore),
            'is_passed'    => $finalScore >= 75.0,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Nilai siswa berhasil diperbarui.',
            'data'    => $grade->fresh(['student', 'subject']),
        ]);
    }

    /**
     * Rekap nilai siswa dalam satu kelas per semester.
     */
    public function rekap(Request $request): JsonResponse
    {
        $request->validate([
            'kelas_id'    => 'nullable|uuid',
            'semester_id' => 'required|uuid|exists:semesters,id',
        ]);

        $query = StudentGrade::with(['student:id,nis,full_name', 'subject:id,code,name'])
            ->where('semester_id', $request->query('semester_id'));

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        $data = $query->get()->groupBy('student_id')->map(function ($gradesByStudent) {
            $student = $gradesByStudent->first()->student;
            return [
                'student' => [
                    'id'       => $student->id,
                    'nis'      => $student->nis,
                    'nama'     => $student->full_name,
                ],
                'nilai' => $gradesByStudent->map(fn ($g) => [
                    'mapel'        => $g->subject?->name,
                    'kode_mapel'   => $g->subject?->code,
                    'nilai_akhir'  => $g->final_score,
                    'grade'        => $g->grade_letter,
                    'lulus'        => $g->is_passed,
                ])->values(),
                'rata_rata' => round($gradesByStudent->avg('final_score'), 2),
            ];
        })->values();

        return response()->json([
            'status'  => 'success',
            'message' => 'Rekap nilai berhasil diambil.',
            'data'    => $data,
        ]);
    }
}
