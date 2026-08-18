<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentCardSetting;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentCardSettingController extends Controller
{
    public function __construct(private readonly AccessScopeService $accessScope) {}

    public function show(Request $request): JsonResponse
    {
        $unitId = $request->query('education_unit_id');
        $setting = StudentCardSetting::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($query) => $unitId
                ? $query->where('education_unit_id', $unitId)
                : $query->whereNull('education_unit_id'))
            ->latest('updated_at')
            ->first();

        return response()->json(['data' => $setting]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'education_unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'orientation' => ['required', Rule::in(['horizontal', 'vertical'])],
            'template_color' => ['required', Rule::in(['green', 'blue', 'purple', 'orange', 'teal', 'navy'])],
            'show_photo' => ['required', 'boolean'],
            'show_logo' => ['required', 'boolean'],
            'show_qrcode' => ['required', 'boolean'],
            'show_nis' => ['required', 'boolean'],
            'show_nisn' => ['required', 'boolean'],
            'show_class' => ['required', 'boolean'],
            'show_rombel' => ['required', 'boolean'],
            'show_unit' => ['required', 'boolean'],
            'show_academic_year' => ['required', 'boolean'],
            'show_motto' => ['required', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()->id;
        if (! empty($data['education_unit_id'])) {
            $this->accessScope->assertEducationUnitAccess($request->user(), $data['education_unit_id']);
        }

        if ($data['is_default'] ?? false) {
            StudentCardSetting::query()
                ->where('user_id', $userId)
                ->where('education_unit_id', $data['education_unit_id'] ?? null)
                ->update(['is_default' => false, 'updated_by' => $userId]);
        }
        $setting = StudentCardSetting::query()->updateOrCreate([
            'user_id' => $userId,
            'education_unit_id' => $data['education_unit_id'] ?? null,
        ], [
            ...$data,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return response()->json([
            'message' => 'Pilihan desain kartu siswa berhasil disimpan.',
            'data' => $setting->fresh(),
        ]);
    }
}
