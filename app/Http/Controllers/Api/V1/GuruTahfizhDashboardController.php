<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GuruTahfizhDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuruTahfizhDashboardController extends Controller
{
    protected GuruTahfizhDashboardService $service;

    public function __construct(GuruTahfizhDashboardService $service)
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
            'message' => 'Dashboard Guru Tahfizh berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
