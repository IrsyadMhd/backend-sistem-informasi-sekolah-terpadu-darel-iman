<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model AcademicYear (Tahun Ajaran)
 * Mengelola data master tahun ajaran sekolah.
 */
class AcademicYear extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'academic_years';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AcademicYear $year) {
            if (empty($year->start_date)) {
                $year->start_date = now()->startOfYear()->toDateString();
            }
            if (empty($year->end_date)) {
                $year->end_date = now()->endOfYear()->toDateString();
            }
        });
    }

    /**
     * Relasi ke Semester
     */
    public function semesters()
    {
        return $this->hasMany(Semester::class, 'academic_year_id');
    }

    /**
     * Relasi ke Kelas
     */
    public function classes()
    {
        return $this->hasMany(Kelas::class, 'tahun_ajaran_id');
    }
}
