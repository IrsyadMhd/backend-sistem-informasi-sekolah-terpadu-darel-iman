<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // 1. Pivot Subject N:N Guru Pengampu
        if (! Schema::hasTable('subject_teachers')) {
            Schema::create('subject_teachers', function (Blueprint $table) use ($isPgsql) {
                if ($isPgsql) {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('subject_id');
                $table->uuid('guru_id');
                $table->boolean('is_utama')->default(true);
                $table->timestampsTz();

                $table->unique(['subject_id', 'guru_id']);
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            });
        }

        // 2. Pivot Subject N:N Kelas
        if (! Schema::hasTable('subject_classes')) {
            Schema::create('subject_classes', function (Blueprint $table) use ($isPgsql) {
                if ($isPgsql) {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('subject_id');
                $table->uuid('kelas_id');
                $table->timestampsTz();

                $table->unique(['subject_id', 'kelas_id']);
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            });
        }

        // 3. Pivot Subject N:N Rombel
        if (! Schema::hasTable('subject_rombel')) {
            Schema::create('subject_rombel', function (Blueprint $table) use ($isPgsql) {
                if ($isPgsql) {
                    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
                } else {
                    $table->uuid('id')->primary();
                }
                $table->uuid('subject_id');
                $table->uuid('rombel_id');
                $table->timestampsTz();

                $table->unique(['subject_id', 'rombel_id']);
                $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_rombel');
        Schema::dropIfExists('subject_classes');
        Schema::dropIfExists('subject_teachers');
    }
};
