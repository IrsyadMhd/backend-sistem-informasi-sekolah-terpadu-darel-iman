<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WakaKesiswaanDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WakaKesiswaanDashboardController extends Controller
{
    protected WakaKesiswaanDashboardService $service;

    public function __construct(WakaKesiswaanDashboardService $service)
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
            'message' => 'Dashboard Waka Kesiswaan berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
