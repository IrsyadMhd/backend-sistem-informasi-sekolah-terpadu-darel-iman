<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CodeSequence;
use App\Services\AccessScopeService;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CodeGeneratorController extends Controller
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly AccessScopeService $accessScope
    ) {}

    /**
     * Dapatkan preview nomor berikutnya tanpa reservasi / increment.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:60'],
            'unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'jenjang' => ['nullable', 'string', 'max:30'],
            'prefix' => ['nullable', 'string', 'max:30'],
        ]);

        $code = $this->codeGenerator->preview(
            $validated['entity_type'],
            $validated['unit_id'] ?? null,
            $validated['academic_year_id'] ?? null,
            $validated
        );

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => [
                'entity_type' => $validated['entity_type'],
                'preview_code' => $code,
            ],
        ]);
    }

    /**
     * Daftar konfigurasi format generator nomor.
     */
    public function configs(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $configs = CodeSequence::with(['educationUnit:id,name', 'academicYear:id,name'])
            ->orderBy('entity_type')
            ->get();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $configs,
        ]);
    }

    /**
     * Simpan / perbarui konfigurasi format generator nomor.
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:60'],
            'prefix' => ['nullable', 'string', 'max:30'],
            'separator' => ['nullable', 'string', 'max:5'],
            'padding_length' => ['required', 'integer', 'between:1,10'],
            'unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'period_key' => ['nullable', 'string', 'max:30'],
            'format_pattern' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);

        $periodKey = $validated['period_key'] ?? now()->format('Y');

        $config = CodeSequence::updateOrCreate(
            [
                'entity_type' => $validated['entity_type'],
                'unit_id' => $validated['unit_id'] ?? null,
                'period_key' => $periodKey,
            ],
            $validated
        );

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Konfigurasi generator kode berhasil disimpan.',
            'data' => $config,
        ]);
    }
}
