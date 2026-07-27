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
