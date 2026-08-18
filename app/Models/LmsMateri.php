<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsMateri extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_materi';

    protected $fillable = [
        'modul_ajar_id',
        'mata_pelajaran_id',
        'guru_id',
        'judul',
        'tipe',
        'isi',
        'file',
        'video',
        'link',
        'konten',
        'tipe_materi',
        'urutan',
        'status',
        'is_published',
        'tanggal_publish',
        'catatan',
        'created_by',
        'updated_by',
        'deleted_by',
        'subject_id',
        'teacher_id',
        'ringkasan',
        'bobot',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_published' => 'boolean',
            'tanggal_publish' => 'datetime',
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
            if (empty($model->tipe)) {
                $model->tipe = $model->tipe_materi ?? 'teks';
            }
            if (empty($model->isi) && ! empty($model->konten)) {
                $model->isi = $model->konten;
            }
            if (empty($model->konten) && ! empty($model->isi)) {
                $model->konten = $model->isi;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
            if (empty($model->konten) && ! empty($model->isi)) {
                $model->konten = $model->isi;
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

    public function setTeacherIdAttribute($value): void
    {
        $this->attributes['guru_id'] = $value;
    }

    public function getTeacherIdAttribute()
    {
        return $this->attributes['guru_id'] ?? null;
    }

    public function setSubjectIdAttribute($value): void
    {
        $this->attributes['mata_pelajaran_id'] = $value;
    }

    public function getSubjectIdAttribute()
    {
        return $this->attributes['mata_pelajaran_id'] ?? null;
    }

    public function setRingkasanAttribute($value): void
    {
        $this->attributes['catatan'] = $value;
    }

    public function getRingkasanAttribute()
    {
        return $this->attributes['catatan'] ?? null;
    }

    public function setIsiAttribute($value): void
    {
        $this->attributes['konten'] = $value;
    }

    public function getIsiAttribute()
    {
        return $this->attributes['konten'] ?? null;
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['is_published'] = in_array($value, ['published', 'dipublikasikan', '1', 1, true], true);
    }

    public function getStatusAttribute(): string
    {
        return ! empty($this->attributes['is_published']) ? 'published' : 'draft';
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(LmsMedia::class, 'materi_id')->orderBy('urutan', 'asc');
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
