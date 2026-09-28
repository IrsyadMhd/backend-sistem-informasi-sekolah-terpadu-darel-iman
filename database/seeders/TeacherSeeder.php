<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        // DEF-SEED-001 FIX (2026-09-14):
        // Domain corrected from @school-erp.local → @dareliman.sch.id.
        // Each teacher is linked to the matching Employee record by NIY
        // (deterministic 1:1 mapping confirmed in Phase A audit).
        // All 4 employees are at unit SDIT-01.
        $teachersData = [
            [
                'employee_number' => 'GURU-001',
                'full_name'       => 'Ust. Ahmad Dahlan, S.Pd.',
                'email'           => 'ahmad.dahlan@dareliman.sch.id',
                'phone'           => '081234567001',
                'niy'             => 'NIY-550625',
            ],
            [
                'employee_number' => 'GURU-002',
                'full_name'       => 'Ust. Hidayatullah, M.Pd.',
                'email'           => 'hidayatullah@dareliman.sch.id',
                'phone'           => '081234567002',
                'niy'             => 'NIY-811298',
            ],
            [
                'employee_number' => 'GURU-003',
                'full_name'       => 'Ustadzah Fatimah Azzahra, S.Ag.',
                'email'           => 'fatimah.azzahra@dareliman.sch.id',
                'phone'           => '081234567003',
                'niy'             => 'NIY-245689',
            ],
            [
                'employee_number' => 'GURU-004',
                'full_name'       => 'Ust. Rahmat Hidayat, Lc.',
                'email'           => 'rahmat.hidayat@dareliman.sch.id',
                'phone'           => '081234567004',
                'niy'             => 'NIY-891622',
            ],
        ];

        foreach ($teachersData as $data) {
            // 1. Resolve the existing Employee record by NIY (stable, never changes).
            $employee = Employee::where('niy', $data['niy'])->first();

            // 2. Resolve User: prefer the user already linked via employee.user_id.
            //    If that user still has the old @school-erp.local email, migrate it.
            $user = null;
            if ($employee && $employee->user_id) {
                $user = User::find($employee->user_id);
            }

            if (! $user) {
                // Fallback: find user by new canonical email or create fresh.
                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name'      => $data['full_name'],
                        'password'  => 'Password123!',
                        'is_active' => true,
                    ]
                );
            } elseif (str_ends_with($user->email, '@school-erp.local')) {
                // Migrate the user email to canonical domain.
                $user->forceFill(['email' => $data['email']])->save();
            }

            $user->syncRoles(['Guru']);

            // 3. Update the employee record: sync email domain + user_id.
            if ($employee) {
                if (str_ends_with($employee->email, '@school-erp.local')) {
                    $employee->forceFill([
                        'email'   => $data['email'],
                        'user_id' => $user->id,
                    ])->save();
                }
            }

            // 4. Upsert the Teacher record, linking employee_id + user_id.
            Teacher::query()->updateOrCreate(
                ['employee_number' => $data['employee_number']],
                [
                    'employee_id' => $employee?->id,
                    'user_id'     => $user->id,
                    'full_name'   => $data['full_name'],
                    'email'       => $data['email'],
                    'phone'       => $data['phone'],
                ]
            );
        }
    }
}
