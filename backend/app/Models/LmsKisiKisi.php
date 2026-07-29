<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsKisiKisi extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_kisi_kisi';

    protected $fillable = [
        'kurikulum_id',
        'mata_pelajaran_id',
        'cp_id',
        'tp_id',
        'kelas_id',
        'semester_id',
        'tahun_ajaran_id',
        'guru_id',
        'judul_kisi',
        'jenis_ujian',
        'jumlah_soal',
        'alokasi_waktu_menit',
        'kompetensi_dasar',
        'level_kognitif',
        'distribusi_bobot',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_soal' => 'integer',
            'alokasi_waktu_menit' => 'integer',
            'distribusi_bobot' => 'array',
            'status' => 'boolean',
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

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(MasterKurikulum::class, 'kurikulum_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function cp(): BelongsTo
    {
        return $this->belongsTo(CapaianPembelajaran::class, 'cp_id');
    }

    public function tp(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tp_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function bankSoal(): HasMany
    {
        return $this->hasMany(LmsBankSoal::class, 'kisi_kisi_id');
    }

    public function ujian(): HasMany
    {
        return $this->hasMany(LmsUjian::class, 'kisi_kisi_id');
    }
}
