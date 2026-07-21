<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'classroom_id',
        'homeroom_teacher_id',
        'name',
        'level',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
