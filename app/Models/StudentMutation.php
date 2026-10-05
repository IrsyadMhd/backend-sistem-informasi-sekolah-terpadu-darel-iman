<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentMutation extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'student_mutations';

    protected $fillable = [
        'student_id',
        'unit_asal_id',
        'unit_tujuan_id',
        'kelas_asal_id',
        'kelas_tujuan_id',
        'jenis_mutasi',
        'sekolah_tujuan',
        'sekolah_asal',
        'alasan',
        'catatan_penolakan',
        'status',
        'diajukan_oleh',
        'diajukan_pada',
        'disetujui_oleh',
        'disetujui_pada',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'diajukan_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function unitAsal(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_asal_id');
    }

    public function unitTujuan(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_tujuan_id');
    }

    public function kelasAsal(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_asal_id');
    }

    public function kelasTujuan(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_tujuan_id');
    }

    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'menunggu_persetujuan');
    }

    public function scopeForUnitTujuan($query, string $unitId)
    {
        return $query->where('unit_tujuan_id', $unitId);
    }

    public function scopeForUnitAsal($query, string $unitId)
    {
        return $query->where('unit_asal_id', $unitId);
    }
}
