<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class LessonAttendanceCorrection extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['attendance_id', 'previous_status', 'proposed_status', 'reason', 'attachment_path', 'status', 'before_data', 'after_data', 'requested_by', 'approved_by', 'approved_at', 'reviewed_by', 'reviewed_at', 'review_notes', 'ip_address', 'user_agent'];

    protected $casts = ['before_data' => 'array', 'after_data' => 'array', 'approved_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function attendance()
    {
        return $this->belongsTo(LmsPresensi::class);
    }
}
