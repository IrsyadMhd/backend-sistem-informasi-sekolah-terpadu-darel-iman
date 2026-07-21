<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreStudentRequest;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class StudentController extends Controller
{
    public function __construct(private readonly StudentRepositoryInterface $studentRepository)
    {
    }

    public function index(IndexRequest $request): JsonResponse
    {
        $data = $this->studentRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15)
        );

        return response()->json($data);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = Student::query()->create($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data siswa berhasil disimpan.',
            'data' => $student,
        ], 201);
    }

    public function show(string $student): JsonResponse
    {
        return response()->json(Student::query()->findOrFail($student));
    }

    public function update(StoreStudentRequest $request, string $student): JsonResponse
    {
        $model = Student::query()->findOrFail($student);
        $model->update($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(string $student): JsonResponse
    {
        Student::query()->findOrFail($student)->delete();

        return response()->json([
            'message' => 'Data siswa berhasil dihapus.',
        ]);
    }

    private function mappedPayload(array $validated): array
    {
        return [
            'class_id' => $validated['class_id'] ?? null,
            'nis' => $validated['nis'],
            'full_name' => $validated['full_name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'] ?? null,
            'birth_place' => $validated['birth_place'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }
}
