<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaporanBulanan extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'id_tahun_ajaran',
        'id_semester',
        'bulan',
        'tahun',
        'judul_laporan',
        'ringkasan_laporan',
        'tindak_lanjut',
        'status_validasi',
        'id_penginput',
        'id_pemeriksa',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'data_tambahan' => 'array',
        ];
    }
}
