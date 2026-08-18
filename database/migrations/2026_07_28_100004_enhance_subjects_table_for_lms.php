<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Enhance Subjects Table for LMS Integration & Master Mata Pelajaran CRUD
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::table('subjects', function (Blueprint $table) use ($isPgsql) {
            if (! Schema::hasColumn('subjects', 'unit_pendidikan_id')) {
                $table->uuid('unit_pendidikan_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('subjects', 'kurikulum_id')) {
                $table->uuid('kurikulum_id')->nullable()->after('unit_pendidikan_id');
            }
            if (! Schema::hasColumn('subjects', 'kode_mapel')) {
                $table->string('kode_mapel', 50)->nullable()->after('kurikulum_id');
            }
            if (! Schema::hasColumn('subjects', 'nama_mapel')) {
                $table->string('nama_mapel', 150)->nullable()->after('kode_mapel');
            }
            if (! Schema::hasColumn('subjects', 'nama_singkat')) {
                $table->string('nama_singkat', 50)->nullable()->after('nama_mapel');
            }
            if (! Schema::hasColumn('subjects', 'kelompok_mapel')) {
                $table->string('kelompok_mapel', 50)->default('Kelompok A')->after('nama_singkat');
            }
            if (! Schema::hasColumn('subjects', 'kategori')) {
                $table->string('kategori', 50)->default('Wajib')->after('kelompok_mapel');
            }
            if (! Schema::hasColumn('subjects', 'jenjang')) {
                $table->string('jenjang', 20)->default('SD')->after('kategori');
            }
            if (! Schema::hasColumn('subjects', 'tingkat_kelas')) {
                $table->string('tingkat_kelas', 20)->default('All')->after('jenjang');
            }
            if (! Schema::hasColumn('subjects', 'jam_pelajaran')) {
                $table->unsignedSmallInteger('jam_pelajaran')->default(2)->after('tingkat_kelas');
            }
            if (! Schema::hasColumn('subjects', 'guru_pengampu_id')) {
                $table->uuid('guru_pengampu_id')->nullable()->after('jam_pelajaran');
            }
            if (! Schema::hasColumn('subjects', 'kkm')) {
                $table->decimal('kkm', 5, 2)->default(75.00)->after('guru_pengampu_id');
            }
            if (! Schema::hasColumn('subjects', 'bobot_pengetahuan')) {
                $table->unsignedSmallInteger('bobot_pengetahuan')->default(40)->after('kkm');
            }
            if (! Schema::hasColumn('subjects', 'bobot_keterampilan')) {
                $table->unsignedSmallInteger('bobot_keterampilan')->default(40)->after('bobot_pengetahuan');
            }
            if (! Schema::hasColumn('subjects', 'bobot_sikap')) {
                $table->unsignedSmallInteger('bobot_sikap')->default(20)->after('bobot_keterampilan');
            }
            if (! Schema::hasColumn('subjects', 'bobot_nilai')) {
                if ($isPgsql) {
                    $table->jsonb('bobot_nilai')->nullable()->after('bobot_sikap');
                } else {
                    $table->json('bobot_nilai')->nullable()->after('bobot_sikap');
                }
            }
            if (! Schema::hasColumn('subjects', 'warna')) {
                $table->string('warna', 20)->default('#0E5C44')->after('bobot_nilai');
            }
            if (! Schema::hasColumn('subjects', 'ikon')) {
                $table->string('ikon', 50)->default('BookOpen')->after('warna');
            }
            if (! Schema::hasColumn('subjects', 'urutan_tampil')) {
                $table->unsignedInteger('urutan_tampil')->default(1)->after('ikon');
            }
            if (! Schema::hasColumn('subjects', 'status')) {
                $table->boolean('status')->default(true)->after('urutan_tampil');
            }
            if (! Schema::hasColumn('subjects', 'deskripsi')) {
                $table->text('deskripsi')->nullable()->after('status');
            }
            if (! Schema::hasColumn('subjects', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('subjects', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('subjects', 'deleted_by')) {
                $table->uuid('deleted_by')->nullable()->after('updated_by');
            }

            // Indeks performa
            $table->index(['unit_pendidikan_id'], 'idx_subjects_unit');
            $table->index(['kurikulum_id'], 'idx_subjects_kurikulum_lms');
            $table->index(['guru_pengampu_id'], 'idx_subjects_guru');
            $table->index(['status'], 'idx_subjects_status');
        });

        // Pengisian default data dari code & name jika ada
        DB::statement('UPDATE subjects SET kode_mapel = code WHERE kode_mapel IS NULL AND code IS NOT NULL');
        DB::statement('UPDATE subjects SET nama_mapel = name WHERE nama_mapel IS NULL AND name IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('idx_subjects_unit');
            $table->dropIndex('idx_subjects_kurikulum_lms');
            $table->dropIndex('idx_subjects_guru');
            $table->dropIndex('idx_subjects_status');
        });
    }
};
