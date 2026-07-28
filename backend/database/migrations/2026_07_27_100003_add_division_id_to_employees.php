<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE REFACTOR — Migration 03
 *
 * Masalah: Pegawai tidak terhubung ke divisi secara formal.
 *
 * Solusi: Tambah kolom `division_id` nullable ke `employees`.
 *
 * Aturan SAFE REFACTOR:
 * - Nullable → data pegawai lama tidak terpengaruh.
 * - Tidak ada perubahan pada controller/model existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'division_id')) {
                $table->uuid('division_id')->nullable()->after('unit_id');
                $table->foreign('division_id')
                    ->references('id')
                    ->on('divisions')
                    ->nullOnDelete();
            }

            // Tambah audit log (created_by, updated_by, deleted_by) ke employees
            if (! Schema::hasColumn('employees', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('employees', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('employees', 'deleted_by')) {
                $table->uuid('deleted_by')->nullable()->after('updated_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'division_id')) {
                $table->dropForeign(['division_id']);
                $table->dropColumn('division_id');
            }
            foreach (['created_by', 'updated_by', 'deleted_by'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
