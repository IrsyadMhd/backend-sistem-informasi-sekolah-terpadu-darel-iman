<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrCredential extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'qr_credentials';

    protected $fillable = [
        'user_id',
        'employee_id',
        'student_id',
        'card_type',
        'token_hash',
        'card_version',
        'status',
        'issued_at',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
