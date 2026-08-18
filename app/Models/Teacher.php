<?php

namespace App\Models;

use App\Traits\HasPersonPhoto;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, HasPersonPhoto, HasUuidPrimaryKey, SoftDeletes;

    protected $appends = ['photo_url', 'avatar_url'];

    protected $fillable = [
        'user_id',
        'employee_id',
        'employee_number',
        'full_name',
        'phone',
        'email',
        'join_date',
        'metadata',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'metadata' => 'array',
        ];
    }
}
