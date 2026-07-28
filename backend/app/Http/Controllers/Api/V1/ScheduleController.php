<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ScheduleController
 *
 * CRUD Jadwal Pelajaran.
 * Endpoint: /api/v1/schedules
 */
class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClassSchedule::with([
            'kelas',       // tbl_kelas (primer)
            'schoolClass', // classes (legacy)
            'employee',    // employees (primer)
            'teacher',     // teachers (legacy)
            'subject',
            'academicYear',
            'semester',
        ]);

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->query('teacher_id'));
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

        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', (int) $request->query('day_of_week'));
        }

        if ($request->boolean('aktif_only', true)) {
            $query->where('is_active', true);
        }

        $perPage = (int) $request->query('per_page', 50);
        $data = $query->orderBy('day_of_week')->orderBy('time_start')->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daftar jadwal pelajaran berhasil diambil.',
            'data'    => $data,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kelas_id'        => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id'        => 'nullable|uuid|exists:classes,id',
            'employee_id'     => 'nullable|uuid|exists:employees,id',
            'teacher_id'      => 'nullable|uuid|exists:teachers,id',
            'subject_id'      => 'required|uuid|exists:subjects,id',
            'classroom_id'    => 'nullable|uuid|exists:classrooms,id',
            'academic_year_id' => 'required|uuid|exists:academic_years,id',
            'semester_id'     => 'required|uuid|exists:semesters,id',
            'day_of_week'     => 'required|integer|min:1|max:7',
            'time_start'      => 'required|date_format:H:i',
            'time_end'        => 'required|date_format:H:i|after:time_start',
            'week_type'       => 'nullable|string|in:all,odd,even',
            'is_active'       => 'nullable|boolean',
            'metadata'        => 'nullable|array',
        ]);

        // Validasi: harus ada minimal satu referensi kelas
        if (empty($validated['kelas_id']) && empty($validated['class_id'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Harus mengisi salah satu dari kelas_id (tbl_kelas) atau class_id (classes).',
            ], 422);
        }

        $validated['created_by'] = Auth::id();

        $schedule = ClassSchedule::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal pelajaran berhasil ditambahkan.',
            'data'    => $schedule->load(['kelas', 'employee', 'subject', 'semester']),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $schedule = ClassSchedule::with([
            'kelas', 'schoolClass', 'employee', 'teacher',
            'subject', 'classroom', 'academicYear', 'semester',
        ])->find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $schedule]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $schedule = ClassSchedule::find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'kelas_id'     => 'nullable|uuid|exists:tbl_kelas,id',
            'class_id'     => 'nullable|uuid|exists:classes,id',
            'employee_id'  => 'nullable|uuid|exists:employees,id',
            'teacher_id'   => 'nullable|uuid|exists:teachers,id',
            'subject_id'   => 'sometimes|uuid|exists:subjects,id',
            'classroom_id' => 'nullable|uuid|exists:classrooms,id',
            'day_of_week'  => 'sometimes|integer|min:1|max:7',
            'time_start'   => 'sometimes|date_format:H:i',
            'time_end'     => 'sometimes|date_format:H:i',
            'week_type'    => 'nullable|string|in:all,odd,even',
            'is_active'    => 'nullable|boolean',
            'metadata'     => 'nullable|array',
        ]);

        $validated['updated_by'] = Auth::id();
        $schedule->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal berhasil diperbarui.',
            'data'    => $schedule->fresh(['kelas', 'employee', 'subject']),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $schedule = ClassSchedule::find($id);

        if (! $schedule) {
            return response()->json(['status' => 'error', 'message' => 'Jadwal tidak ditemukan.'], 404);
        }

        $schedule->update(['deleted_by' => Auth::id()]);
        $schedule->delete();

        return response()->json(['status' => 'success', 'message' => 'Jadwal berhasil dihapus.']);
    }
}
