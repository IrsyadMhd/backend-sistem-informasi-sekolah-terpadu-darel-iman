<?php

namespace Database\Seeders;

use App\Models\ParentModel;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        $parentsData = [
            [
                'full_name' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@parent.local',
                'phone' => '081200010001',
                'nik' => '1201010101010001',
                'father_nik' => '1201010101010001',
                'mother_nik' => '1201010101010002',
                'occupation' => 'Wiraswasta',
                'address' => 'Jl. Melati No. 12, Padang',
            ],
            [
                'full_name' => 'Budi Santoso',
                'email' => 'budi.santoso@parent.local',
                'phone' => '081200010002',
                'nik' => '1201010101010003',
                'father_nik' => '1201010101010003',
                'mother_nik' => '1201010101010004',
                'occupation' => 'PNS',
                'address' => 'Jl. Mawar No. 5, Padang',
            ],
            [
                'full_name' => 'Hendra Kurniawan',
                'email' => 'hendra.kurniawan@parent.local',
                'phone' => '081200010003',
                'nik' => '1201010101010005',
                'father_nik' => '1201010101010005',
                'mother_nik' => '1201010101010006',
                'occupation' => 'Karyawan Swasta',
                'address' => 'Jl. Kenanga No. 8, Padang',
            ],
        ];

        foreach ($parentsData as $data) {
            $phoneNormalized = PhoneNormalizer::normalize($data['phone']);

            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'password' => 'Password123!',
                    'phone' => $phoneNormalized,
                    'is_active' => true,
                ]
            );

            $user->syncRoles(['Orang Tua']);

            $user->forceFill([
                'name' => $data['full_name'],
                'phone' => $phoneNormalized,
                'is_active' => true,
            ])->save();

            ParentModel::withTrashed()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'user_id' => $user->id,
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'nik' => $data['nik'],
                    'father_nik' => $data['father_nik'],
                    'mother_nik' => $data['mother_nik'],
                    'occupation' => $data['occupation'],
                    'address' => $data['address'],
                ]
            );
        }
    }
}
