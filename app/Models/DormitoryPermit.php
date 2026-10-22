<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DormitoryPermit extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dormitory_permits';

    protected $fillable = [
        'id',
        'student_id',
        'unit_pendidikan_id',
        'permit_number',
        'permit_type',
        'destination',
        'scheduled_departure_at',
        'actual_departure_at',
        'scheduled_return_at',
        'actual_return_at',
        'guardian_name',
        'guardian_phone',
        'guardian_relation',
        'status',
        'return_status',
        'presence_status',
        'deadline_status',
        'is_emergency',
        'medical_complaint',
        'medical_destination',
        'medical_escort_name',
        'original_scheduled_return_at',
        'extension_count',
        'extension_status',
        'extension_reason',
        'extension_requested_return_at',
        'extension_approved_by',
        'extension_approved_at',
        'applied_by_user_id',
        'rejection_reason',
        'late_minutes',
        'notes',
        'approved_by_musyrif_id',
        'checked_out_by',
        'checked_in_by',
    ];

    protected $casts = [
        'scheduled_departure_at' => 'datetime',
        'actual_departure_at' => 'datetime',
        'scheduled_return_at' => 'datetime',
        'actual_return_at' => 'datetime',
        'original_scheduled_return_at' => 'datetime',
        'extension_requested_return_at' => 'datetime',
        'extension_approved_at' => 'datetime',
        'is_emergency' => 'boolean',
        'extension_count' => 'integer',
        'late_minutes' => 'integer',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function musyrif(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by_musyrif_id');
    }

    public function extensionApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'extension_approved_by');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }
}
