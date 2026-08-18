<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CapaianPembelajaran extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_capaian_pembelajaran';

    protected $fillable = [
        'unit_pendidikan_id',
        'tahun_ajaran_id',
        'kurikulum_id',
        'mata_pelajaran_id',
        'kode_cp',
        'nama_cp',
        'deskripsi',
        'fase',
        'kelas_target',
        'urutan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
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
            // Guard: Cegah penghapusan CP yang masih memiliki Tujuan Pembelajaran aktif.
            // Ini berlaku sebagai perlindungan aplikasi selain FK RESTRICT di database.
            // Jika soft delete (bukan force delete), izinkan — TP turunan tetap aktif tapi CP terhapus soft.
            if (! $model->isForceDeleting()) {
                // Soft delete diizinkan — set deleted_by saja
                if (Auth::check()) {
                    $model->deleted_by = Auth::id();
                    $model->saveQuietly();
                }

                return; // Lanjutkan soft delete
            }

            // Force delete: cek apakah ada TP yang mengacu ke CP ini (termasuk yang sudah soft deleted)
            $hasTP = TujuanPembelajaran::withTrashed()
                ->where('cp_id', $model->id)
                ->exists();

            if ($hasTP) {
                throw new \RuntimeException(
                    "Capaian Pembelajaran '{$model->kode_cp}' tidak dapat dihapus permanen " .
                    'karena masih memiliki Tujuan Pembelajaran yang merujuk padanya. ' .
                    'Hapus semua TP terlebih dahulu.'
                );
            }

            if (Auth::check()) {
                $model->deleted_by = Auth::id();
                $model->saveQuietly();
            }
        });
    }

    public function unitPendidikan(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(MasterKurikulum::class, 'kurikulum_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'mata_pelajaran_id');
    }

    public function tujuanPembelajaran(): HasMany
    {
        return $this->hasMany(TujuanPembelajaran::class, 'cp_id')->orderBy('urutan', 'asc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->when($filters['search'] ?? null, function ($q, $search) use ($likeOp) {
            $q->where(function ($sub) use ($search, $likeOp) {
                $sub->where('kode_cp', $likeOp, "%{$search}%")
                    ->orWhere('nama_cp', $likeOp, "%{$search}%")
                    ->orWhere('deskripsi', $likeOp, "%{$search}%");
            });
        });

        $query->when($filters['unit_pendidikan_id'] ?? null, function ($q, $unitId) {
            if ($unitId !== '' && $unitId !== 'semua' && Schema::hasColumn('lms_capaian_pembelajaran', 'unit_pendidikan_id')) {
                $q->where('unit_pendidikan_id', $unitId);
            }
        });

        $query->when($filters['tahun_ajaran_id'] ?? null, function ($q, $tahunId) {
            if ($tahunId !== '' && $tahunId !== 'semua' && Schema::hasColumn('lms_capaian_pembelajaran', 'tahun_ajaran_id')) {
                $q->where('tahun_ajaran_id', $tahunId);
            }
        });

        $query->when($filters['kurikulum_id'] ?? null, function ($q, $kurId) {
            if ($kurId !== '' && $kurId !== 'semua') {
                $q->where('kurikulum_id', $kurId);
            }
        });

        $query->when($filters['mata_pelajaran_id'] ?? null, function ($q, $mapelId) {
            if ($mapelId !== '' && $mapelId !== 'semua') {
                $q->where('mata_pelajaran_id', $mapelId);
            }
        });

        $query->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'semua', function ($q) use ($filters) {
            if ($filters['status'] === 'aktif' || $filters['status'] === 'true' || $filters['status'] === '1') {
                $q->where('status', true);
            } elseif ($filters['status'] === 'tidak_aktif' || $filters['status'] === 'false' || $filters['status'] === '0') {
                $q->where('status', false);
            }
        });
    }
}
