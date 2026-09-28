<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NavigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationModuleController extends Controller
{
    protected NavigationService $navigationService;

    public function __construct(NavigationService $navigationService)
    {
        $this->navigationService = $navigationService;
    }

    /**
     * Return dynamic navigation modules and items for the authenticated user
     * based entirely on database tables and Spatie permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $allPermissions = $user->getAllPermissions()->pluck('name')->all();
        $roles = $user->getRoleNames()->all();

        $platform = $request->query('platform', 'web');
        $modules = $this->navigationService->getModulesForUser($user, $platform);

        return response()->json([
            'success' => true,
            'message' => 'Data modul navigasi berhasil dimuat.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $allPermissions,
                ],
                'modules' => $modules,
            ],
        ]);
    }
}
