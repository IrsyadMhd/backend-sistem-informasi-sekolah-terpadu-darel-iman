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
        if (! Schema::hasTable('lms_aktivitas_belajar')) {
            Schema::create('lms_aktivitas_belajar', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('modul_ajar_id')->nullable()->comment('Relasi 1:N ke LmsModulAjar');
                $table->string('nama_aktivitas', 255);
                $table->string('jenis_aktivitas', 50)->default('Inti');
                $table->text('instruksi')->nullable();
                $table->integer('waktu')->default(15)->comment('Alokasi waktu dalam menit');
                $table->integer('urutan')->default(1);
                $table->string('status', 30)->default('aktif');

                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['modul_ajar_id'], 'lms_aktivitas_modul_idx');
                $table->index(['jenis_aktivitas'], 'lms_aktivitas_jenis_idx');
                $table->index(['status'], 'lms_aktivitas_status_idx');

                $table->foreign('modul_ajar_id')
                    ->references('id')
                    ->on('lms_modul_ajar')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_aktivitas_belajar');
    }
};
