<?php

namespace Database\Seeders;

use App\Models\StudentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentStatusSeeder extends Seeder
{
    /**
     * Seed Master Status Siswa resmi (Aktif, Lulus, Mutasi, Berhenti, Cuti, Nonaktif).
     */
    public function run(): void
    {
        $statuses = [
            [
                'code' => 'aktif',
                'name' => 'Aktif',
                'badge_color' => 'emerald',
                'order' => 1,
                'is_active' => true,
                'description' => 'Siswa aktif terdaftar dan mengikuti kegiatan belajar mengajar.',
            ],
            [
                'code' => 'lulus',
                'name' => 'Lulus',
                'badge_color' => 'indigo',
                'order' => 2,
                'is_active' => true,
                'description' => 'Siswa telah menyelesaikan seluruh jenjang pendidikan dan dinyatakan lulus.',
            ],
            [
                'code' => 'mutasi',
                'name' => 'Mutasi',
                'badge_color' => 'amber',
                'order' => 3,
                'is_active' => true,
                'description' => 'Siswa pindah unit sekolah internal atau mutasi ke luar lembaga.',
            ],
            [
                'code' => 'berhenti',
                'name' => 'Berhenti',
                'badge_color' => 'rose',
                'order' => 4,
                'is_active' => true,
                'description' => 'Siswa mengundurkan diri atau diberhentikan secara resmi.',
            ],
            [
                'code' => 'cuti',
                'name' => 'Cuti',
                'badge_color' => 'sky',
                'order' => 5,
                'is_active' => true,
                'description' => 'Siswa mengambil izin cuti akademik sementara.',
            ],
            [
                'code' => 'nonaktif',
                'name' => 'Nonaktif',
                'badge_color' => 'slate',
                'order' => 6,
                'is_active' => true,
                'description' => 'Siswa dalam status non-aktif operasional.',
            ],
        ];

        foreach ($statuses as $st) {
            DB::table('student_statuses')->updateOrInsert(
                ['code' => $st['code']],
                array_merge($st, [
                    'updated_at' => now(),
                ])
            );
        }

        echo "Master Status Siswa (6 status) berhasil di-seed.\n";
    }
}

