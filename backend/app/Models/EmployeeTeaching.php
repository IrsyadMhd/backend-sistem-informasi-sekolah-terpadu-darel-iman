<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTeaching extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'employee_teachings';

    protected $fillable = [
        'employee_id',
        'classroom_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'aktif',
        'metadata',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'metadata' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
