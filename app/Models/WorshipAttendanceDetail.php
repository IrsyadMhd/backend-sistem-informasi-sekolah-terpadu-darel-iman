<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorshipAttendanceDetail extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'worship_attendance_details';

    protected $fillable = [
        'session_id',
        'student_id',
        'attendance_status',
        'attended_at',
        'method',
        'device_identifier',
        'verified_by',
        'notes',
        'is_private',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'is_private' => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorshipAttendanceSession::class, 'session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
