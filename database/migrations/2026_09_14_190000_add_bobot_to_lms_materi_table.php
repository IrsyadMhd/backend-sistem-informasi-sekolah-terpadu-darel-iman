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
        Schema::table('lms_materi', function (Blueprint $table) {
            if (!Schema::hasColumn('lms_materi', 'bobot')) {
                $table->integer('bobot')->nullable()->default(0)->after('catatan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lms_materi', function (Blueprint $table) {
            if (Schema::hasColumn('lms_materi', 'bobot')) {
                $table->dropColumn('bobot');
            }
        });
    }
};
