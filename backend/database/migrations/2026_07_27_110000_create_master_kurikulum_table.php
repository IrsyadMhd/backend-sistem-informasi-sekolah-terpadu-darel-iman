<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');

        Schema::create('master_kurikulum', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('kode_kurikulum', 30)->unique();
            $table->string('nama_kurikulum', 150);
            $table->enum('jenis_kurikulum', ['Nasional', 'Merdeka', 'SIT', 'Lokal', 'Pesantren', 'Lainnya'])->default('SIT');
            $table->uuid('unit_pendidikan_id');
            $table->string('jenjang', 20);
            $table->uuid('tahun_ajaran_id');
            $table->uuid('semester_id')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('status')->default(true);
            $table->text('deskripsi')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['unit_pendidikan_id']);
            $table->index(['tahun_ajaran_id']);
            $table->index(['semester_id']);
            $table->index(['status']);
            $table->index(['jenjang']);
            $table->index(['jenis_kurikulum']);

            $table->foreign('unit_pendidikan_id')->references('id')->on('education_units')->cascadeOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_kurikulum');
    }
};
