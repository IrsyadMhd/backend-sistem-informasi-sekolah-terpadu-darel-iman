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
        if (! Schema::hasTable('tahfizh_kpi_targets')) {
            Schema::create('tahfizh_kpi_targets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('education_unit_id')->nullable()->index();
                $table->string('unit_level', 30)->nullable()->index()->comment('TKIT, TAUD, SDIT, MIT, SMPIT, SMAIT, PONPES, ALL');
                $table->string('grade_level', 20)->nullable()->index()->comment('1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, ALL');
                $table->string('program_type', 30)->default('all')->index()->comment('all, fullday, boarding, regular');
                $table->string('target_name', 150);
                $table->string('target_juz', 50)->nullable()->comment('Contoh: Juz 30, Juz 29-30, 30 Juz Penuh');
                $table->integer('target_surahs')->default(37);
                $table->integer('target_ayats')->default(564);
                $table->integer('minimum_kkm_ayats')->default(0)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('education_unit_id')
                    ->references('id')
                    ->on('education_units')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tahfizh_kpi_targets');
    }
};
