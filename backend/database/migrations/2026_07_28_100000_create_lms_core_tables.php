<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LMS Core Tables — Layer 1: Perencanaan Pembelajaran
 *
 * Tabel yang dibuat:
 * 1. lms_capaian_pembelajaran — Capaian Pembelajaran (CP) per Mata Pelajaran & Kurikulum
 * 2. lms_tujuan_pembelajaran — Tujuan Pembelajaran (TP) turunan CP
 * 3. lms_modul_ajar — Rencana Pelaksanaan Pembelajaran (RPP Digital)
 * 4. lms_materi — Konten Materi per Modul Ajar
 * 5. lms_media — File / Attachment Media Pembelajaran
 *
 * Relasi Utama (tidak menduplikasi tabel yang ada):
 * - kurikulum_id → master_kurikulum.id
 * - mata_pelajaran_id → subjects.id
 * - guru_id → employees.id
 * - kelas_id → tbl_kelas.id
 * - semester_id → semesters.id
 * - tahun_ajaran_id → academic_years.id
 */
return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        // ─────────────────────────────────────────────────────────────────────
        // 1. Capaian Pembelajaran (CP)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_capaian_pembelajaran', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('kurikulum_id');
            $table->uuid('mata_pelajaran_id');

            $table->string('kode_cp', 30)->comment('Contoh: CP-MTK-SD-1');
            $table->string('nama_cp', 200);
            $table->text('deskripsi')->nullable();
            $table->string('fase', 10)->nullable()->comment('Fase A, B, C, D, E, F (Kurikulum Merdeka)');
            $table->string('kelas_target', 20)->nullable()->comment('Kelas 1-12 atau All');
            $table->unsignedSmallInteger('urutan')->default(1);
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

            // Indeks performa
            $table->index(['kurikulum_id', 'mata_pelajaran_id'], 'lms_cp_kurikulum_mapel_idx');
            $table->index(['status'], 'lms_cp_status_idx');

            // FK
            $table->foreign('kurikulum_id')->references('id')->on('master_kurikulum')->cascadeOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->cascadeOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 2. Tujuan Pembelajaran (TP)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_tujuan_pembelajaran', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('cp_id')->comment('FK ke lms_capaian_pembelajaran');

            $table->string('kode_tp', 30)->comment('Contoh: TP-MTK-SD-1-1');
            $table->string('nama_tp', 250);
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('alokasi_waktu_jp')->default(2)->comment('Jam Pelajaran');
            $table->unsignedSmallInteger('urutan')->default(1);
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

            $table->index(['cp_id'], 'lms_tp_cp_idx');

            $table->foreign('cp_id')->references('id')->on('lms_capaian_pembelajaran')->cascadeOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 3. Modul Ajar (RPP Digital)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_modul_ajar', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('tp_id')->nullable()->comment('FK ke lms_tujuan_pembelajaran');
            $table->uuid('kurikulum_id');
            $table->uuid('mata_pelajaran_id');
            $table->uuid('guru_id')->comment('FK ke employees');
            $table->uuid('kelas_id')->comment('FK ke tbl_kelas');
            $table->uuid('semester_id');
            $table->uuid('tahun_ajaran_id');

            $table->string('judul_modul', 200);
            $table->text('tujuan_pembelajaran')->nullable();
            $table->text('profil_pelajar_pancasila')->nullable();
            $table->string('model_pembelajaran', 100)->nullable()->comment('Problem Based Learning, dll');
            $table->string('metode_pembelajaran', 200)->nullable();
            $table->unsignedSmallInteger('alokasi_waktu_jp')->default(2);
            $table->string('media_pembelajaran', 500)->nullable();
            $table->string('sumber_belajar', 500)->nullable();
            $table->text('kegiatan_pendahuluan')->nullable();
            $table->text('kegiatan_inti')->nullable();
            $table->text('kegiatan_penutup')->nullable();
            $table->text('rencana_penilaian')->nullable();
            $table->text('refleksi_guru')->nullable();
            $table->string('status', 20)->default('draft')->comment('draft, published, archived');

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

            $table->index(['kurikulum_id', 'mata_pelajaran_id', 'kelas_id'], 'lms_modul_core_idx');
            $table->index(['guru_id', 'tahun_ajaran_id', 'semester_id'], 'lms_modul_guru_period_idx');
            $table->index(['status'], 'lms_modul_status_idx');

            $table->foreign('tp_id')->references('id')->on('lms_tujuan_pembelajaran')->nullOnDelete();
            $table->foreign('kurikulum_id')->references('id')->on('master_kurikulum')->restrictOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->restrictOnDelete();
            $table->foreign('kelas_id')->references('id')->on('tbl_kelas')->restrictOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->restrictOnDelete();
            $table->foreign('tahun_ajaran_id')->references('id')->on('academic_years')->restrictOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 4. Materi Pembelajaran
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_materi', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('modul_ajar_id');
            $table->uuid('mata_pelajaran_id');
            $table->uuid('guru_id');

            $table->string('judul', 200);
            $table->longText('konten')->nullable();
            $table->string('tipe_materi', 20)->default('teks')
                ->comment('teks, video, pdf, link, quiz, presentasi');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('is_published')->default(false);
            $table->timestamp('tanggal_publish')->nullable();
            $table->text('catatan')->nullable();

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

            $table->index(['modul_ajar_id'], 'lms_materi_modul_idx');
            $table->index(['mata_pelajaran_id', 'is_published'], 'lms_materi_mapel_pub_idx');

            $table->foreign('modul_ajar_id')->references('id')->on('lms_modul_ajar')->cascadeOnDelete();
            $table->foreign('mata_pelajaran_id')->references('id')->on('subjects')->restrictOnDelete();
            $table->foreign('guru_id')->references('id')->on('employees')->restrictOnDelete();
        });

        // ─────────────────────────────────────────────────────────────────────
        // 5. Media Pembelajaran
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('lms_media', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            } else {
                $table->uuid('id')->primary();
            }

            $table->uuid('materi_id');
            $table->string('nama_file', 255);
            $table->string('tipe_file', 50)->nullable()->comment('image, video, pdf, audio, doc, etc');
            $table->string('path_file', 500)->nullable();
            $table->string('url_eksternal', 1000)->nullable();
            $table->unsignedBigInteger('ukuran_bytes')->nullable();
            $table->unsignedInteger('durasi_detik')->nullable()->comment('Untuk audio/video');
            $table->text('deskripsi')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);

            $table->uuid('created_by')->nullable();

            if ($isPgsql) {
                $table->timestampsTz();
            } else {
                $table->timestamps();
            }

            $table->index(['materi_id'], 'lms_media_materi_idx');

            $table->foreign('materi_id')->references('id')->on('lms_materi')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_media');
        Schema::dropIfExists('lms_materi');
        Schema::dropIfExists('lms_modul_ajar');
        Schema::dropIfExists('lms_tujuan_pembelajaran');
        Schema::dropIfExists('lms_capaian_pembelajaran');
    }
};
