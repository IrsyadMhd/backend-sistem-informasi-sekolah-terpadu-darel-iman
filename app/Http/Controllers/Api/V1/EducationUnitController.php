<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreEducationUnitRequest;
use App\Http\Requests\V1\UpdateEducationUnitRequest;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\JenisUnitPendidikan;
use App\Models\Student;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EducationUnitController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    public function index(IndexRequest $request): JsonResponse
    {
        $search = (string) $request->validated('search', '');
        $perPage = (int) $request->validated('per_page', 15);
        $level = $request->query('level');
        $city = $request->query('city');
        $province = $request->query('province');
        $status = $request->query('status');
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $scopedUnits = $this->accessScope->accessibleEducationUnits($request->user());
        $unitIds = (clone $scopedUnits)->pluck('education_units.id');

        $data = (clone $scopedUnits)
            ->withCount([
                'students as total_siswa',
                'employees as total_guru' => fn ($query) => $this->teacherQuery($query),
            ])
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

        $employees = Employee::query()->whereIn('unit_id', $unitIds);
        $this->teacherQuery($employees);

        $metadataOptions = (clone $scopedUnits)
            ->get(['level', 'metadata'])
            ->reduce(function (array $options, EducationUnit $unit) {
                if ($unit->level) {
                    $options['levels'][] = $unit->level;
                }
                if ($unit->metadata['city'] ?? null) {
                    $options['cities'][] = $unit->metadata['city'];
                }
                if ($unit->metadata['province'] ?? null) {
                    $options['provinces'][] = $unit->metadata['province'];
                }

                return $options;
            }, [
                'levels' => JenisUnitPendidikan::query()
                    ->where('status', true)
                    ->pluck('singkatan')
                    ->filter()
                    ->values()
                    ->all(),
                'cities' => [],
                'provinces' => [],
            ]);

        foreach ($metadataOptions as $key => $values) {
            $metadataOptions[$key] = collect($values)->unique()->sort()->values()->all();
        }

        return response()->json(array_merge($data->toArray(), [
            'statistics' => [
                'total_unit' => $unitIds->count(),
                'total_siswa' => Student::query()->whereIn('unit_id', $unitIds)->count(),
                'total_tenaga_pendidik' => $employees->count(),
                'total_unit_aktif' => (clone $scopedUnits)->where('is_active', true)->count(),
            ],
            'filter_options' => $metadataOptions,
        ]));
    }

    public function store(StoreEducationUnitRequest $request): JsonResponse
    {
        $educationUnit = EducationUnit::query()->create($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data unit pendidikan berhasil disimpan.',
            'data' => $educationUnit,
        ], 201);
    }

    public function show(Request $request, EducationUnit|string $education_unit): JsonResponse
    {
        $model = $this->scopedUnit($request->user(), $education_unit);

        return response()->json($model);
    }

    public function update(UpdateEducationUnitRequest $request, EducationUnit|string $education_unit): JsonResponse
    {
        $model = $this->scopedUnit($request->user(), $education_unit);
        $model->update($this->mappedPayload($request->validated(), $model->code));

        return response()->json([
            'message' => 'Data unit pendidikan berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(Request $request, EducationUnit|string $education_unit): JsonResponse
    {
        $model = $this->scopedUnit($request->user(), $education_unit);
        $model->delete();

        return response()->json([
            'message' => 'Data unit pendidikan berhasil dihapus.',
        ]);
    }

    private function scopedUnit($user, EducationUnit|string $educationUnit): EducationUnit
    {
        $id = $educationUnit instanceof EducationUnit ? $educationUnit->getKey() : $educationUnit;

        return $this->accessScope
            ->accessibleEducationUnits($user)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function mappedPayload(array $validated, ?string $existingCode = null): array
    {
        $code = $validated['code'] ?? null;
        $level = $validated['level'] ?? null;

        if (empty($code)) {
            if (! empty($existingCode)) {
                $code = $existingCode;
            } else {
                $rawPrefix = preg_replace('/[^A-Za-z0-9]/', '', $level ?? 'UP');
                $prefix = strtoupper(substr($rawPrefix ?: 'UP', 0, 10));
                $code = $prefix.'-'.strtoupper(substr(md5(uniqid()), 0, 5));
            }
        }

        $jenisUnitId = $validated['jenis_unit_id'] ?? null;
        if (empty($jenisUnitId) && ! empty($level)) {
            $jenisUnitId = JenisUnitPendidikan::query()
                ->where('singkatan', $level)
                ->orWhere('kode_jenis', $level)
                ->orWhere('nama_jenis', $level)
                ->value('uuid');
        }
        if (empty($jenisUnitId)) {
            $jenisUnitId = JenisUnitPendidikan::query()->value('uuid');
        }

        $payload = [
            'code' => $code,
            'name' => $validated['name'],
            'level' => $level,
            'description' => $validated['description'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];

        if (! empty($jenisUnitId)) {
            $payload['jenis_unit_id'] = $jenisUnitId;
        }

        return $payload;
    }

    /**
     * Identifikasi guru kanonis: bridge Teacher, penugasan mengajar, atau jabatan
     * pendidik yang sudah direkonsiliasi. Tidak memakai status_pegawai sebagai tebakan.
     */
    private function teacherQuery($query)
    {
        return $query->where(function ($teacher) {
            $teacher
                ->whereHas('teacherBridge')
                ->orWhereHas('teachings', fn ($teaching) => $teaching->where('aktif', true))
                ->orWhereHas('position', function ($position) {
                    $position
                        ->whereIn('level_jabatan', [8, 9])
                        ->orWhere(function ($name) {
                            $likeOp = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                            $name->where('name', $likeOp, '%guru%')
                                ->orWhere('name', $likeOp, '%pendidik%');
                        });
                });
        });
    }
}
