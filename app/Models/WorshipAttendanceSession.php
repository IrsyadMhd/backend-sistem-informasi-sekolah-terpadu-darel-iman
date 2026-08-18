<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorshipAttendanceSession extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'worship_attendance_sessions';

    protected $fillable = [
        'template_id',
        'session_date',
        'scheduled_start_at',
        'scheduled_end_at',
        'opened_at',
        'closed_at',
        'location_name',
        'supervisor_id',
        'status',
        'generated_automatically',
        'created_by',
    ];

    protected $casts = [
        'session_date' => 'date',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'generated_automatically' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorshipAttendanceTemplate::class, 'template_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(WorshipAttendanceDetail::class, 'session_id');
    }
}
