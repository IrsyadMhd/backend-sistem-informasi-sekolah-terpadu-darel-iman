<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Subject extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'subjects';

    protected $fillable = [
        'unit_pendidikan_id',
        'kurikulum_id',
        'kode_mapel',
        'nama_mapel',
        'nama_singkat',
        'code',
        'name',
        'kelompok_mapel',
        'kategori',
        'jenjang',
        'tingkat_kelas',
        'jam_pelajaran',
        'guru_pengampu_id',
        'kkm',
        'bobot_pengetahuan',
        'bobot_keterampilan',
        'bobot_sikap',
        'bobot_nilai',
        'warna',
        'ikon',
        'urutan_tampil',
        'status',
        'deskripsi',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'kkm' => 'float',
            'jam_pelajaran' => 'integer',
            'urutan_tampil' => 'integer',
            'bobot_pengetahuan' => 'integer',
            'bobot_keterampilan' => 'integer',
            'bobot_sikap' => 'integer',
            'bobot_nilai' => 'array',
            'metadata' => 'array',
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
            if (empty($model->kode_mapel) && ! empty($model->code)) {
                $model->kode_mapel = $model->code;
            }
            if (empty($model->nama_mapel) && ! empty($model->name)) {
                $model->nama_mapel = $model->name;
            }
            if (empty($model->code) && ! empty($model->kode_mapel)) {
                $model->code = $model->kode_mapel;
            }
            if (empty($model->name) && ! empty($model->nama_mapel)) {
                $model->name = $model->nama_mapel;
            }
            if (empty($model->deskripsi) && ! empty($model->description)) {
                $model->deskripsi = $model->description;
            }
            if (empty($model->description) && ! empty($model->deskripsi)) {
                $model->description = $model->deskripsi;
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
            if (! empty($model->kode_mapel)) {
                $model->code = $model->kode_mapel;
            }
            if (! empty($model->nama_mapel)) {
                $model->name = $model->nama_mapel;
            }
            if (! empty($model->deskripsi)) {
                $model->description = $model->deskripsi;
            }
            if (! empty($model->description)) {
                $model->deskripsi = $model->description;
            }
        });

        static::deleting(function ($model) {
            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    // --- Accessors Backward Compatibility ---

    public function getKodeDisplayAttribute(): string
    {
        return $this->kode_mapel ?? $this->code ?? '-';
    }

    public function getNamaDisplayAttribute(): string
    {
        return $this->nama_mapel ?? $this->name ?? '-';
    }

    // --- Relationships ---

    public function unitPendidikan(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(MasterKurikulum::class, 'kurikulum_id');
    }

    public function guruPengampu(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_pengampu_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'subject_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(Employee::class, 'subject_teachers', 'subject_id', 'guru_id')->withTimestamps();
    }

    public function classes()
    {
        return $this->belongsToMany(Kelas::class, 'subject_classes', 'subject_id', 'kelas_id')->withTimestamps();
    }

    public function rombel()
    {
        return $this->belongsToMany(Kelas::class, 'subject_rombel', 'subject_id', 'rombel_id')->withTimestamps();
    }

    public function capaianPembelajaran(): HasMany
    {
        return $this->hasMany(CapaianPembelajaran::class, 'mata_pelajaran_id');
    }

    public function tujuanPembelajaran(): HasManyThrough
    {
        return $this->hasManyThrough(
            TujuanPembelajaran::class,
            CapaianPembelajaran::class,
            'mata_pelajaran_id',
            'cp_id',
            'id',
            'id'
        );
    }

    public function modulAjar(): HasMany
    {
        return $this->hasMany(LmsModulAjar::class, 'mata_pelajaran_id');
    }

    public function materi(): HasMany
    {
        return $this->hasMany(LmsMateri::class, 'mata_pelajaran_id');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(LmsPenugasan::class, 'mata_pelajaran_id');
    }

    public function kisiKisi(): HasMany
    {
        return $this->hasMany(LmsKisiKisi::class, 'mata_pelajaran_id');
    }

    public function bankSoal(): HasMany
    {
        return $this->hasMany(LmsBankSoal::class, 'mata_pelajaran_id');
    }

    public function cbt(): HasManyThrough
    {
        return $this->hasManyThrough(
            LmsUjian::class,
            LmsKisiKisi::class,
            'mata_pelajaran_id',
            'kisi_kisi_id',
            'id',
            'id'
        );
    }

    public function penilaian(): HasMany
    {
        return $this->hasMany(StudentGrade::class, 'subject_id');
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

    // --- Scopes ---

    public function scopeFilter($query, array $filters)
    {
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->when($filters['search'] ?? null, function ($q, $search) use ($likeOp) {
            $q->where(function ($sub) use ($search, $likeOp) {
                $sub->where('kode_mapel', $likeOp, "%{$search}%")
                    ->orWhere('code', $likeOp, "%{$search}%")
                    ->orWhere('nama_mapel', $likeOp, "%{$search}%")
                    ->orWhere('name', $likeOp, "%{$search}%")
                    ->orWhere('deskripsi', $likeOp, "%{$search}%")
                    ->orWhere('description', $likeOp, "%{$search}%");
            });
        });

        $query->when($filters['unit_pendidikan_id'] ?? null, function ($q, $unitId) {
            if ($unitId !== '' && $unitId !== 'semua') {
                $q->where('unit_pendidikan_id', $unitId);
            }
        });

        $query->when($filters['kurikulum_id'] ?? null, function ($q, $kurId) {
            if ($kurId !== '' && $kurId !== 'semua') {
                $q->where('kurikulum_id', $kurId);
            }
        });

        $query->when($filters['kelompok_mapel'] ?? null, function ($q, $kel) {
            if ($kel !== '' && $kel !== 'semua') {
                $q->where('kelompok_mapel', $kel);
            }
        });

        $query->when($filters['kategori'] ?? null, function ($q, $kat) {
            if ($kat !== '' && $kat !== 'semua') {
                $q->where('kategori', $kat);
            }
        });

        $query->when($filters['jenjang'] ?? null, function ($q, $jnj) {
            if ($jnj !== '' && $jnj !== 'semua') {
                $q->where('jenjang', $jnj);
            }
        });

        $query->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'semua', function ($q) use ($filters) {
            if ($filters['status'] === 'aktif' || $filters['status'] === 'true' || $filters['status'] === '1') {
                $q->where('status', true);
            } elseif ($filters['status'] === 'tidak_aktif' || $filters['status'] === 'false' || $filters['status'] === '0') {
                $q->where('status', false);
            }
        });

        $query->when(isset($filters['dengan_sampah']) && ($filters['dengan_sampah'] === 'true' || $filters['dengan_sampah'] === '1'), function ($q) {
            $q->withTrashed();
        });
    }
}
