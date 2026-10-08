<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAttendancePermission extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = ['student_id', 'academic_year_id', 'semester_id', 'class_id', 'start_date', 'end_date', 'type', 'reason', 'attachment_path', 'notes', 'status', 'submitted_at', 'review_notes', 'reviewed_by', 'reviewed_at', 'created_by', 'updated_by'];

    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
