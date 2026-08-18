<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->string('satuan_kerja', 40)
                ->nullable()
                ->index()
                ->after('name');
            $table->string('scope_akses', 40)
                ->nullable()
                ->index()
                ->after('role_sistem_id');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropIndex(['satuan_kerja']);
            $table->dropIndex(['scope_akses']);
            $table->dropColumn(['satuan_kerja', 'scope_akses']);
        });
    }
};
