<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\QrCredential;
use App\Models\Student;
use App\Services\StudentQrCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QrCredentialController extends Controller
{
    public function __construct(private StudentQrCredentialService $studentQr)
    {
    }

    public function generateEmployeeQr(Request $request, string $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        // Revoke any existing active employee card QR
        QrCredential::where('employee_id', $employee->id)
            ->where('card_type', 'employee_card')
            ->where('status', 'active')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
            ]);

        // Generate random secure token (UUID token)
        $rawToken = Str::uuid()->toString();
        $tokenHash = hash('sha256', $rawToken);

        $qr = QrCredential::create([
            'user_id' => $employee->user_id,
            'employee_id' => $employee->id,
            'card_type' => 'employee_card',
            'token_hash' => $tokenHash,
            'card_version' => 'v1',
            'status' => 'active',
            'issued_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'QR Code ID Card Pegawai berhasil dibuat.',
            'data' => [
                'id' => $qr->id,
                'employee_id' => $employee->id,
                'raw_token' => $rawToken, // Provided once for QR image generation
                'token_hash' => $tokenHash,
                'status' => $qr->status,
                'issued_at' => $qr->issued_at,
            ],
        ]);
    }

    public function generateStudentQr(Request $request, string $studentId): JsonResponse
    {
        $student = Student::findOrFail($studentId);
        $issued = $this->studentQr->issue($student);
        $qr = $issued['credential'];
        $rawToken = $issued['raw_token'];

        return response()->json([
            'status' => 'success',
            'message' => 'QR Code Kartu Siswa berhasil dibuat.',
            'data' => [
                'id' => $qr->id,
                'student_id' => $student->id,
                'raw_token' => $rawToken,
                'status' => $qr->status,
                'issued_at' => $qr->issued_at,
            ],
        ]);
    }

    public function revokeQr(Request $request, string $id): JsonResponse
    {
        $qr = QrCredential::findOrFail($id);
        $qr->update([
            'status' => 'revoked',
            'revoked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'QR Code berhasil dicabut.',
        ]);
    }
}
