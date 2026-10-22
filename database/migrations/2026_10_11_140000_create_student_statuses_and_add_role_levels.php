<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create student_statuses table if not exists
        if (! Schema::hasTable('student_statuses')) {
            Schema::create('student_statuses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 50)->unique();
                $table->string('name', 100);
                $table->string('badge_color', 50)->default('emerald');
                $table->integer('order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            // Seed initial official student statuses
            $statuses = [
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'aktif',
                    'name' => 'Aktif',
                    'badge_color' => 'emerald',
                    'order' => 1,
                    'is_active' => true,
                    'description' => 'Siswa aktif terdaftar dan mengikuti kegiatan belajar mengajar.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'lulus',
                    'name' => 'Lulus',
                    'badge_color' => 'indigo',
                    'order' => 2,
                    'is_active' => true,
                    'description' => 'Siswa telah menyelesaikan seluruh jenjang pendidikan dan dinyatakan lulus.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'mutasi',
                    'name' => 'Mutasi',
                    'badge_color' => 'amber',
                    'order' => 3,
                    'is_active' => true,
                    'description' => 'Siswa pindah unit sekolah internal atau mutasi ke luar lembaga.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'berhenti',
                    'name' => 'Berhenti',
                    'badge_color' => 'rose',
                    'order' => 4,
                    'is_active' => true,
                    'description' => 'Siswa mengundurkan diri atau diberhentikan secara resmi.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'cuti',
                    'name' => 'Cuti',
                    'badge_color' => 'sky',
                    'order' => 5,
                    'is_active' => true,
                    'description' => 'Siswa mengambil izin cuti akademik sementara.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::uuid(),
                    'code' => 'nonaktif',
                    'name' => 'Nonaktif',
                    'badge_color' => 'slate',
                    'order' => 6,
                    'is_active' => true,
                    'description' => 'Siswa dalam status non-aktif operasional.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            DB::table('student_statuses')->insert($statuses);
        }

        // 2. Add level column to roles table if not exists
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'level')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->integer('level')->default(99)->after('name')->index();
            });
        }

        // 3. Update level for all canonical roles
        $levelMapping = [
            1 => ['Super Admin'],
            2 => ['Admin'],
            3 => ['Operator', 'Tata Usaha'],
            4 => ['Kepala Sekolah'],
            5 => [
                'Ketua Yayasan',
                'Sekretaris Yayasan',
                'Bendahara Yayasan',
                'Pengurus Yayasan',
                'Wakil Kepala Sekolah',
                'Waka Kurikulum',
                'Waka Kesiswaan',
                'Divisi Pendidikan',
                'Kepala Bidang Pendidikan',
                'Divisi Kurikulum',
                'Divisi Kesiswaan',
                'Divisi Bahasa',
                'Divisi Program Khusus',
                'Wakil Humas dan Program Khusus',
            ],
            6 => [
                'Guru',
                'Guru Mata Pelajaran',
                'Wali Kelas',
                'Guru Tahfizh',
                'Guru BK',
                'Guru PAI',
                'Pembimbing',
            ],
            7 => ['Musyrif', 'Musyrifah'],
            8 => ['Orang Tua'],
            9 => ['Siswa'],
            10 => ['Alumni'],
        ];

        foreach ($levelMapping as $lvl => $roleNames) {
            DB::table('roles')->whereIn('name', $roleNames)->update(['level' => $lvl]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_statuses');

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'level')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        }
    }
};
