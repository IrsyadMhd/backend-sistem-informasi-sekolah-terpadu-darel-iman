<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsDiskusiKomentar extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_diskusi_komentar';

    protected $fillable = [
        'diskusi_id',
        'parent_id',
        'user_id',
        'peran_pengirim',
        'konten',
        'is_solution',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_solution' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                if (empty($model->user_id)) {
                    $model->user_id = Auth::id();
                }
                if (empty($model->created_by)) {
                    $model->created_by = Auth::id();
                }
            }
            if (empty($model->peran_pengirim)) {
                $model->peran_pengirim = 'Guru';
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

    public function diskusi(): BelongsTo
    {
        return $this->belongsTo(LmsDiskusi::class, 'diskusi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LmsDiskusiKomentar::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(LmsDiskusiKomentar::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
