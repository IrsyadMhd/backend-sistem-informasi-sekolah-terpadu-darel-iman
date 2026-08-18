<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorshipAttendanceTemplate extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'worship_attendance_templates';

    protected $fillable = [
        'nama',
        'code',
        'category',
        'obligation_type',
        'education_unit_id',
        'dormitory_id',
        'gender_scope',
        'participant_scope',
        'time_source',
        'prayer_name',
        'start_time',
        'end_time',
        'open_offset_minutes',
        'iqamah_offset_minutes',
        'late_tolerance_minutes',
        'close_offset_minutes',
        'active_days',
        'location_name',
        'attendance_methods',
        'verification_required',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active_days' => 'array',
        'attendance_methods' => 'array',
        'verification_required' => 'boolean',
        'is_active' => 'boolean',
        'open_offset_minutes' => 'integer',
        'iqamah_offset_minutes' => 'integer',
        'late_tolerance_minutes' => 'integer',
        'close_offset_minutes' => 'integer',
    ];

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WorshipAttendanceSession::class, 'template_id');
    }
}
