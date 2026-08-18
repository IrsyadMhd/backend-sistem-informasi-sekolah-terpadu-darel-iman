<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class AttendanceScanLog extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['lesson_attendance_id', 'student_id', 'class_schedule_id', 'scan_method', 'raw_identifier', 'hashed_identifier', 'device_id', 'scanned_at', 'result_status', 'failure_reason', 'confidence_score', 'request_ip', 'user_agent', 'metadata', 'created_by'];

    protected $hidden = ['raw_identifier', 'hashed_identifier'];

    protected $casts = ['scanned_at' => 'datetime', 'confidence_score' => 'decimal:2', 'metadata' => 'array'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function session()
    {
        return $this->belongsTo(LessonAttendanceSession::class, 'lesson_attendance_id');
    }

    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class);
    }
}
