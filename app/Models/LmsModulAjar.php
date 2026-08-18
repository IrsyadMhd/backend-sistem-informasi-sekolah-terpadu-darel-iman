<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsModulAjar extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_modul_ajar';

    protected $fillable = [
        'unit_pendidikan_id',
        'tahun_ajaran_id',
        'semester_id',
        'kurikulum_id',
        'mata_pelajaran_id',
        'guru_id',
        'kelas_id',
        'rombel_id',
        'cp_id',
        'tp_id',
        'kode_modul',
        'judul_modul',
        'fase',
        'semester',
        'alokasi_waktu_jp',
        'tujuan_pembelajaran',
        'profil_pelajar_pancasila',
        'target_peserta_didik',
        'model_pembelajaran',
        'metode_pembelajaran',
        'media_pembelajaran',
        'sumber_belajar',
        'kegiatan_pendahuluan',
        'kegiatan_inti',
        'kegiatan_penutup',
        'asesmen_awal',
        'asesmen_proses',
        'asesmen_akhir',
        'rencana_penilaian',
        'refleksi_guru',
        'lampiran',
        'status',
        'deskripsi',
        'versi',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'alokasi_waktu_jp' => 'integer',
            'lampiran' => 'array',
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
            if (empty($model->versi)) {
                $model->versi = '1.0';
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

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    public function semesterModel(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(MasterKurikulum::class, 'kurikulum_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'rombel_id');
    }

    public function capaianPembelajaran(): BelongsTo
    {
        return $this->belongsTo(CapaianPembelajaran::class, 'cp_id');
    }

    public function tujuanPembelajaran(): BelongsTo
    {
        return $this->belongsTo(TujuanPembelajaran::class, 'tp_id');
    }

    public function cps(): BelongsToMany
    {
        return $this->belongsToMany(CapaianPembelajaran::class, 'lms_modul_ajar_cp', 'modul_ajar_id', 'cp_id');
    }

    public function tps(): BelongsToMany
    {
        return $this->belongsToMany(TujuanPembelajaran::class, 'lms_modul_ajar_tp', 'modul_ajar_id', 'tp_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LmsModulAjarRevision::class, 'modul_ajar_id')->orderBy('created_at', 'desc');
    }

    public function materi(): HasMany
    {
        return $this->hasMany(LmsMateri::class, 'modul_ajar_id')->orderBy('urutan', 'asc');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(LmsPenugasan::class, 'modul_ajar_id');
    }

    public function referensi(): HasMany
    {
        return $this->hasMany(LmsReferensi::class, 'modul_ajar_id')->latest();
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(LmsAktivitasBelajar::class, 'modul_ajar_id')->orderBy('urutan', 'asc');
    }

    public function kisiKisi(): HasMany
    {
        return $this->hasMany(LmsKisiKisi::class, 'mata_pelajaran_id', 'mata_pelajaran_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getJudulAttribute(): ?string
    {
        return $this->attributes['judul_modul'] ?? null;
    }
}
