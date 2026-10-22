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
        // 1. Tambahkan kolom supervisi pada tabel lms_materi jika belum ada
        Schema::table('lms_materi', function (Blueprint $table) {
            if (! Schema::hasColumn('lms_materi', 'status_supervisi')) {
                $table->string('status_supervisi', 30)->default('disetujui')
                    ->comment('draft, diajukan, perlu_revisi, disetujui');
            }
            if (! Schema::hasColumn('lms_materi', 'catatan_koreksi')) {
                $table->text('catatan_koreksi')->nullable()
                    ->comment('Catatan koreksi/arahan dari Kepala Sekolah atau Divisi Pendidikan');
            }
            if (! Schema::hasColumn('lms_materi', 'supervisor_id')) {
                $table->uuid('supervisor_id')->nullable();
                $table->foreign('supervisor_id')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('lms_materi', 'supervisor_role')) {
                $table->string('supervisor_role', 100)->nullable();
            }
            if (! Schema::hasColumn('lms_materi', 'tanggal_supervisi')) {
                $table->timestampTz('tanggal_supervisi')->nullable();
            }

            $table->index(['status_supervisi'], 'lms_materi_status_supervisi_idx');
        });

        // 2. Buat tabel riwayat dialog & log supervisi lms_materi_supervisi_logs
        if (! Schema::hasTable('lms_materi_supervisi_logs')) {
            Schema::create('lms_materi_supervisi_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('materi_id');
                $table->uuid('user_id')->nullable();
                $table->string('user_name', 150)->nullable();
                $table->string('user_role', 100)->nullable();
                $table->string('status_sebelumnya', 30)->nullable();
                $table->string('status_baru', 30);
                $table->text('catatan');
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('materi_id')->references('id')->on('lms_materi')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['materi_id', 'created_at'], 'lms_materi_sup_logs_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_materi_supervisi_logs');

        Schema::table('lms_materi', function (Blueprint $table) {
            $table->dropIndex('lms_materi_status_supervisi_idx');
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn([
                'status_supervisi',
                'catatan_koreksi',
                'supervisor_id',
                'supervisor_role',
                'tanggal_supervisi',
            ]);
        });
    }
};
