<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Model Kelas / Rombongan Belajar (Rombel)
 * Mengelola data kelas, wali kelas, kapasitas, serta audit logging.
 */
class Kelas extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'tbl_kelas';

    protected $fillable = [
        'yayasan_id',
        'unit_pendidikan_id',
        'tahun_ajaran_id',
        'semester_id',
        'jenjang',
        'tingkat',
        'kode_kelas',
        'nama_kelas',
        'wali_kelas_id',
        'kapasitas',
        'ruangan',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Event booted model untuk otomatis mencatat Audit Log (created_by, updated_by, deleted_by).
     */
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

    /**
     * Relasi ke Unit Pendidikan
     */
    public function unitPendidikan()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    /**
     * Relasi ke Tahun Ajaran
     */
    public function tahunAjaran()
    {
        return $this->belongsTo(AcademicYear::class, 'tahun_ajaran_id');
    }

    /**
     * Relasi ke Semester
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Relasi ke Wali Kelas (diambil dari data Employee / Guru)
     */
    public function waliKelas()
    {
        return $this->belongsTo(Employee::class, 'wali_kelas_id');
    }

    /**
     * Relasi ke Data Siswa dalam Rombel
     */
    public function siswa()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    /**
     * Relasi Pengguna Pembuat Data (Audit Log)
     */
    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi Pengguna Pengubah Data (Audit Log)
     */
    public function pengubah()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope filter kelas aktif
     */
    public function scopeAktif($query)
    {
        return $query->where('status', 'Aktif');
    }
}
