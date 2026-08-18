<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JenisUnitPendidikan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_jenis_unit_pendidikan';

    /**
     * Primary key adalah INTEGER (auto-increment bigint) bukan UUID.
     * Kolom `uuid` adalah secondary identifier yang digunakan sebagai FK public.
     * Jangan gunakan HasUuids trait karena akan mencoba generate UUID di kolom id.
     */
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    /**
     * Route model binding menggunakan `uuid` bukan `id` (integer)
     * agar URL tetap mengekspos UUID, bukan integer.
     */
    protected $routeKeyName = 'uuid';

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

    /**
     * Generate UUID otomatis untuk kolom `uuid` saat record baru dibuat.
     * Ini menggantikan HasUuids::uniqueIds() yang sebelumnya digunakan.
     */
    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
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
            $likeOp = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
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
