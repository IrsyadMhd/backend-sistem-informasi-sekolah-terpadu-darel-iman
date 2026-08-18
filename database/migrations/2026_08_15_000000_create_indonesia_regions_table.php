<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indonesia_regions', function (Blueprint $table) {
            $table->id();
            $table->string('provinsi', 100)->index();
            $table->string('kota_kabupaten', 100)->index();
            $table->string('kecamatan', 100)->index();
            $table->string('kelurahan', 100)->index();
            $table->timestamps();

            $table->index(['provinsi', 'kota_kabupaten'], 'idx_region_prov_kota');
            $table->index(['kota_kabupaten', 'kecamatan'], 'idx_region_kota_kec');
            $table->index(['kecamatan', 'kelurahan'], 'idx_region_kec_kel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indonesia_regions');
    }
};
