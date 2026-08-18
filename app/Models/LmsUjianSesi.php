<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LmsUjianSesi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lms_ujian_sesi';

    protected $fillable = [
        'ujian_id',
        'siswa_id',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_aktual_detik',
        'jumlah_benar',
        'jumlah_salah',
        'jumlah_kosong',
        'nilai_raw',
        'nilai_final',
        'status',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'durasi_aktual_detik' => 'integer',
            'jumlah_benar' => 'integer',
            'jumlah_salah' => 'integer',
            'jumlah_kosong' => 'integer',
            'nilai_raw' => 'float',
            'nilai_final' => 'float',
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(LmsUjian::class, 'ujian_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'siswa_id');
    }

    public function jawaban(): HasMany
    {
        return $this->hasMany(LmsJawabanSiswa::class, 'sesi_id');
    }
}
