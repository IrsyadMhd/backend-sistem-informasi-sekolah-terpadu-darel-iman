<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreEducationUnitRequest;
use App\Http\Requests\V1\UpdateEducationUnitRequest;
use App\Models\EducationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class EducationUnitController extends Controller
{
    public function index(IndexRequest $request): JsonResponse
    {
        $search = (string) $request->validated('search', '');
        $perPage = (int) $request->validated('per_page', 15);
        $level = $request->query('level');
        $city = $request->query('city');
        $province = $request->query('province');
        $status = $request->query('status');

        $data = EducationUnit::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere('level', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereRaw("metadata->>'city' ilike ?", ["%{$search}%"])
                        ->orWhereRaw("metadata->>'province' ilike ?", ["%{$search}%"])
                        ->orWhereRaw("metadata->>'principal_name' ilike ?", ["%{$search}%"]);
                });
            })
            ->when($level, function ($query) use ($level) {
                $query->where('level', $level);
            })
            ->when($city, function ($query) use ($city) {
                $query->whereRaw("metadata->>'city' = ?", [$city]);
            })
            ->when($province, function ($query) use ($province) {
                $query->whereRaw("metadata->>'province' = ?", [$province]);
            })
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                if ($status === 'aktif' || $status === '1' || $status === 'true') {
                    $query->where('is_active', true);
                } elseif ($status === 'nonaktif' || $status === '0' || $status === 'false') {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json($data);
    }

    public function store(StoreEducationUnitRequest $request): JsonResponse
    {
        $educationUnit = EducationUnit::query()->create($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data unit pendidikan berhasil disimpan.',
            'data' => $educationUnit,
        ], 201);
    }

    public function show(string $education_unit): JsonResponse
    {
        return response()->json(EducationUnit::query()->findOrFail($education_unit));
    }

    public function update(UpdateEducationUnitRequest $request, string $education_unit): JsonResponse
    {
        $model = EducationUnit::query()->findOrFail($education_unit);
        $model->update($this->mappedPayload($request->validated(), $model->code));

        return response()->json([
            'message' => 'Data unit pendidikan berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(string $education_unit): JsonResponse
    {
        EducationUnit::query()->findOrFail($education_unit)->delete();

        return response()->json([
            'message' => 'Data unit pendidikan berhasil dihapus.',
        ]);
    }

    private function mappedPayload(array $validated, ?string $existingCode = null): array
    {
        $code = $validated['code'] ?? null;
        if (empty($code)) {
            if (!empty($existingCode)) {
                $code = $existingCode;
            } else {
                $prefix = strtoupper(str_replace(' ', '', $validated['level'] ?? 'UP'));
                $code = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
            }
        }

        return [
            'code' => $code,
            'name' => $validated['name'],
            'level' => $validated['level'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }
}
