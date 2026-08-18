<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachersData = [
            [
                'employee_number' => 'GURU-001',
                'full_name' => 'Ust. Ahmad Dahlan, S.Pd.',
                'email' => 'ahmad.dahlan@school-erp.local',
                'phone' => '081234567001',
            ],
            [
                'employee_number' => 'GURU-002',
                'full_name' => 'Ust. Hidayatullah, M.Pd.',
                'email' => 'hidayatullah@school-erp.local',
                'phone' => '081234567002',
            ],
            [
                'employee_number' => 'GURU-003',
                'full_name' => 'Ustadzah Fatimah Azzahra, S.Ag.',
                'email' => 'fatimah.azzahra@school-erp.local',
                'phone' => '081234567003',
            ],
            [
                'employee_number' => 'GURU-004',
                'full_name' => 'Ust. Rahmat Hidayat, Lc.',
                'email' => 'rahmat.hidayat@school-erp.local',
                'phone' => '081234567004',
            ],
        ];

        foreach ($teachersData as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'password' => 'Password123!',
                    'is_active' => true,
                ]
            );

            $user->assignRole('Guru');

            Teacher::query()->updateOrCreate(
                ['employee_number' => $data['employee_number']],
                [
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ]
            );
        }
    }
}
