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
        if (!Schema::hasTable('doas')) {
            Schema::create('doas', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary(); // Numeric ID matching EQuran.id (1-228)
                $table->string('nama', 150)->index();
                $table->string('grup', 100)->nullable()->index();
                $table->text('ar')->nullable(); // Teks Arab dengan harakat
                $table->text('tr')->nullable(); // Transliterasi Latin
                $table->text('idn')->nullable(); // Terjemahan Bahasa Indonesia
                $table->text('tentang')->nullable(); // Referensi / Sumber Hadits
                $table->json('tag')->nullable(); // Multiple tags per doa e.g. ["tidur","malam"]
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doas');
    }
};
