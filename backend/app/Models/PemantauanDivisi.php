<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PemantauanDivisi extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'pemantauan_divisis';

    protected $fillable = [
        'id_tahun_ajaran',
        'id_semester',
        'tanggal_pemantauan',
        'nama_divisi',
        'aspek_pemantauan',
        'persentase_capaian',
        'status_pemantauan',
        'catatan',
        'id_penginput',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pemantauan' => 'date',
            'persentase_capaian' => 'float',
            'data_tambahan' => 'array',
        ];
    }
}
