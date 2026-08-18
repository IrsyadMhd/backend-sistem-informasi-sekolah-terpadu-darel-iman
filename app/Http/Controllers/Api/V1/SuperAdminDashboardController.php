<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    protected SuperAdminDashboardService $service;

    public function __construct(SuperAdminDashboardService $service)
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

        $filters = [
            'academic_year_id' => $request->query('academic_year_id'),
            'semester_id' => $request->query('semester_id'),
        ];

        $data = $this->service->getDashboardOverview($filters);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Super Admin berhasil dimuat.',
            'data' => $data,
        ]);
    }
}
