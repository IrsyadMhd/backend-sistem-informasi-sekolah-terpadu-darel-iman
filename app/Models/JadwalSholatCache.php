<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalSholatCache extends Model
{
    use HasFactory;

    protected $table = 'jadwal_sholat_caches';

    protected $fillable = [
        'provinsi',
        'kabkota_id',
        'kabkota_name',
        'tanggal',
        'tanggal_lengkap',
        'hari',
        'bulan',
        'tahun',
        'imsak',
        'subuh',
        'terbit',
        'dhuha',
        'dzuhur',
        'ashar',
        'maghrib',
        'isya',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];
}
