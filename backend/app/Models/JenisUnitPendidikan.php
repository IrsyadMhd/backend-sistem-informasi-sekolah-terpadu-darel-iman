<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class JenisUnitPendidikan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'master_jenis_unit_pendidikan';

    protected $fillable = [
        'uuid',
        'kode_jenis',
        'nama_jenis',
        'singkatan',
        'jenjang',
        'warna_badge',
        'icon',
        'urutan',
        'keterangan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Relasi ke Unit Pendidikan (EducationUnit / Master Unit Pendidikan)
     */
    public function unitPendidikan()
    {
        return $this->hasMany(EducationUnit::class, 'jenis_unit_id', 'uuid');
    }

    /**
     * User Pembuat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User Pengubah
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User Penghapus
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Scope Filter Data
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $likeOp = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $q->where(function ($sub) use ($search, $likeOp) {
                $sub->where('kode_jenis', $likeOp, "%{$search}%")
                    ->orWhere('nama_jenis', $likeOp, "%{$search}%")
                    ->orWhere('singkatan', $likeOp, "%{$search}%")
                    ->orWhere('keterangan', $likeOp, "%{$search}%");
            });
        });

        $query->when(isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'semua', function ($q) use ($filters) {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($status !== null) {
                $q->where('status', $status);
            } elseif ($filters['status'] === 'aktif' || $filters['status'] === 'true' || $filters['status'] === '1') {
                $q->where('status', true);
            } elseif ($filters['status'] === 'tidak_aktif' || $filters['status'] === 'false' || $filters['status'] === '0') {
                $q->where('status', false);
            }
        });

        $query->when($filters['jenjang'] ?? null, function ($q, $jenjang) {
            if ($jenjang !== '' && $jenjang !== 'semua') {
                $q->where('jenjang', $jenjang);
            }
        });

        $query->when(isset($filters['dengan_sampah']) && $filters['dengan_sampah'] === 'true', function ($q) {
            $q->withTrashed();
        });
    }
}
