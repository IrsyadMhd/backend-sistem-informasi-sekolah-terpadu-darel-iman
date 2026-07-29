<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_name', 100)->default('Sistem Manajemen Sekolah');
            $table->string('school_name', 150)->default('YAYASAN DAR EL - IMAN');
            $table->string('logo_text', 20)->default('YDE');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('header_style', 30)->default('light');
            $table->boolean('header_sticky')->default(true);
            $table->string('sidebar_style', 30)->default('gradient');
            $table->string('sidebar_position', 10)->default('left');
            $table->boolean('sidebar_collapsed')->default(false);
            $table->string('template', 30)->default('modern');
            $table->string('sidebar_color', 7)->default('#0E5C44');
            $table->string('sidebar_accent_color', 7)->default('#3FBF75');
            $table->string('body_color', 7)->default('#F7F9FC');
            $table->string('header_color', 7)->default('#FFFFFF');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
