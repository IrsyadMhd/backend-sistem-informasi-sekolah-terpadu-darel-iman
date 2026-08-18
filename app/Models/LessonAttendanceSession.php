<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonAttendanceSession extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'schedule_id', 'attendance_date', 'meeting_number', 'learning_module_id',
        'learning_material_id', 'learning_activity_id', 'learning_material',
        'learning_activity', 'topic', 'meeting_notes', 'status',
        'finalized_at', 'finalized_by', 'locked_at', 'created_by', 'updated_by',
        'attendance_method', 'session_token_hash', 'session_started_at',
        'session_expires_at', 'session_closed_at', 'device_id', 'scan_location', 'metadata',
        'teaching_attendance_id', 'teaching_session_status',
    ];

    protected $casts = ['attendance_date' => 'date:Y-m-d', 'finalized_at' => 'datetime', 'locked_at' => 'datetime', 'session_started_at' => 'datetime', 'session_expires_at' => 'datetime', 'session_closed_at' => 'datetime', 'metadata' => 'array'];

    public function schedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function attendances()
    {
        return $this->hasMany(LmsPresensi::class, 'session_id');
    }

    public function scanLogs()
    {
        return $this->hasMany(AttendanceScanLog::class, 'lesson_attendance_id');
    }

    public function teachingAttendance()
    {
        return $this->belongsTo(TeachingAttendance::class, 'teaching_attendance_id');
    }
}
