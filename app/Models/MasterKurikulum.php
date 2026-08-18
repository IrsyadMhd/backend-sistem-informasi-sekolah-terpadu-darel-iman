<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MasterKurikulum extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'master_kurikulum';

    protected $fillable = [
        'kode_kurikulum',
        'nama_kurikulum',
        'jenis_kurikulum',
        'unit_pendidikan_id',
        'jenjang',
        'tahun_ajaran_id',
        'semester_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'deskripsi',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke Unit Pendidikan (EducationUnit)
     */
    public function unitPendidikan(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    /**
     * Relasi ke Tahun Ajaran (AcademicYear)
     */
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    /**
     * Relasi ke Semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Relasi ke Mata Pelajaran (Subjects)
     */
    public function subjects()
    {
        return $this->hasMany(Subject::class, 'kurikulum_id');
    }

    /**
     * User Pembuat (Audit Log)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User Pengubah (Audit Log)
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User Penghapus (Audit Log)
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Scope Filter Data
     */
    public function scopeFilter($query, array $filters)
    {
        $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $query->when($filters['search'] ?? null, function ($q, $search) use ($likeOp) {
            $q->where(function ($sub) use ($search, $likeOp) {
                $sub->where('kode_kurikulum', $likeOp, "%{$search}%")
                    ->orWhere('nama_kurikulum', $likeOp, "%{$search}%")
                    ->orWhere('deskripsi', $likeOp, "%{$search}%");
            });
        });

        $query->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'semua', function ($q) use ($filters) {
            if ($filters['status'] === 'aktif' || $filters['status'] === 'true' || $filters['status'] === '1') {
                $q->where('status', true);
            } elseif ($filters['status'] === 'tidak_aktif' || $filters['status'] === 'false' || $filters['status'] === '0') {
                $q->where('status', false);
            }
        });

        $query->when($filters['jenis_kurikulum'] ?? null, function ($q, $jenis) {
            if ($jenis !== '' && $jenis !== 'semua') {
                $q->where('jenis_kurikulum', $jenis);
            }
        });

        $query->when($filters['jenjang'] ?? null, function ($q, $jenjang) {
            if ($jenjang !== '' && $jenjang !== 'semua') {
                $q->where('jenjang', $jenjang);
            }
        });

        $query->when($filters['unit_pendidikan_id'] ?? null, function ($q, $unitId) {
            if ($unitId !== '' && $unitId !== 'semua') {
                $q->where('unit_pendidikan_id', $unitId);
            }
        });

        $query->when($filters['tahun_ajaran_id'] ?? null, function ($q, $tahunId) {
            if ($tahunId !== '' && $tahunId !== 'semua') {
                $q->where('tahun_ajaran_id', $tahunId);
            }
        });

        $query->when(isset($filters['dengan_sampah']) && ($filters['dengan_sampah'] === 'true' || $filters['dengan_sampah'] === '1'), function ($q) {
            $q->withTrashed();
        });
    }
}
