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
        if (Schema::hasTable('lms_penugasan')) {
            Schema::table('lms_penugasan', function (Blueprint $table) {
                if (! Schema::hasColumn('lms_penugasan', 'metadata')) {
                    $table->json('metadata')->nullable()->after('file_lampiran');
                }
                if (! Schema::hasColumn('lms_penugasan', 'link_lampiran')) {
                    $table->string('link_lampiran', 1000)->nullable()->after('file_lampiran');
                }
            });
        }

        if (Schema::hasTable('lms_pengumpulan_tugas')) {
            Schema::table('lms_pengumpulan_tugas', function (Blueprint $table) {
                if (! Schema::hasColumn('lms_pengumpulan_tugas', 'metadata')) {
                    $table->json('metadata')->nullable()->after('url_link');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lms_penugasan')) {
            Schema::table('lms_penugasan', function (Blueprint $table) {
                if (Schema::hasColumn('lms_penugasan', 'metadata')) {
                    $table->dropColumn('metadata');
                }
                if (Schema::hasColumn('lms_penugasan', 'link_lampiran')) {
                    $table->dropColumn('link_lampiran');
                }
            });
        }

        if (Schema::hasTable('lms_pengumpulan_tugas')) {
            Schema::table('lms_pengumpulan_tugas', function (Blueprint $table) {
                if (Schema::hasColumn('lms_pengumpulan_tugas', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }
    }
};
