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

    public function educationUnit()
    {
        return $this->hasOneThrough(
            EducationUnit::class,
            Employee::class,
            'id', // FK on employees table
            'id', // FK on education_units table
            'employee_id', // Local key on teachers table
            'unit_id' // Local key on employees table
        );
    }

    public function unit()
    {
        return $this->educationUnit();
    }

    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id', 'employee_id');
    }

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'metadata' => 'array',
        ];
    }
}
