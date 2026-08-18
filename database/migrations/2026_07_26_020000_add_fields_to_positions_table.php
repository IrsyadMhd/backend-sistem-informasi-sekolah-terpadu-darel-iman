<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'unit_sekolah_id')) {
                $table->uuid('unit_sekolah_id')->nullable()->after('name');
            }
            if (! Schema::hasColumn('positions', 'level_jabatan')) {
                $table->integer('level_jabatan')->default(9)->index()->after('unit_sekolah_id');
            }
            if (! Schema::hasColumn('positions', 'atasan_langsung_id')) {
                $table->uuid('atasan_langsung_id')->nullable()->after('level_jabatan');
            }
            if (! Schema::hasColumn('positions', 'role_sistem_id')) {
                $table->unsignedBigInteger('role_sistem_id')->nullable()->after('atasan_langsung_id');
            }
            if (! Schema::hasColumn('positions', 'urutan')) {
                $table->integer('urutan')->default(0)->after('role_sistem_id');
            }
            if (! Schema::hasColumn('positions', 'warna')) {
                $table->string('warna', 30)->default('#3B82F6')->after('urutan');
            }
            if (! Schema::hasColumn('positions', 'ikon')) {
                $table->string('ikon', 50)->default('UserCheck')->after('warna');
            }
            if (! Schema::hasColumn('positions', 'tampil_struktur')) {
                $table->boolean('tampil_struktur')->default(true)->after('ikon');
            }
            if (! Schema::hasColumn('positions', 'boleh_login')) {
                $table->boolean('boleh_login')->default(false)->after('tampil_struktur');
            }
            if (! Schema::hasColumn('positions', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('positions', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }

            // Foreign keys
            $table->foreign('unit_sekolah_id')->references('id')->on('education_units')->nullOnDelete();
            $table->foreign('atasan_langsung_id')->references('id')->on('positions')->nullOnDelete();
            $table->foreign('role_sistem_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['unit_sekolah_id']);
            $table->dropForeign(['atasan_langsung_id']);
            $table->dropForeign(['role_sistem_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropColumn([
                'unit_sekolah_id',
                'level_jabatan',
                'atasan_langsung_id',
                'role_sistem_id',
                'urutan',
                'warna',
                'ikon',
                'tampil_struktur',
                'boleh_login',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
