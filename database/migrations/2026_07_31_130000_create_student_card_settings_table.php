<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_card_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('education_unit_id')->nullable();
            $table->string('orientation', 20)->default('horizontal');
            $table->string('template_color', 30)->default('green');
            $table->boolean('show_photo')->default(true);
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_qrcode')->default(true);
            $table->boolean('show_nis')->default(true);
            $table->boolean('show_nisn')->default(true);
            $table->boolean('show_class')->default(true);
            $table->boolean('show_rombel')->default(true);
            $table->boolean('show_unit')->default(true);
            $table->boolean('show_academic_year')->default(false);
            $table->boolean('show_motto')->default(true);
            $table->boolean('is_default')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'education_unit_id'], 'student_card_settings_scope_idx');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('education_unit_id')->references('id')->on('education_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_card_settings');
    }
};
