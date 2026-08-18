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
        if (! Schema::hasTable('lms_referensi')) {
            Schema::create('lms_referensi', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('modul_ajar_id')->nullable()->comment('Relasi 1:N ke LmsModulAjar');
                $table->string('judul', 255);
                $table->string('penulis', 255)->nullable();
                $table->string('penerbit', 255)->nullable();
                $table->integer('tahun')->nullable();
                $table->text('url')->nullable();
                $table->text('file')->nullable();
                $table->string('status', 30)->default('aktif');

                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['modul_ajar_id'], 'lms_referensi_modul_idx');
                $table->index(['status'], 'lms_referensi_status_idx');

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
        Schema::dropIfExists('lms_referensi');
    }
};
