<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
        }

        // Master Jabatan (Positions)
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->jsonb('metadata')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();
        });

        // Master Pegawai (Employees)
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identitas
            $table->string('niy', 50)->unique();
            $table->string('nik', 32)->nullable()->index();
            $table->string('nama_lengkap');
            $table->string('nama_panggilan')->nullable();
            $table->string('gelar_depan', 30)->nullable();
            $table->string('gelar_belakang', 30)->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->default('L');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama', 30)->default('Islam');
            $table->text('foto')->nullable();

            // Kepegawaian
            $table->uuid('unit_id')->nullable();
            $table->uuid('jabatan_id')->nullable();
            $table->string('status_pegawai', 50)->default('Tetap'); // Tetap, Kontrak, Honorer, Magang
            $table->date('tanggal_masuk')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->string('status', 30)->default('Aktif'); // Aktif, Nonaktif, Cuti, Resign

            // Kontak
            $table->string('no_hp', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kode_pos', 10)->nullable();

            // Login & Role
            $table->uuid('user_id')->nullable()->unique();
            $table->unsignedBigInteger('role_id')->nullable();

            // Metadata Tambahan (Sertifikasi, Dokumen, Absensi, Riwayat Jabatan, dll)
            $table->jsonb('metadata')->nullable();

            $table->softDeletesTz();
            $table->timestampsTz();

            // Foreign Keys
            $table->foreign('unit_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('jabatan_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });

        // Penugasan Mengajar (Employee Teachings)
        Schema::create('employee_teachings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('employee_id');
            $table->uuid('classroom_id')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('academic_year_id')->nullable();
            $table->uuid('semester_id')->nullable();
            $table->boolean('aktif')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('id')->on('classrooms')->nullOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->nullOnDelete();
        });

        // Insert Default Positions
        DB::table('positions')->insert([
            ['id' => (string) Str::uuid(), 'code' => 'JAB-001', 'name' => 'Kepala Sekolah', 'description' => 'Pimpinan Utama Unit Sekolah', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-002', 'name' => 'Wakil Kepala Sekolah', 'description' => 'Wakil Pimpinan Sekolah', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-003', 'name' => 'Guru Kelas', 'description' => 'Tenaga Pendidik Wali Kelas', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-004', 'name' => 'Guru Mata Pelajaran', 'description' => 'Tenaga Pendidik Mapel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-005', 'name' => 'Tata Usaha (TU)', 'description' => 'Staf Administrasi Sekolah', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-006', 'name' => 'Operator Sekolah', 'description' => 'Pengelola Data & Sistem IT', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-007', 'name' => 'Divisi Pendidikan', 'description' => 'Pengawas & Penjamin Mutu Pendidikan Yayasan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'code' => 'JAB-008', 'name' => 'Ketua Yayasan', 'description' => 'Pimpinan Tertinggi Yayasan Dar El-Iman', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_teachings');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
    }
};
