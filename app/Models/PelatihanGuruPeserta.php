<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PelatihanGuruPeserta extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'pelatihan_guru_pesertas';

    protected $fillable = [
        'pelatihan_id',
        'employee_id',
        'education_unit_id',
        'status_kehadiran',
        'nilai_evaluasi',
        'nomor_sertifikat',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai_evaluasi' => 'integer',
        ];
    }

    public function pelatihan(): BelongsTo
    {
        return $this->belongsTo(PelatihanGuru::class, 'pelatihan_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }
}
