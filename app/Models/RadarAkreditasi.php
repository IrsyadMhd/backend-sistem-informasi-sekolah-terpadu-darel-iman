<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadarAkreditasi extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'radar_akreditasis';

    protected $fillable = [
        'unit_id',
        'kode_audit',
        'peringkat_saat_ini',
        'skor_akreditasi',
        'tanggal_sk',
        'habis_berlaku',
        'sisa_waktu',
        'kesiapan_total',
        'standar_evaluasi',
        'rekomendasi_divisi',
        'auditor_id',
    ];

    protected function casts(): array
    {
        return [
            'skor_akreditasi' => 'integer',
            'kesiapan_total' => 'integer',
            'standar_evaluasi' => 'array',
            'tanggal_sk' => 'date',
            'habis_berlaku' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
