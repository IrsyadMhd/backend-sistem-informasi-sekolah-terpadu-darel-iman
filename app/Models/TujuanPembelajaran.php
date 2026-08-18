<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class TujuanPembelajaran extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_tujuan_pembelajaran';

    protected $fillable = [
        'cp_id',
        'kode_tp',
        'nama_tp',
        'deskripsi',
        'alokasi_waktu_jp',
        'urutan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $appends = [
        'deskripsi_tp',
    ];

    protected function casts(): array
    {
        return [
            'alokasi_waktu_jp' => 'integer',
            'urutan' => 'integer',
            'status' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Accessor for deskripsi_tp alias.
     */
    public function getDeskripsiTpAttribute(): ?string
    {
        return $this->attributes['deskripsi'] ?? $this->attributes['nama_tp'] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (empty($model->nama_tp) && ! empty($model->deskripsi)) {
                $model->nama_tp = substr($model->deskripsi, 0, 245);
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
            if (empty($model->nama_tp) && ! empty($model->deskripsi)) {
                $model->nama_tp = substr($model->deskripsi, 0, 245);
            }
        });

        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    public function capaianPembelajaran(): BelongsTo
    {
        return $this->belongsTo(CapaianPembelajaran::class, 'cp_id');
    }

    public function modulAjar(): HasMany
    {
        return $this->hasMany(LmsModulAjar::class, 'tp_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
