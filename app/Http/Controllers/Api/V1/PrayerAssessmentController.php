<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ParentModel;
use App\Models\PrayerAssessmentItem;
use App\Models\PrayerGradeRule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentPrayerAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrayerAssessmentController extends Controller
{
    /**
     * Ambil daftar 62 target Doa Harian beserta status aktif & bobotnya.
     */
    public function items(Request $request): JsonResponse
    {
        $query = PrayerAssessmentItem::query()->orderBy('order_number');

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'total' => $items->count(),
        ]);
    }

    /**
     * Tambah item doa baru (Kepsek, Divisi Pendidikan, TU, Super Admin).
     */
    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'group' => 'nullable|string|max:100',
            'max_score' => 'nullable|numeric|min:0|max:100',
            'passing_score' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $item = PrayerAssessmentItem::create([
            'id' => (string) Str::uuid(),
            'order_number' => $validated['order_number'],
            'name' => $validated['name'],
            'group' => $validated['group'] ?? 'Doa Doa Harian',
            'max_score' => $validated['max_score'] ?? 100.00,
            'passing_score' => $validated['passing_score'] ?? 75.00,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item target doa berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * Update nama doa, nomor urut, atau bobot nilai (Kepsek, Divisi Pendidikan, TU).
     */
    public function updateItem(Request $request, string $id): JsonResponse
    {
        $item = PrayerAssessmentItem::findOrFail($id);

        $validated = $request->validate([
            'order_number' => 'sometimes|integer|min:1',
            'name' => 'sometimes|string|max:255',
            'group' => 'nullable|string|max:100',
            'max_score' => 'sometimes|numeric|min:0|max:100',
            'passing_score' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['updated_by'] = auth()->id();
        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan item doa berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    /**
     * Ambil aturan konversi grade (Mumtaz, Jayyid Jiddan, Jayyid, Maqbul).
     */
    public function gradeRules(): JsonResponse
    {
        $rules = PrayerGradeRule::orderBy('order_index')->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Update aturan grade (Kepala Sekolah, Divisi Pendidikan, TU).
     */
    public function updateGradeRules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|uuid',
            'rules.*.grade' => 'required|string|max:5',
            'rules.*.label' => 'required|string|max:100',
            'rules.*.min_score' => 'required|numeric|min:0|max:100',
            'rules.*.max_score' => 'required|numeric|min:0|max:100',
            'rules.*.description' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['rules'] as $ruleData) {
                PrayerGradeRule::where('id', $ruleData['id'])->update([
                    'label' => $ruleData['label'],
                    'min_score' => $ruleData['min_score'],
                    'max_score' => $ruleData['max_score'],
                    'description' => $ruleData['description'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan skala grade poin doa berhasil diperbarui.',
            'data' => PrayerGradeRule::orderBy('order_index')->get(),
        ]);
    }

    /**
     * Ambil lembar penilaian doa siswa (Format tabel fisik: No, Doa Doa Harian, Poin, Paraf).
     */
    public function studentSheet(string $studentId): JsonResponse
    {
        $student = Student::with(['schoolClass', 'educationUnit'])->findOrFail($studentId);

        $items = PrayerAssessmentItem::where('is_active', true)
            ->orderBy('order_number')
            ->get();

        $assessments = StudentPrayerAssessment::where('student_id', $studentId)
            ->get()
            ->keyBy('prayer_item_id');

        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();

        $totalScore = 0;
        $scoredCount = 0;
        $passedCount = 0;

        $rows = $items->map(function ($item) use ($assessments, &$totalScore, &$scoredCount, &$passedCount) {
            $assessment = $assessments->get($item->id);
            $score = $assessment ? (float) $assessment->score : null;
            $isPassed = $assessment ? (bool) $assessment->is_passed : false;

            if ($score !== null) {
                $totalScore += $score;
                $scoredCount++;
                if ($isPassed) $passedCount++;
            }

            return [
                'id' => $item->id,
                'no' => $item->order_number,
                'nama' => $item->name,
                'grup' => $item->group,
                'passing_score' => (float) $item->passing_score,
                'poin' => $score,
                'grade' => $assessment ? $assessment->grade : null,
                'is_passed' => $isPassed,
                'paraf_name' => $assessment ? $assessment->paraf_name : null,
                'paraf_at' => $assessment && $assessment->paraf_at ? $assessment->paraf_at->format('d/m/Y H:i') : null,
                'notes' => $assessment ? $assessment->notes : null,
            ];
        });

        $averageScore = $scoredCount > 0 ? round($totalScore / $scoredCount, 2) : 0;
        $overallGradeInfo = PrayerGradeRule::resolveGrade($averageScore);

        return response()->json([
            'success' => true,
            'student' => [
                'id' => $student->id,
                'name' => $student->nama_lengkap ?? $student->name,
                'nis' => $student->nis,
                'class' => $student->schoolClass?->nama_kelas,
                'unit' => $student->educationUnit?->name,
                'academic_year' => $activeYear?->name,
                'semester' => $activeSemester?->name,
            ],
            'summary' => [
                'total_items' => $items->count(),
                'tested_items' => $scoredCount,
                'passed_items' => $passedCount,
                'average_score' => $averageScore,
                'total_points' => round($totalScore, 2),
                'grade' => $overallGradeInfo['grade'],
                'grade_label' => $overallGradeInfo['label'],
                'grade_description' => $overallGradeInfo['description'] ?? '',
            ],
            'items' => $rows,
        ]);
    }

    /**
     * Simpan nilai poin & paraf per siswa (Guru Tahfizh, Guru PAI, Wali Kelas, Kepsek).
     */
    public function saveStudentScores(Request $request, string $studentId): JsonResponse
    {
        $student = Student::findOrFail($studentId);

        $validated = $request->validate([
            'scores' => 'required|array',
            'scores.*.prayer_item_id' => 'required|uuid|exists:prayer_assessment_items,id',
            'scores.*.score' => 'nullable|numeric|min:0|max:100',
            'scores.*.is_paraf' => 'nullable|boolean',
            'scores.*.notes' => 'nullable|string|max:500',
        ]);

        $activeYear = AcademicYear::where('is_active', true)->first();
        $activeSemester = Semester::where('is_active', true)->first();
        $user = auth()->user();

        $items = PrayerAssessmentItem::whereIn('id', collect($validated['scores'])->pluck('prayer_item_id'))->get()->keyBy('id');

        DB::transaction(function () use ($studentId, $validated, $activeYear, $activeSemester, $user, $items) {
            foreach ($validated['scores'] as $entry) {
                $item = $items->get($entry['prayer_item_id']);
                if (! $item) continue;

                $score = isset($entry['score']) && $entry['score'] !== null ? (float) $entry['score'] : null;
                $isPassed = $score !== null ? $score >= (float) $item->passing_score : false;
                $gradeInfo = $score !== null ? PrayerGradeRule::resolveGrade($score) : null;

                $existing = StudentPrayerAssessment::where('student_id', $studentId)
                    ->where('prayer_item_id', $item->id)
                    ->first();

                $parafName = $existing?->paraf_name;
                $parafAt = $existing?->paraf_at;
                $parafBy = $existing?->paraf_by;

                if (! empty($entry['is_paraf'])) {
                    $parafName = $user->name;
                    $parafAt = now();
                    $parafBy = $user->id;
                }

                StudentPrayerAssessment::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'prayer_item_id' => $item->id,
                    ],
                    [
                        'id' => $existing ? $existing->id : (string) Str::uuid(),
                        'academic_year_id' => $activeYear?->id,
                        'semester_id' => $activeSemester?->id,
                        'score' => $score,
                        'grade' => $gradeInfo ? $gradeInfo['grade'] : null,
                        'is_passed' => $isPassed,
                        'paraf_by' => $parafBy,
                        'paraf_name' => $parafName,
                        'paraf_at' => $parafAt,
                        'notes' => $entry['notes'] ?? ($existing ? $existing->notes : null),
                        'updated_by' => $user->id,
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Nilai poin & paraf hafalan doa ananda berhasil disimpan.',
        ]);
    }

    /**
     * Endpoint untuk Orang Tua di Android melihat nilai ananda secara transparan.
     */
    public function parentStudentSheet(Request $request, string $studentId): JsonResponse
    {
        $user = auth()->user();
        
        // Verifikasi kepemilikan ananda jika user adalah orang tua
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            $isChild = $parent->students()->where('students.id', $studentId)->exists();
            if (! $isChild && ! $user->can('student.view_all') && ! $user->can('prayer_assessment.view')) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Ananda bukan tanggungan Anda.'], 403);
            }
        }

        return $this->studentSheet($studentId);
    }
}
