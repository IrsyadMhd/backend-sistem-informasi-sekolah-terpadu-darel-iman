<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\QrCredential;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class QrCredentialSeeder extends Seeder
{
    /**
     * Seed stable demo QR credentials for Employee QR Login and Student QR Attendance.
     */
    public function run(): void
    {
        // 1. Employee QR Login Demo Credential
        $guruUser = User::where('email', 'guru@school-erp.local')->first();
        $employee = Employee::where('niy', 'TEST-NIY-17')->first()
            ?? ($guruUser ? Employee::where('user_id', $guruUser->id)->first() : null);

        if ($guruUser && $employee) {
            $employeeRawToken = 'empqr-demo-guru-0017';
            $employeeHash = hash('sha256', $employeeRawToken);

            QrCredential::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'card_type' => 'employee_card',
                ],
                [
                    'user_id' => $guruUser->id,
                    'token_hash' => $employeeHash,
                    'card_version' => 'v1',
                    'status' => 'active',
                    'issued_at' => now(),
                    'metadata' => [
                        'fixture' => 'demo_qr_login',
                        'raw_token' => $employeeRawToken,
                    ],
                ]
            );
        }

        // 2. Student QR Attendance Demo Credentials
        $student1 = Student::where('nis', 'TEST-NIS-023')->first();
        if ($student1) {
            $stuRawToken1 = 'stuqr:v1:demo-student-023';
            $stuHash1 = hash('sha256', $stuRawToken1);

            QrCredential::updateOrCreate(
                [
                    'student_id' => $student1->id,
                    'card_type' => 'student_card',
                ],
                [
                    'user_id' => $student1->user_id,
                    'token_hash' => $stuHash1,
                    'card_version' => 'v1',
                    'status' => 'active',
                    'issued_at' => now(),
                    'metadata' => [
                        'fixture' => 'demo_student_qr',
                        'raw_token' => $stuRawToken1,
                    ],
                ]
            );
        }

        $student2 = Student::where('nis', 'TEST-NIS-025')->first();
        if ($student2) {
            $stuRawToken2 = 'stuqr:v1:demo-student-025';
            $stuHash2 = hash('sha256', $stuRawToken2);

            QrCredential::updateOrCreate(
                [
                    'student_id' => $student2->id,
                    'card_type' => 'student_card',
                ],
                [
                    'user_id' => $student2->user_id,
                    'token_hash' => $stuHash2,
                    'card_version' => 'v1',
                    'status' => 'active',
                    'issued_at' => now(),
                    'metadata' => [
                        'fixture' => 'demo_student_qr',
                        'raw_token' => $stuRawToken2,
                    ],
                ]
            );
        }
    }
}
