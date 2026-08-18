<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsAktivitasBelajar extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_aktivitas_belajar';

    protected $fillable = [
        'modul_ajar_id',
        'nama_aktivitas',
        'jenis_aktivitas',
        'instruksi',
        'waktu',
        'urutan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'waktu' => 'integer',
            'urutan' => 'integer',
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
            if (empty($model->status)) {
                $model->status = 'aktif';
            }
            if (empty($model->jenis_aktivitas)) {
                $model->jenis_aktivitas = 'Inti';
            }
            if (empty($model->waktu)) {
                $model->waktu = 15;
            }
            if (empty($model->urutan)) {
                $model->urutan = 1;
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

    public function modulAjar(): BelongsTo
    {
        return $this->belongsTo(LmsModulAjar::class, 'modul_ajar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
