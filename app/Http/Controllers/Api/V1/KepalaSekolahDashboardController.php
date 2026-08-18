<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\KepalaSekolahDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KepalaSekolahDashboardController extends Controller
{
    protected KepalaSekolahDashboardService $service;

    public function __construct(KepalaSekolahDashboardService $service)
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

        $data = $this->service->getDashboardData($user, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Kepala Sekolah berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
