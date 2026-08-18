<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // 1. Enhance lms_modul_ajar table
        Schema::table('lms_modul_ajar', function (Blueprint $table) use ($isPgsql) {
            if (! Schema::hasColumn('lms_modul_ajar', 'unit_pendidikan_id')) {
                $table->uuid('unit_pendidikan_id')->nullable()->after('id')->comment('FK ke education_units');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'rombel_id')) {
                $table->uuid('rombel_id')->nullable()->after('kelas_id')->comment('FK ke tbl_kelas (rombel)');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'cp_id')) {
                $table->uuid('cp_id')->nullable()->after('tp_id')->comment('FK ke lms_capaian_pembelajaran');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'kode_modul')) {
                $table->string('kode_modul', 50)->nullable()->after('cp_id');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'fase')) {
                $table->string('fase', 20)->default('Fase D')->after('judul_modul');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'semester')) {
                $table->string('semester', 20)->default('Ganjil')->after('fase');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'target_peserta_didik')) {
                $table->text('target_peserta_didik')->nullable()->after('profil_pelajar_pancasila');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'asesmen_awal')) {
                $table->text('asesmen_awal')->nullable()->after('kegiatan_penutup');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'asesmen_proses')) {
                $table->text('asesmen_proses')->nullable()->after('asesmen_awal');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'asesmen_akhir')) {
                $table->text('asesmen_akhir')->nullable()->after('asesmen_proses');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'lampiran')) {
                if ($isPgsql) {
                    $table->jsonb('lampiran')->nullable()->after('asesmen_akhir');
                } else {
                    $table->text('lampiran')->nullable()->after('asesmen_akhir');
                }
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('status');
            }

            if (! Schema::hasColumn('lms_modul_ajar', 'versi')) {
                $table->string('versi', 20)->default('1.0')->after('deskripsi');
            }
        });

        // Add foreign keys and indexes if missing
        Schema::table('lms_modul_ajar', function (Blueprint $table) {
            try {
                $table->foreign('unit_pendidikan_id')->references('id')->on('education_units')->nullOnDelete();
            } catch (Throwable $e) {
            }

            try {
                $table->foreign('rombel_id')->references('id')->on('tbl_kelas')->nullOnDelete();
            } catch (Throwable $e) {
            }

            try {
                $table->foreign('cp_id')->references('id')->on('lms_capaian_pembelajaran')->nullOnDelete();
            } catch (Throwable $e) {
            }

            try {
                $table->index(['unit_pendidikan_id', 'tahun_ajaran_id', 'status'], 'lms_modul_unit_status_idx');
            } catch (Throwable $e) {
            }
        });

        // 2. Create lms_modul_ajar_revisions table
        if (! Schema::hasTable('lms_modul_ajar_revisions')) {
            Schema::create('lms_modul_ajar_revisions', function (Blueprint $table) use ($isPgsql) {
                $table->uuid('id')->primary();
                $table->uuid('modul_ajar_id');
                $table->string('versi', 20)->default('1.0');
                $table->string('judul_modul', 200);
                $table->text('catatan_revisi')->nullable();

                if ($isPgsql) {
                    $table->jsonb('snapshot_data')->nullable();
                } else {
                    $table->longText('snapshot_data')->nullable();
                }

                $table->uuid('created_by')->nullable();

                if ($isPgsql) {
                    $table->timestampTz('created_at')->nullable();
                } else {
                    $table->timestamp('created_at')->nullable();
                }

                $table->foreign('modul_ajar_id')->references('id')->on('lms_modul_ajar')->cascadeOnDelete();
                $table->index(['modul_ajar_id', 'versi'], 'lms_modul_rev_idx');
            });
        }

        // 3. Create lms_modul_ajar_cp pivot table
        if (! Schema::hasTable('lms_modul_ajar_cp')) {
            Schema::create('lms_modul_ajar_cp', function (Blueprint $table) {
                $table->uuid('modul_ajar_id');
                $table->uuid('cp_id');
                $table->primary(['modul_ajar_id', 'cp_id']);
                $table->foreign('modul_ajar_id')->references('id')->on('lms_modul_ajar')->cascadeOnDelete();
                $table->foreign('cp_id')->references('id')->on('lms_capaian_pembelajaran')->cascadeOnDelete();
            });
        }

        // 4. Create lms_modul_ajar_tp pivot table
        if (! Schema::hasTable('lms_modul_ajar_tp')) {
            Schema::create('lms_modul_ajar_tp', function (Blueprint $table) {
                $table->uuid('modul_ajar_id');
                $table->uuid('tp_id');
                $table->primary(['modul_ajar_id', 'tp_id']);
                $table->foreign('modul_ajar_id')->references('id')->on('lms_modul_ajar')->cascadeOnDelete();
                $table->foreign('tp_id')->references('id')->on('lms_tujuan_pembelajaran')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lms_modul_ajar_tp');
        Schema::dropIfExists('lms_modul_ajar_cp');
        Schema::dropIfExists('lms_modul_ajar_revisions');

        Schema::table('lms_modul_ajar', function (Blueprint $table) {
            $columns = [
                'unit_pendidikan_id',
                'rombel_id',
                'cp_id',
                'kode_modul',
                'fase',
                'semester',
                'target_peserta_didik',
                'asesmen_awal',
                'asesmen_proses',
                'asesmen_akhir',
                'lampiran',
                'deskripsi',
                'versi',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('lms_modul_ajar', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
