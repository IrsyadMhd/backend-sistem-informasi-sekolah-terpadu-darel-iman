<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeleteRequest extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'delete_requests';

    protected $fillable = [
        'target_table',
        'target_id',
        'target_label',
        'requested_by',
        'education_unit_id',
        'reason',
        'attachment_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }
}
