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
        if (! Schema::hasTable('code_sequences')) {
            Schema::create('code_sequences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('entity_type', 60)->index();
                $table->string('prefix', 30)->nullable();
                $table->string('separator', 5)->default('-');
                $table->unsignedInteger('padding_length')->default(4);
                $table->uuid('unit_id')->nullable()->index();
                $table->uuid('academic_year_id')->nullable()->index();
                $table->string('period_key', 30)->nullable()->index();
                $table->unsignedBigInteger('current_sequence')->default(0);
                $table->string('format_pattern', 120)->default('{PREFIX}{SEPARATOR}{PERIOD}{SEPARATOR}{SEQUENCE}');
                $table->boolean('is_active')->default(true);
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->unique(['entity_type', 'unit_id', 'period_key'], 'uniq_code_seq_entity_unit_period');
            });

            if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uniq_code_seq_null_unit ON code_sequences (entity_type, period_key) WHERE unit_id IS NULL;');
                \Illuminate\Support\Facades\DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uniq_code_seq_with_unit ON code_sequences (entity_type, unit_id, period_key) WHERE unit_id IS NOT NULL;');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_sequences');
    }
};
