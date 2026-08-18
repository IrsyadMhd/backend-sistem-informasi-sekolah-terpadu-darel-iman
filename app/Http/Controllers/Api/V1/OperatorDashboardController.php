<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OperatorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatorDashboardController extends Controller
{
    protected OperatorDashboardService $service;

    public function __construct(OperatorDashboardService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }

        $data = $this->service->getDashboardOverview($user, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Operator Sekolah berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
