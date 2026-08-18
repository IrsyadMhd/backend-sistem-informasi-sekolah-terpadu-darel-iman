<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->string('father_nik', 32)->nullable()->after('nik');
            $table->string('mother_nik', 32)->nullable()->after('father_nik');
            $table->index('father_nik', 'parents_father_nik_index');
            $table->index('mother_nik', 'parents_mother_nik_index');
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropIndex('parents_father_nik_index');
            $table->dropIndex('parents_mother_nik_index');
            $table->dropColumn(['father_nik', 'mother_nik']);
        });
    }
};
