<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Semester
 * Mengelola data semester (Ganjil/Genap) pada tahun ajaran aktif.
 */
class Semester extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'semesters';

    protected $fillable = [
        'academic_year_id',
        'name',
        'sequence',
        'start_date',
        'end_date',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sequence' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Alias kompatibilitas untuk modul LMS yang masih memakai nama field lama.
     */
    public function getNamaSemesterAttribute(): ?string
    {
        return $this->name;
    }

    public function getTipeSemesterAttribute(): string
    {
        return $this->sequence === 2 ? 'Genap' : 'Ganjil';
    }

    protected static function booted(): void
    {
        static::creating(function (Semester $semester) {
            if (empty($semester->sequence)) {
                $semester->sequence = str_contains(strtolower($semester->name ?? ''), 'genap') ? 2 : 1;
            }
            if (empty($semester->start_date)) {
                $semester->start_date = now()->startOfYear()->toDateString();
            }
            if (empty($semester->end_date)) {
                $semester->end_date = now()->endOfYear()->toDateString();
            }
        });
    }

    /**
     * Relasi ke Tahun Ajaran
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /**
     * Relasi ke Kelas
     */
    public function classes()
    {
        return $this->hasMany(Kelas::class, 'semester_id');
    }
}
