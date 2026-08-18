<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeroomAttendanceFollowUp extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = ['class_id', 'student_id', 'homeroom_teacher_id', 'case_type', 'case_date', 'occurrence_count', 'priority', 'action', 'parent_communication', 'follow_up_date', 'due_date', 'status', 'notes', 'attachment_path', 'created_by', 'updated_by'];

    protected $casts = ['case_date' => 'date:Y-m-d', 'follow_up_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }
}
