<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_type',
        'ip_address',
        'user_agent',
        'is_trusted',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'last_active_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
