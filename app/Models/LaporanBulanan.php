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
        'kode_laporan',
        'id_tahun_ajaran',
        'id_semester',
        'unit_id',
        'bulan',
        'tahun',
        'periode',
        'judul_laporan',
        'kategori_laporan',
        'ringkasan_laporan',
        'tindak_lanjut',
        'status_validasi',
        'catatan_divisi',
        'tanggal_kirim',
        'persentase_kelengkapan',
        'id_penginput',
        'id_pemeriksa',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'bulan' => 'integer',
            'tahun' => 'integer',
            'persentase_kelengkapan' => 'integer',
            'data_tambahan' => 'array',
            'tanggal_kirim' => 'date',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function penginput()
    {
        return $this->belongsTo(User::class, 'id_penginput');
    }

    public function pemeriksa()
    {
        return $this->belongsTo(User::class, 'id_pemeriksa');
    }
}
