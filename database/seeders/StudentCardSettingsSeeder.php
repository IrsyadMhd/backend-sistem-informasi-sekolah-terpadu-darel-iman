<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentCardSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $existing = DB::table('student_card_settings')->where('is_default', true)->first();

        if (!$existing) {
            DB::table('student_card_settings')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => null,
                'education_unit_id' => null,
                'orientation' => 'horizontal',
                'template_color' => 'green',
                'show_photo' => true,
                'show_logo' => true,
                'show_qrcode' => true,
                'show_nis' => true,
                'show_nisn' => true,
                'show_class' => true,
                'show_rombel' => true,
                'show_unit' => true,
                'show_academic_year' => true,
                'show_motto' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
