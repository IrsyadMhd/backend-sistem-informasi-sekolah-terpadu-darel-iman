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
        if (Schema::hasTable('subjects') && ! Schema::hasColumn('subjects', 'kurikulum_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->uuid('kurikulum_id')->nullable()->after('id');
                $table->index('kurikulum_id');
                $table->foreign('kurikulum_id')->references('id')->on('master_kurikulum')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subjects') && Schema::hasColumn('subjects', 'kurikulum_id')) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropForeign(['kurikulum_id']);
                $table->dropIndex(['kurikulum_id']);
                $table->dropColumn('kurikulum_id');
            });
        }
    }
};
