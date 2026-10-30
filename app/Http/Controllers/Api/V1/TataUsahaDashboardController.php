<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TataUsahaDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TataUsahaDashboardController extends Controller
{
    protected TataUsahaDashboardService $service;

    public function __construct(TataUsahaDashboardService $service)
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

        $cacheKey = 'tata_usaha_dashboard_' . $user->id . '_' . md5(json_encode($request->all()));
        if (app()->runningUnitTests()) {
            $data = $this->service->getDashboardOverview($user, $request->all());
        } else {
            $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($user, $request) {
                return $this->service->getDashboardOverview($user, $request->all());
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Tata Usaha berhasil dimuat.',
            'data' => $data,
        ]);
    }

    public function kpiDetail(Request $request, string $type): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.',
            ], 401);
        }

        $data = $this->service->getKpiDetail($user, $type, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Detail KPI berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
