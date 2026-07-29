<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LMS Evaluasi Tables — Layer 3: Penilaian & Ujian Online (CBT)
 *
 * Tabel yang dibuat:
 * 1. lms_kisi_kisi    — Blueprint / Kisi-kisi Ujian
 * 2. lms_bank_soal    — Butir soal per kisi-kisi
 * 3. lms_ujian        — Sesi ujian yang dipublikasikan ke kelas
 * 4. lms_ujian_sesi   — Sesi pengerjaan ujian per siswa
 * 5. lms_jawaban_siswa — Jawaban per butir soal
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // ─────────────────────────────────────────────────────────────────────
        // 1. Kisi-kisi Ujian
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_kisi_kisi', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('kurikulum_id');
            $table->uuid('mata_pelajaran_id');
            $table->uuid('kelas_id');
            $table->uuid('semester_id');
            $table->uuid('tahun_ajaran_id');
            $table->uuid('guru_id');

            $table->string('judul_kisi', 200);
            $table->string('jenis_ujian', 30)->default('UH')
                ->comment('UH, PTS, UTS, PAS, UAS, CBT, Remedial');
            $table->unsignedSmallInteger('jumlah_soal')->default(20);
            $table->unsignedSmallInteger('alokasi_waktu_menit')->default(60);
            $table->text('kompetensi_dasar')->nullable();
            $table->string('level_kognitif', 100)->nullable()
                ->comment('C1-C6 Bloom Taxonomy');

            if ($isPgsql) {
                $table->jsonb('distribusi_bobot')->nullable()
                    ->comment('{"pg": 60, "isian": 20, "esai": 20}');
            } else {
                $table->json('distribusi_bobot')->nullable();
            }

            $table->boolean('status')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            $table->index(['kurikulum_id', 'mata_pelajaran_id', 'kelas_id'], 'lms_kisi_core_idx');
            $table->index(['semester_id', 'tahun_ajaran_id'], 'lms_kisi_period_idx');

            $table->foreign('kurikulum_id')->references('id')->on('master_kurikulum')->restrictOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->restrictOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->restrictOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 2. Bank Soal
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_bank_soal', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('kisi_kisi_id');
            $table->uuid('mata_pelajaran_id');

            $table->string('kode_soal', 30)->nullable();
            $table->text('pertanyaan');
            $table->string('tipe_soal', 20)->default('pg')
                ->comment('pg, isian, esai, benar_salah, menjodohkan');
            $table->text('opsi_a')->nullable();
            $table->text('opsi_b')->nullable();
            $table->text('opsi_c')->nullable();
            $table->text('opsi_d')->nullable();
            $table->text('opsi_e')->nullable();
            $table->text('kunci_jawaban')->nullable()
                ->comment('Untuk pg: A/B/C/D/E. Untuk esai: null (manual)');
            $table->text('pembahasan')->nullable();
            $table->decimal('poin', 5, 2)->default(1);
            $table->string('tingkat_kesulitan', 20)->default('sedang')
                ->comment('mudah, sedang, sulit');
            $table->string('indikator', 500)->nullable();
            $table->string('gambar_path', 500)->nullable();
            $table->boolean('status')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            $table->index(['kisi_kisi_id', 'status'], 'lms_soal_kisi_idx');
            $table->index(['mata_pelajaran_id', 'tingkat_kesulitan'], 'lms_soal_mapel_idx');

            $table->foreign('kisi_kisi_id')->references('id')->on('lms_kisi_kisi')->cascadeOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 3. Ujian (CBT) — Sesi Ujian yang Dipublikasikan
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_ujian', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('kisi_kisi_id');
            $table->uuid('kelas_id');
            $table->uuid('semester_id');
            $table->uuid('guru_id');

            $table->string('judul_ujian', 200);
            $table->text('instruksi')->nullable();
            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->unsignedSmallInteger('durasi_menit')->default(60);
            $table->boolean('acak_soal')->default(false);
            $table->boolean('acak_jawaban')->default(false);
            $table->boolean('tampilkan_nilai_langsung')->default(false);
            $table->decimal('nilai_kkm', 5, 2)->default(70);
            $table->unsignedTinyInteger('max_attempt')->default(1);
            $table->string('status', 20)->default('draft')
                ->comment('draft, published, berlangsung, selesai, dibatalkan');

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
                $table->softDeletesTz();
            } else {
                $table->timestamps();
                $table->softDeletes();
            }

            $table->index(['kelas_id', 'semester_id', 'status'], 'lms_ujian_core_idx');
            $table->index(['waktu_mulai', 'waktu_selesai'], 'lms_ujian_time_idx');

            $table->foreign('kisi_kisi_id')->references('id')->on('lms_kisi_kisi')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->restrictOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 4. Sesi Ujian per Siswa
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_ujian_sesi', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('ujian_id');
            $table->uuid('siswa_id')->comment('FK ke students');

            $table->timestamp('waktu_mulai')->nullable();
            $table->timestamp('waktu_selesai')->nullable();
            $table->unsignedInteger('durasi_aktual_detik')->nullable();
            $table->unsignedSmallInteger('jumlah_benar')->default(0);
            $table->unsignedSmallInteger('jumlah_salah')->default(0);
            $table->unsignedSmallInteger('jumlah_kosong')->default(0);
            $table->decimal('nilai_raw', 5, 2)->nullable();
            $table->decimal('nilai_final', 5, 2)->nullable();
            $table->string('status', 20)->default('proses')
                ->comment('proses, selesai, timeout, dibatalkan');
            $table->string('ip_address', 45)->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
            } else {
                $table->timestamps();
            }

            $table->index(['ujian_id', 'siswa_id'], 'lms_sesi_ujian_siswa_idx');
            $table->index(['ujian_id', 'status'], 'lms_sesi_status_idx');

            $table->foreign('ujian_id')->references('id')->on('lms_ujian')->cascadeOnDelete();
            $table->foreign('siswa_id')->references('id')->on('students')->cascadeOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 5. Jawaban Siswa per Butir Soal
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_jawaban_siswa', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('sesi_id')->comment('FK ke lms_ujian_sesi');
            $table->uuid('soal_id')->comment('FK ke lms_bank_soal');

            $table->string('jawaban_dipilih', 10)->nullable()->comment('A/B/C/D/E untuk PG');
            $table->text('jawaban_esai')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('poin_didapat', 5, 2)->default(0);
            $table->unsignedInteger('waktu_jawab_detik')->nullable();

            // Untuk esai: penilaian manual guru
            $table->text('catatan_guru')->nullable();
            $table->uuid('dinilai_oleh')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
            } else {
                $table->timestamps();
            }

            // Satu sesi hanya boleh menjawab satu soal sekali
            $table->unique(['sesi_id', 'soal_id'], 'lms_jawaban_sesi_soal_unique');

            $table->index(['sesi_id'], 'lms_jawaban_sesi_idx');

            $table->foreign('sesi_id')->references('id')->on('lms_ujian_sesi')->cascadeOnDelete();
            $table->foreign('soal_id')->references('id')->on('lms_bank_soal')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_jawaban_siswa');
        Schema::dropIfExists('lms_ujian_sesi');
        Schema::dropIfExists('lms_ujian');
        Schema::dropIfExists('lms_bank_soal');
        Schema::dropIfExists('lms_kisi_kisi');
    }
};
