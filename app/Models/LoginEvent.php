<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginEvent extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'login_events';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'education_unit_id',
        'portal_type',
        'identifier_used',
        'login_method',
        'status',
        'failure_reason',
        'ip_address',
        'user_agent',
        'device_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }
}
