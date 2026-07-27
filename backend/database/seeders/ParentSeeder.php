<?php

namespace Database\Seeders;

use App\Models\ParentModel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        $parentsData = [
            [
                'full_name' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@parent.local',
                'phone' => '081200010001',
                'occupation' => 'Wiraswasta',
                'address' => 'Jl. Melati No. 12, Padang',
            ],
            [
                'full_name' => 'Budi Santoso',
                'email' => 'budi.santoso@parent.local',
                'phone' => '081200010002',
                'occupation' => 'PNS',
                'address' => 'Jl. Mawar No. 5, Padang',
            ],
            [
                'full_name' => 'Hendra Kurniawan',
                'email' => 'hendra.kurniawan@parent.local',
                'phone' => '081200010003',
                'occupation' => 'Karyawan Swasta',
                'address' => 'Jl. Kenanga No. 8, Padang',
            ],
        ];

        foreach ($parentsData as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'password' => 'Password123!',
                    'is_active' => true,
                ]
            );

            $user->assignRole('Orang Tua');

            DB::table('parents')->updateOrInsert(
                ['email' => $data['email']],
                [
                    'id' => DB::raw('gen_random_uuid()'),
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'occupation' => $data['occupation'],
                    'address' => $data['address'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
