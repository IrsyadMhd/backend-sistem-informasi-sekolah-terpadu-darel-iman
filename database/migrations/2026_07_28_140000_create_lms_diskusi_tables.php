<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('lms_diskusi')) {
            Schema::create('lms_diskusi', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('modul_ajar_id')->nullable()->comment('Relasi 1:N ke LmsModulAjar');
                $table->string('judul', 255);
                $table->text('deskripsi')->nullable();
                $table->string('kategori', 50)->default('Umum');
                $table->dateTime('tanggal_mulai')->nullable();
                $table->dateTime('tanggal_tutup')->nullable();
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_closed')->default(false);
                $table->string('status', 30)->default('aktif');

                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['modul_ajar_id'], 'lms_diskusi_modul_idx');
                $table->index(['kategori'], 'lms_diskusi_kategori_idx');
                $table->index(['status'], 'lms_diskusi_status_idx');

                $table->foreign('modul_ajar_id')
                    ->references('id')
                    ->on('lms_modul_ajar')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('lms_diskusi_komentar')) {
            Schema::create('lms_diskusi_komentar', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('diskusi_id');
                $table->uuid('parent_id')->nullable()->comment('Relasi balasan komentar');
                $table->uuid('user_id')->nullable();
                $table->string('peran_pengirim', 30)->default('Guru')->comment('Guru, Siswa, Admin');
                $table->text('konten');
                $table->boolean('is_solution')->default(false);

                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['diskusi_id'], 'lms_komentar_diskusi_idx');
                $table->index(['parent_id'], 'lms_komentar_parent_idx');
                $table->index(['user_id'], 'lms_komentar_user_idx');

                $table->foreign('diskusi_id')
                    ->references('id')
                    ->on('lms_diskusi')
                    ->cascadeOnDelete();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });

            Schema::table('lms_diskusi_komentar', function (Blueprint $table) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('lms_diskusi_komentar')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_diskusi_komentar');
        Schema::dropIfExists('lms_diskusi');
    }
};
