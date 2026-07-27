<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'attendances';

    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'month',
        'attendance_date',
        'student_id',
        'class_id',
        'check_in_time',
        'check_out_time',
        'status',
        'attendance_method',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'month' => 'integer',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('status', 'HADIR');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'ALPA');
    }

    public function scopeByDate($query, string $date)
    {
        return $query->where('attendance_date', $date);
    }

    public function scopeByClass($query, string $classId)
    {
        return $query->where('class_id', $classId);
    }
}
