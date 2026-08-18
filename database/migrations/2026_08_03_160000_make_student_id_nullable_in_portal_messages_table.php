<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_messages')) {
            Schema::table('portal_messages', function (Blueprint $table) {
                $table->uuid('student_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('portal_messages')) {
            Schema::table('portal_messages', function (Blueprint $table) {
                $table->uuid('student_id')->nullable(false)->change();
            });
        }
    }
};
