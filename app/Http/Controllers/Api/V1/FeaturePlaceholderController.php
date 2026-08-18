<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FeaturePlaceholderController extends Controller
{
    public function __invoke(string $feature): JsonResponse
    {
        return response()->json([
            'feature' => $feature,
            'status' => 'in_progress',
            'message' => 'Endpoint tersedia sebagai placeholder tahap awal.',
        ], 202);
    }
}
