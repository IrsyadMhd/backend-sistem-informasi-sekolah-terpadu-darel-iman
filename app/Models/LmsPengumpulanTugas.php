<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsPengumpulanTugas extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_pengumpulan_tugas';

    protected $fillable = [
        'penugasan_id',
        'siswa_id',
        'jawaban_teks',
        'file_path',
        'url_link',
        'metadata',
        'status',
        'waktu_kumpul',
        'nilai_guru',
        'catatan_guru',
        'waktu_dinilai',
        'dinilai_oleh',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'nilai_guru' => 'float',
            'metadata' => 'array',
            'waktu_kumpul' => 'datetime',
            'waktu_dinilai' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected $appends = [
        'file_url',
    ];

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }
        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }
        return url('storage/' . ltrim($this->file_path, '/'));
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
    }

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(LmsPenugasan::class, 'penugasan_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'siswa_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'siswa_id');
    }

    public function penilai(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'dinilai_oleh');
    }
}
