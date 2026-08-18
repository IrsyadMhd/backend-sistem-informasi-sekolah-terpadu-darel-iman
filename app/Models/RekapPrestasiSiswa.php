<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RekapPrestasiSiswa extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'id_siswa',
        'jenis_prestasi',
        'nama_prestasi',
        'tingkat_prestasi',
        'tanggal_prestasi',
        'nilai_prestasi',
        'keterangan',
        'id_penginput',
        'data_tambahan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_prestasi' => 'date',
            'nilai_prestasi' => 'float',
            'data_tambahan' => 'array',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'id_siswa');
    }
}
