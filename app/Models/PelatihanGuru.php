<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PelatihanGuru extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'pelatihan_gurus';

    protected $fillable = [
        'kode_pelatihan',
        'nama',
        'kategori',
        'narasumber',
        'jam_pelatihan',
        'tanggal_mulai',
        'tanggal_selesai',
        'lokasi',
        'kuota',
        'jumlah_terdaftar',
        'status',
        'deskripsi',
        'penyelenggara',
        'target_unit_id',
        'penanggung_jawab_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date:Y-m-d',
            'tanggal_selesai' => 'date:Y-m-d',
            'jam_pelatihan' => 'integer',
            'kuota' => 'integer',
            'jumlah_terdaftar' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function targetUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'target_unit_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penanggung_jawab_user_id');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(PelatihanGuruPeserta::class, 'pelatihan_id');
    }
}
