<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_configs', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->default('android')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->json('config');
            $table->boolean('is_published')->default(true);
            $table->timestampTz('published_at')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_configs');
    }
};
