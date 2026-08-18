<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsJawabanSiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lms_jawaban_siswa';

    protected $fillable = [
        'sesi_id',
        'soal_id',
        'jawaban_dipilih',
        'jawaban_esai',
        'is_correct',
        'poin_didapat',
        'waktu_jawab_detik',
        'catatan_guru',
        'dinilai_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'poin_didapat' => 'float',
            'waktu_jawab_detik' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sesi(): BelongsTo
    {
        return $this->belongsTo(LmsUjianSesi::class, 'sesi_id');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(LmsBankSoal::class, 'soal_id');
    }
}
