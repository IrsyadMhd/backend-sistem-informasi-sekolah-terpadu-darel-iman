<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function login(string $email, string $password, string $deviceName = 'web-client'): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Email atau password tidak valid.');
        }

        if (! $user->is_active) {
            throw new UnauthorizedHttpException('Bearer', 'Akun tidak aktif. Hubungi admin.');
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [$user, $token];
    }
}
