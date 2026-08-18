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
            'waktu_kumpul' => 'datetime',
            'waktu_dinilai' => 'datetime',
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
    }

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(LmsPenugasan::class, 'penugasan_id');
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
