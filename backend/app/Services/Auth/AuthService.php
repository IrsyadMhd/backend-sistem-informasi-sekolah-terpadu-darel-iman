<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    public function login(string $email, string $password, string $deviceName = 'web-client'): array
    {
        $identifier = trim($email);

        $user = User::query()->where('email', $identifier)
            ->orWhere('email', strtolower($identifier))
            ->first();

        if (! $user) {
            $teacher = Teacher::query()->where('employee_number', $identifier)->first();
            if ($teacher && $teacher->user_id) {
                $user = User::query()->find($teacher->user_id);
            }
        }

        if (! $user) {
            $student = Student::query()->where('nis', $identifier)->orWhere('nisn', $identifier)->first();
            if ($student && $student->user_id) {
                $user = User::query()->find($student->user_id);
            }
        }

        if (! $user) {
            throw new UnauthorizedHttpException('Bearer', 'Email/NIP/NIS atau password tidak valid.');
        }

        if (! Hash::check($password, $user->password)) {
            throw new UnauthorizedHttpException('Bearer', 'Email/NIP/NIS atau password tidak valid.');
        }

        if (! $user->is_active) {
            throw new UnauthorizedHttpException('Bearer', 'Akun tidak aktif. Hubungi admin.');
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [$user, $token];
    }
}
