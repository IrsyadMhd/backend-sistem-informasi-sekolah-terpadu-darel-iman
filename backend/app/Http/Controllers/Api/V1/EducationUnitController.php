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
        $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $data = EducationUnit::query()
            ->when($search !== '', function ($query) use ($search, $likeOp) {
                $query->where(function ($subQuery) use ($search, $likeOp) {
                    $subQuery
                        ->where('code', $likeOp, "%{$search}%")
                        ->orWhere('name', $likeOp, "%{$search}%")
                        ->orWhere('level', $likeOp, "%{$search}%")
                        ->orWhere('description', $likeOp, "%{$search}%")
                        ->orWhere('metadata->city', $likeOp, "%{$search}%")
                        ->orWhere('metadata->province', $likeOp, "%{$search}%")
                        ->orWhere('metadata->principal_name', $likeOp, "%{$search}%");
                });
            })
            ->when($level, function ($query) use ($level) {
                $query->where('level', $level);
            })
            ->when($city, function ($query) use ($city) {
                $query->where('metadata->city', $city);
            })
            ->when($province, function ($query) use ($province) {
                $query->where('metadata->province', $province);
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

    public function show(EducationUnit|string $education_unit): JsonResponse
    {
        $model = $education_unit instanceof EducationUnit ? $education_unit : EducationUnit::query()->findOrFail($education_unit);
        return response()->json($model);
    }

    public function update(UpdateEducationUnitRequest $request, EducationUnit|string $education_unit): JsonResponse
    {
        $model = $education_unit instanceof EducationUnit ? $education_unit : EducationUnit::query()->findOrFail($education_unit);
        $model->update($this->mappedPayload($request->validated(), $model->code));

        return response()->json([
            'message' => 'Data unit pendidikan berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(EducationUnit|string $education_unit): JsonResponse
    {
        $model = $education_unit instanceof EducationUnit ? $education_unit : EducationUnit::query()->findOrFail($education_unit);
        $model->delete();

        return response()->json([
            'message' => 'Data unit pendidikan berhasil dihapus.',
        ]);
    }

    private function mappedPayload(array $validated, ?string $existingCode = null): array
    {
        $code = $validated['code'] ?? null;
        $level = $validated['level'] ?? null;

        if (empty($code)) {
            if (!empty($existingCode)) {
                $code = $existingCode;
            } else {
                $rawPrefix = preg_replace('/[^A-Za-z0-9]/', '', $level ?? 'UP');
                $prefix = strtoupper(substr($rawPrefix ?: 'UP', 0, 10));
                $code = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
            }
        }

        $jenisUnitId = $validated['jenis_unit_id'] ?? null;
        if (empty($jenisUnitId) && !empty($level)) {
            $jenisUnitId = \App\Models\JenisUnitPendidikan::query()
                ->where('singkatan', $level)
                ->orWhere('kode_jenis', $level)
                ->orWhere('nama_jenis', $level)
                ->value('uuid');
        }
        if (empty($jenisUnitId)) {
            $jenisUnitId = \App\Models\JenisUnitPendidikan::query()->value('uuid');
        }

        $payload = [
            'code' => $code,
            'name' => $validated['name'],
            'level' => $level,
            'description' => $validated['description'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];

        if (!empty($jenisUnitId)) {
            $payload['jenis_unit_id'] = $jenisUnitId;
        }

        return $payload;
    }
}
