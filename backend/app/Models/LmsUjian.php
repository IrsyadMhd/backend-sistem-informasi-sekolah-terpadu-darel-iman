<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsUjian extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_ujian';

    protected $fillable = [
        'kisi_kisi_id',
        'kelas_id',
        'semester_id',
        'guru_id',
        'judul_ujian',
        'instruksi',
        'waktu_mulai',
        'waktu_selesai',
        'durasi_menit',
        'acak_soal',
        'acak_jawaban',
        'tampilkan_nilai_langsung',
        'nilai_kkm',
        'max_attempt',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'durasi_menit' => 'integer',
            'max_attempt' => 'integer',
            'acak_soal' => 'boolean',
            'acak_jawaban' => 'boolean',
            'tampilkan_nilai_langsung' => 'boolean',
            'nilai_kkm' => 'float',
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    public function kisiKisi(): BelongsTo
    {
        return $this->belongsTo(LmsKisiKisi::class, 'kisi_kisi_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function sesi(): HasMany
    {
        return $this->hasMany(LmsUjianSesi::class, 'ujian_id');
    }
}
