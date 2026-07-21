<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndikatorKinerjaUtama extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'indikator_kinerja_utamas';

    protected $fillable = [
        'kode_indikator',
        'nama_indikator',
        'kategori_indikator',
        'nilai',
        'target_nilai',
        'satuan',
        'bulan_periode',
        'tahun_periode',
        'warna_hex',
        'urutan_tampil',
        'id_penginput',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'float',
            'target_nilai' => 'float',
            'bulan_periode' => 'integer',
            'tahun_periode' => 'integer',
            'urutan_tampil' => 'integer',
            'data_tambahan' => 'array',
        ];
    }
}
