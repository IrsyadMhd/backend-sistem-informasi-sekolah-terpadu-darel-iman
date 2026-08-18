<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsPenugasan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_penugasan';

    protected $fillable = [
        'mata_pelajaran_id',
        'kelas_id',
        'guru_id',
        'semester_id',
        'tahun_ajaran_id',
        'modul_ajar_id',
        'judul_tugas',
        'deskripsi',
        'instruksi',
        'tipe_tugas',
        'jenis_tugas',
        'nilai_maksimal',
        'bobot_persen',
        'tanggal_mulai',
        'deadline',
        'izin_kumpul_terlambat',
        'is_published',
        'file_lampiran',
        'created_by',
        'updated_by',
        'deleted_by',
        'subject_id',
        'teacher_id',
        'judul',
        'bobot',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'nilai_maksimal' => 'float',
            'bobot_persen' => 'float',
            'izin_kumpul_terlambat' => 'boolean',
            'is_published' => 'boolean',
            'tanggal_mulai' => 'datetime',
            'deadline' => 'datetime',
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

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(LmsPengumpulanTugas::class, 'penugasan_id');
    }

    public function pengumpulanTugas(): HasMany
    {
        return $this->pengumpulan();
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

    public function setBobotAttribute($value): void
    {
        $this->attributes['bobot_persen'] = $value;
    }

    public function getBobotAttribute()
    {
        return $this->attributes['bobot_persen'] ?? null;
    }

    public function modulAjar(): BelongsTo
    {
        return $this->belongsTo(LmsModulAjar::class, 'modul_ajar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessors & Mutators for User Requested Field Aliases
    public function getJudulAttribute(): ?string
    {
        return $this->attributes['judul_tugas'] ?? null;
    }

    public function setJudulAttribute(?string $value): void
    {
        $this->attributes['judul_tugas'] = $value;
    }

    public function getTipeAttribute(): ?string
    {
        return $this->attributes['tipe_tugas'] ?? null;
    }

    public function setTipeAttribute(?string $value): void
    {
        $this->attributes['tipe_tugas'] = $value;
    }

    public function getTanggalSelesaiAttribute(): mixed
    {
        return $this->deadline;
    }

    public function setTanggalSelesaiAttribute(mixed $value): void
    {
        $this->attributes['deadline'] = $value;
    }

    public function getLampiranAttribute(): ?string
    {
        return $this->attributes['file_lampiran'] ?? null;
    }

    public function setLampiranAttribute(?string $value): void
    {
        $this->attributes['file_lampiran'] = $value;
    }

    public function getStatusAttribute(): string
    {
        return $this->is_published ? 'dipublikasikan' : 'draft';
    }

    public function setStatusAttribute(?string $value): void
    {
        if ($value === 'dipublikasikan' || $value === 'published' || $value === '1' || $value === 1 || $value === true) {
            $this->attributes['is_published'] = true;
        } else {
            $this->attributes['is_published'] = false;
        }
    }
}
