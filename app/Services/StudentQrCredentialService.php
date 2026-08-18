<?php

namespace App\Services;

use App\Models\QrCredential;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentQrCredentialService
{
    private const TOKEN_PREFIX = 'stuqr:v1:';

    public function tokenFor(Student $student): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw ValidationException::withMessages(['qr_token' => 'Konfigurasi keamanan QR siswa belum tersedia.']);
        }

        return self::TOKEN_PREFIX.hash_hmac('sha256', (string) $student->getKey(), $key);
    }

    public function issue(Student $student): array
    {
        if (! $student->is_active) {
            throw ValidationException::withMessages(['student_id' => 'Kartu hanya dapat dibuat untuk siswa aktif.']);
        }

        $token = $this->tokenFor($student);
        $tokenHash = hash('sha256', $token);

        $credential = DB::transaction(function () use ($student, $tokenHash): QrCredential {
            // Serialise issuance per student so two portal/card requests do
            // not create two active credentials for the same stable token.
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);
            $active = QrCredential::query()
                ->where('student_id', $lockedStudent->id)
                ->where('card_type', 'student_card')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($active && ! $active->revoked_at && hash_equals((string) $active->token_hash, $tokenHash) && ! $active->expires_at?->isPast()) {
                return $active;
            }

            if ($active) {
                $active->update(['status' => 'revoked', 'revoked_at' => now()]);
            }

            return QrCredential::create([
                'user_id' => $lockedStudent->user_id,
                'student_id' => $lockedStudent->id,
                'card_type' => 'student_card',
                'token_hash' => $tokenHash,
                'card_version' => 'v1',
                'status' => 'active',
                'issued_at' => now(),
                'metadata' => ['token_scheme' => 'hmac_student_id_v1'],
            ]);
        });

        return ['credential' => $credential->fresh(), 'raw_token' => $token];
    }

    public function resolve(string $rawToken): ?Student
    {
        $token = trim($rawToken);
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        $credential = QrCredential::query()
            ->active()
            ->where('card_type', 'student_card')
            ->where('token_hash', hash('sha256', $token))
            ->with('student')
            ->first();

        if (! $credential || ! $credential->student?->is_active) {
            return null;
        }

        $credential->update(['last_used_at' => now()]);

        return $credential->student;
    }
}
