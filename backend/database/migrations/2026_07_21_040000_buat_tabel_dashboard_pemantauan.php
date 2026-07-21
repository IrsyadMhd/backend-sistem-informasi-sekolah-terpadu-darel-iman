<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemantauan_divisis', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('id_tahun_ajaran')->nullable();
            $table->uuid('id_semester')->nullable();
            $table->date('tanggal_pemantauan')->index();
            $table->string('nama_divisi', 120)->index();
            $table->string('aspek_pemantauan', 150);
            $table->decimal('persentase_capaian', 5, 2)->default(0);
            $table->string('status_pemantauan', 30)->default('proses')->index();
            $table->text('catatan')->nullable();
            $table->uuid('id_penginput');
            $table->jsonb('data_tambahan')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['id_tahun_ajaran', 'id_semester', 'tanggal_pemantauan', 'nama_divisi'], 'pemantauan_divisis_periode_idx');
            $table->foreign('id_tahun_ajaran')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('id_semester')->references('id')->on('semesters')->nullOnDelete();
            $table->foreign('id_penginput')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('laporan_bulanans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('id_tahun_ajaran')->nullable();
            $table->uuid('id_semester')->nullable();
            $table->smallInteger('bulan')->index();
            $table->smallInteger('tahun')->index();
            $table->string('judul_laporan', 180);
            $table->text('ringkasan_laporan');
            $table->text('tindak_lanjut')->nullable();
            $table->string('status_validasi', 30)->default('draf')->index();
            $table->uuid('id_penginput');
            $table->uuid('id_pemeriksa')->nullable();
            $table->jsonb('data_tambahan')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['bulan', 'tahun', 'status_validasi'], 'laporan_bulanans_periode_idx');
            $table->foreign('id_tahun_ajaran')->references('id')->on('academic_years')->nullOnDelete();
            $table->foreign('id_semester')->references('id')->on('semesters')->nullOnDelete();
            $table->foreign('id_penginput')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('id_pemeriksa')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('rekap_prestasi_siswas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('id_siswa');
            $table->string('jenis_prestasi', 30)->index();
            $table->string('nama_prestasi', 180);
            $table->string('tingkat_prestasi', 80)->nullable();
            $table->date('tanggal_prestasi')->index();
            $table->decimal('nilai_prestasi', 7, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->uuid('id_penginput');
            $table->jsonb('data_tambahan')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['id_siswa', 'jenis_prestasi', 'tanggal_prestasi'], 'rekap_prestasi_siswas_lookup_idx');
            $table->foreign('id_siswa')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('id_penginput')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('pengumuman_sekolahs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('judul_pengumuman', 200);
            $table->text('isi_pengumuman');
            $table->jsonb('target_peran')->nullable();
            $table->timestampTz('mulai_tampil')->index();
            $table->timestampTz('selesai_tampil')->nullable()->index();
            $table->smallInteger('prioritas')->default(1)->index();
            $table->boolean('status_aktif')->default(true)->index();
            $table->uuid('id_penerbit');
            $table->jsonb('data_tambahan')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->foreign('id_penerbit')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement("CREATE INDEX pengumuman_sekolahs_fts_idx ON pengumuman_sekolahs USING GIN (to_tsvector('simple', coalesce(judul_pengumuman,'') || ' ' || coalesce(isi_pengumuman,'')))");

        Schema::create('indikator_kinerja_utamas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('kode_indikator', 80)->unique();
            $table->string('nama_indikator', 150);
            $table->string('kategori_indikator', 80)->index();
            $table->decimal('nilai', 12, 2)->default(0);
            $table->decimal('target_nilai', 12, 2)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->smallInteger('bulan_periode')->index();
            $table->smallInteger('tahun_periode')->index();
            $table->string('warna_hex', 7)->nullable();
            $table->smallInteger('urutan_tampil')->default(0);
            $table->uuid('id_penginput');
            $table->jsonb('data_tambahan')->nullable();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['tahun_periode', 'bulan_periode', 'kategori_indikator'], 'indikator_kinerja_utamas_periode_idx');
            $table->foreign('id_penginput')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pengumuman_sekolahs_fts_idx');

        Schema::dropIfExists('indikator_kinerja_utamas');
        Schema::dropIfExists('pengumuman_sekolahs');
        Schema::dropIfExists('rekap_prestasi_siswas');
        Schema::dropIfExists('laporan_bulanans');
        Schema::dropIfExists('pemantauan_divisis');
    }
};
