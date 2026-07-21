<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengumumanSekolah extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'judul_pengumuman',
        'isi_pengumuman',
        'target_peran',
        'mulai_tampil',
        'selesai_tampil',
        'prioritas',
        'status_aktif',
        'id_penerbit',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'target_peran' => 'array',
            'mulai_tampil' => 'datetime',
            'selesai_tampil' => 'datetime',
            'status_aktif' => 'boolean',
            'prioritas' => 'integer',
            'data_tambahan' => 'array',
        ];
    }
}
