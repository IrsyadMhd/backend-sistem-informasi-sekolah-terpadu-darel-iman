<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DivisiPendidikanDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DivisiPendidikanDashboardController extends Controller
{
    protected DivisiPendidikanDashboardService $service;

    public function __construct(DivisiPendidikanDashboardService $service)
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
            'message' => 'Dashboard Divisi Pendidikan berhasil dimuat.',
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
            'message' => 'Detail KPI Divisi Pendidikan berhasil dimuat.',
            'data' => $data,
        ]);
    }
}

