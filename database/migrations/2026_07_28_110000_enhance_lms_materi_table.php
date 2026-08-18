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
        if (Schema::hasTable('lms_materi')) {
            Schema::table('lms_materi', function (Blueprint $table) {
                if (! Schema::hasColumn('lms_materi', 'tipe')) {
                    $table->string('tipe', 50)->default('teks')->after('judul');
                }
                if (! Schema::hasColumn('lms_materi', 'isi')) {
                    $table->longText('isi')->nullable()->after('tipe');
                }
                if (! Schema::hasColumn('lms_materi', 'file')) {
                    $table->string('file', 500)->nullable()->after('isi');
                }
                if (! Schema::hasColumn('lms_materi', 'video')) {
                    $table->string('video', 500)->nullable()->after('file');
                }
                if (! Schema::hasColumn('lms_materi', 'link')) {
                    $table->string('link', 500)->nullable()->after('video');
                }
                if (! Schema::hasColumn('lms_materi', 'status')) {
                    $table->string('status', 30)->default('aktif')->after('urutan');
                }
                if (! Schema::hasColumn('lms_materi', 'mata_pelajaran_id')) {
                    $table->uuid('mata_pelajaran_id')->nullable()->after('modul_ajar_id');
                }
                if (! Schema::hasColumn('lms_materi', 'guru_id')) {
                    $table->uuid('guru_id')->nullable()->after('mata_pelajaran_id');
                }
                if (! Schema::hasColumn('lms_materi', 'created_by')) {
                    $table->uuid('created_by')->nullable();
                }
                if (! Schema::hasColumn('lms_materi', 'updated_by')) {
                    $table->uuid('updated_by')->nullable();
                }
                if (! Schema::hasColumn('lms_materi', 'deleted_by')) {
                    $table->uuid('deleted_by')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lms_materi')) {
            Schema::table('lms_materi', function (Blueprint $table) {
                $columns = ['tipe', 'isi', 'file', 'video', 'link', 'status'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('lms_materi', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
