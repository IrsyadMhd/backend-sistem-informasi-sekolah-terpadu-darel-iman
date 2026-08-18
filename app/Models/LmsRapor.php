<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LmsRapor extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'lms_rapor';

    protected $fillable = [
        'siswa_id',
        'kelas_id',
        'semester_id',
        'tahun_ajaran_id',
        'guru_wali_id',
        'total_nilai',
        'rata_rata',
        'peringkat_kelas',
        'total_siswa_kelas',
        'total_mapel',
        'mapel_lulus',
        'mapel_tidak_lulus',
        'total_hari_efektif',
        'total_hadir',
        'total_izin',
        'total_sakit',
        'total_alpha',
        'catatan_wali_kelas',
        'catatan_kepala_sekolah',
        'status_rapor',
        'tanggal_terbit',
        'sudah_dilihat_ortu',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'total_nilai' => 'float',
            'rata_rata' => 'float',
            'peringkat_kelas' => 'integer',
            'total_siswa_kelas' => 'integer',
            'total_mapel' => 'integer',
            'mapel_lulus' => 'integer',
            'mapel_tidak_lulus' => 'integer',
            'total_hari_efektif' => 'integer',
            'total_hadir' => 'integer',
            'total_izin' => 'integer',
            'total_sakit' => 'integer',
            'total_alpha' => 'integer',
            'sudah_dilihat_ortu' => 'boolean',
            'tanggal_terbit' => 'date',
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
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'siswa_id');
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

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'guru_wali_id');
    }
}
