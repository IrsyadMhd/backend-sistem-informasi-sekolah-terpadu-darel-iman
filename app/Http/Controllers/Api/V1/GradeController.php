<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Database\Eloquent\Builder;
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
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedQuery($request->user())
            ->with(['student', 'subject', 'academicYear', 'semester', 'kelas']);

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
            'status' => 'success',
            'message' => 'Daftar nilai siswa berhasil diambil.',
            'data' => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'subject_id' => 'required|uuid|exists:subjects,id',
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id' => 'required|uuid|exists:semesters,id',
            'kelas_id' => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'score_assignment' => 'nullable|numeric|min:0|max:100',
            'score_quiz' => 'nullable|numeric|min:0|max:100',
            'score_project' => 'nullable|numeric|min:0|max:100',
            'score_midterm' => 'nullable|numeric|min:0|max:100',
            'score_final' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();
        $this->assertGradeContext($request->user(), $validated);

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
            'status' => 'success',
            'message' => 'Nilai siswa berhasil disimpan.',
            'data' => $grade->load(['student', 'subject', 'semester']),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $grade = $this->scopedQuery($request->user())->with([
            'student', 'subject', 'academicYear', 'semester', 'kelas', 'schoolClass',
        ])->find($id);

        if (! $grade) {
            return response()->json(['status' => 'error', 'message' => 'Nilai tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $grade]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $grade = $this->scopedQuery($request->user())->find($id);

        if (! $grade) {
            return response()->json(['status' => 'error', 'message' => 'Nilai tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'score_assignment' => 'nullable|numeric|min:0|max:100',
            'score_quiz' => 'nullable|numeric|min:0|max:100',
            'score_project' => 'nullable|numeric|min:0|max:100',
            'score_midterm' => 'nullable|numeric|min:0|max:100',
            'score_final' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $validated['updated_by'] = Auth::id();
        $grade->update($validated);

        // Recalculate final score
        $finalScore = $grade->fresh()->hitungNilaiAkhir();
        $grade->update([
            'final_score' => $finalScore,
            'grade_letter' => StudentGrade::getGradeLetter($finalScore),
            'is_passed' => $finalScore >= 75.0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Nilai siswa berhasil diperbarui.',
            'data' => $grade->fresh(['student', 'subject']),
        ]);
    }

    /**
     * Rekap nilai siswa dalam satu kelas per semester.
     */
    public function rekap(Request $request): JsonResponse
    {
        $request->validate([
            'kelas_id' => 'nullable|uuid',
            'semester_id' => 'required|uuid|exists:semesters,id',
        ]);

        $query = $this->scopedQuery($request->user())
            ->with(['student:id,nis,full_name', 'subject:id,code,name'])
            ->where('semester_id', $request->query('semester_id'));

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        $data = $query->get()->groupBy('student_id')->map(function ($gradesByStudent) {
            $student = $gradesByStudent->first()->student;

            return [
                'student' => [
                    'id' => $student->id,
                    'nis' => $student->nis,
                    'nama' => $student->full_name,
                ],
                'nilai' => $gradesByStudent->map(fn ($g) => [
                    'mapel' => $g->subject?->name,
                    'kode_mapel' => $g->subject?->code,
                    'nilai_akhir' => $g->final_score,
                    'grade' => $g->grade_letter,
                    'lulus' => $g->is_passed,
                ])->values(),
                'rata_rata' => round($gradesByStudent->avg('final_score'), 2),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap nilai berhasil diambil.',
            'data' => $data,
        ]);
    }

    private function scopedQuery(User $user): Builder
    {
        $query = StudentGrade::query()
            ->whereIn('student_id', $this->accessScope->accessibleStudents($user)->select('id'));

        if (! $this->isTeachingAssignmentRestricted($user)) {
            return $query;
        }

        $schedules = $this->accessScope->accessibleSchedules($user)
            ->get(['kelas_id', 'class_id', 'subject_id']);

        return $query->where(function (Builder $scope) use ($schedules) {
            foreach ($schedules as $schedule) {
                $scope->orWhere(function (Builder $assignment) use ($schedule) {
                    $assignment->where('subject_id', $schedule->subject_id);
                    if ($schedule->kelas_id) {
                        $assignment->where('kelas_id', $schedule->kelas_id);
                    } elseif ($schedule->class_id) {
                        $assignment->where('class_id', $schedule->class_id);
                    } else {
                        $assignment->whereRaw('1 = 0');
                    }
                });
            }

            if ($schedules->isEmpty()) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    private function assertGradeContext(User $user, array $data): void
    {
        $student = $this->accessScope->accessibleStudents($user)->findOrFail($data['student_id']);

        if (! $this->isTeachingAssignmentRestricted($user)) {
            return;
        }

        $kelasId = $data['kelas_id'] ?? $student->kelas_id;
        $classId = $data['class_id'] ?? $student->class_id;
        $submittedKelasId = $data['kelas_id'] ?? null;
        $submittedClassId = $data['class_id'] ?? null;
        abort_unless(
            (! $submittedKelasId || $submittedKelasId === $student->kelas_id)
                && (! $submittedClassId || $submittedClassId === $student->class_id),
            403,
            'Siswa tidak berada pada kelas yang dipilih.'
        );
        abort_unless($kelasId || $classId, 403, 'Siswa belum terhubung dengan kelas.');

        $allowed = $this->accessScope->accessibleSchedules($user)
            ->where('subject_id', $data['subject_id'])
            ->where(function (Builder $schedule) use ($kelasId, $classId) {
                $schedule->when($kelasId, fn (Builder $query, string $id) => $query->orWhere('kelas_id', $id))
                    ->when($classId, fn (Builder $query, string $id) => $query->orWhere('class_id', $id));
            })
            ->exists();

        abort_unless($allowed, 403, 'Mata pelajaran tidak termasuk assignment guru pada kelas siswa.');
    }

    private function isTeachingAssignmentRestricted(User $user): bool
    {
        $normalize = static fn (string $role): string => strtolower((string) preg_replace('/[\s_-]+/', '', $role));
        $roles = $user->getRoleNames()->map($normalize);
        $management = collect([
            'Super Admin', 'super_admin', 'super-admin', 'Superadmin',
            'Yayasan', 'Ketua Yayasan', 'Pengurus Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan',
            'Kepala Sekolah', 'Divisi Pendidikan', 'Kepala Bidang Pendidikan',
            'Waka Kurikulum', 'Wakil Kurikulum', 'Tata Usaha', 'Operator',
        ])->map($normalize);

        if ($roles->intersect($management)->isNotEmpty()) {
            return false;
        }

        return $roles->intersect(collect([
            'Guru', 'Guru Mata Pelajaran', 'Guru PAI', 'Pembimbing', 'Wali Kelas',
            'Guru Tahfizh', 'Guru BK', 'Musyrif', 'Musyrifah', 'Musyrif / Musyrifah',
        ])->map($normalize))->isNotEmpty();
    }
}
