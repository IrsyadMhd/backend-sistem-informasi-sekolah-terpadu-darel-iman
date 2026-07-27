<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TahfizhRecord extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'memorization_deposits';

    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'class_id',
        'student_id',
        'teacher_id',
        'surah_name',
        'ayah_start',
        'ayah_end',
        'line_count',
        'deposit_date',
        'notes',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'ayah_start' => 'integer',
            'ayah_end' => 'integer',
            'line_count' => 'integer',
            'deposit_date' => 'date',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    // Scopes
    public function scopeLancar($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeByStudent($query, string $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
