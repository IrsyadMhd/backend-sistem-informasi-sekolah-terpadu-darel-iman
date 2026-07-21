<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'class_id',
        'nis',
        'full_name',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
