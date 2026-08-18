<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsDiskusi extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_diskusi';

    protected $fillable = [
        'modul_ajar_id',
        'judul',
        'deskripsi',
        'kategori',
        'tanggal_mulai',
        'tanggal_tutup',
        'is_pinned',
        'is_closed',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_closed' => 'boolean',
            'tanggal_mulai' => 'datetime',
            'tanggal_tutup' => 'datetime',
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
            if (empty($model->kategori)) {
                $model->kategori = 'Umum';
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

    public function komentar(): HasMany
    {
        return $this->hasMany(LmsDiskusiKomentar::class, 'diskusi_id')->whereNull('parent_id')->orderBy('created_at', 'asc');
    }

    public function semuaKomentar(): HasMany
    {
        return $this->hasMany(LmsDiskusiKomentar::class, 'diskusi_id');
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
