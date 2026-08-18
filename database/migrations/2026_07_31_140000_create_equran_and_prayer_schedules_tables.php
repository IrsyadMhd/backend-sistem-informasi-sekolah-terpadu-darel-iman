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
        if (!Schema::hasTable('quran_surahs')) {
            Schema::create('quran_surahs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('nomor')->unique();
                $table->string('nama', 100);
                $table->string('nama_latin', 100);
                $table->unsignedInteger('jumlah_ayat');
                $table->string('tempat_turun', 50);
                $table->string('arti', 150);
                $table->text('deskripsi')->nullable();
                $table->string('audio_full', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jadwal_sholat_caches')) {
            Schema::create('jadwal_sholat_caches', function (Blueprint $table) {
                $table->id();
                $table->string('provinsi', 100)->nullable()->index();
                $table->string('kabkota_id', 50)->nullable()->index();
                $table->string('kabkota_name', 150)->nullable()->index();
                $table->date('tanggal')->index();
                $table->string('tanggal_lengkap', 25)->nullable();
                $table->string('hari', 20)->nullable();
                $table->unsignedTinyInteger('bulan')->nullable()->index();
                $table->unsignedSmallInteger('tahun')->nullable()->index();
                $table->string('imsak', 10)->nullable();
                $table->string('subuh', 10);
                $table->string('terbit', 10)->nullable();
                $table->string('dhuha', 10)->nullable();
                $table->string('dzuhur', 10);
                $table->string('ashar', 10);
                $table->string('maghrib', 10);
                $table->string('isya', 10);
                $table->timestamps();

                $table->unique(['provinsi', 'kabkota_name', 'tanggal'], 'unique_prov_kab_tgl');
            });
        } else {
            Schema::table('jadwal_sholat_caches', function (Blueprint $table) {
                if (!Schema::hasColumn('jadwal_sholat_caches', 'provinsi')) {
                    $table->string('provinsi', 100)->nullable()->index();
                }
                if (!Schema::hasColumn('jadwal_sholat_caches', 'tanggal_lengkap')) {
                    $table->string('tanggal_lengkap', 25)->nullable();
                }
                if (!Schema::hasColumn('jadwal_sholat_caches', 'hari')) {
                    $table->string('hari', 20)->nullable();
                }
                if (!Schema::hasColumn('jadwal_sholat_caches', 'bulan')) {
                    $table->unsignedTinyInteger('bulan')->nullable()->index();
                }
                if (!Schema::hasColumn('jadwal_sholat_caches', 'tahun')) {
                    $table->unsignedSmallInteger('tahun')->nullable()->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_sholat_caches');
        Schema::dropIfExists('quran_surahs');
    }
};
