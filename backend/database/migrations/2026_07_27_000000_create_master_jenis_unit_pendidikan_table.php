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
        Schema::create('master_jenis_unit_pendidikan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kode_jenis', 20)->unique();
            $table->string('nama_jenis', 150)->unique();
            $table->string('singkatan', 50)->nullable();
            $table->string('jenjang', 50);
            $table->string('warna_badge', 30)->nullable()->default('#10B981');
            $table->string('icon', 50)->nullable()->default('School');
            $table->integer('urutan')->default(1);
            $table->text('keterangan')->nullable();
            $table->boolean('status')->default(true);

            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['jenjang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_jenis_unit_pendidikan');
    }
};
