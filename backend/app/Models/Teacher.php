<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'employee_number',
        'full_name',
        'phone',
        'email',
        'join_date',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'metadata' => 'array',
        ];
    }
}
