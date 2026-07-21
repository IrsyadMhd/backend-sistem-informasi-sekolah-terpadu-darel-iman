<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserProfileResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name', 'school-erp-client')
        );

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserProfileResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    public function profile(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user());
    }
}
