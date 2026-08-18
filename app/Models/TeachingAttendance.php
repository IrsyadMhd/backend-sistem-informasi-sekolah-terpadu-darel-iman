<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingAttendance extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'teaching_attendances';

    protected $fillable = [
        'schedule_id',
        'employee_id',
        'education_unit_id',
        'academic_year_id',
        'semester_id',
        'attendance_date',
        'check_in_at',
        'status',
        'attendance_method',
        'qr_credential_id',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function qrCredential()
    {
        return $this->belongsTo(QrCredential::class, 'qr_credential_id');
    }

    public function session()
    {
        return $this->hasOne(LessonAttendanceSession::class, 'teaching_attendance_id');
    }
}
