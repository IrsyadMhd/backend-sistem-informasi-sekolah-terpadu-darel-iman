<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPointTransaction extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'student_point_transactions';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'semester_id',
        'category_id',
        'reported_by_id',
        'recorder_role',
        'points',
        'transaction_date',
        'incident_location',
        'description',
        'direct_consequence_notes',
        'evidence_path',
        'status',
    ];

    protected $casts = [
        'points' => 'integer',
        'transaction_date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function category()
    {
        return $this->belongsTo(DisciplineCategory::class, 'category_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }
}
