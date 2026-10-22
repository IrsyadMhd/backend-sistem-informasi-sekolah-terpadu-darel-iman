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
        if (! Schema::hasTable('business_options')) {
            Schema::create('business_options', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('group', 60)->index();
                $table->string('code', 60);
                $table->string('name', 120);
                $table->integer('order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['group', 'code'], 'uniq_business_opt_group_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_options');
    }
};
