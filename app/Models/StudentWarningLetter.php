<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentWarningLetter extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'student_warning_letters';

    protected $fillable = [
        'letter_number',
        'student_id',
        'academic_year_id',
        'semester_id',
        'letter_type',
        'cumulative_points',
        'issue_date',
        'parent_summon_date',
        'parent_summon_status',
        'notes',
        'parent_response_notes',
        'issued_by_id',
        'signed_by_role',
        'pdf_path',
    ];

    protected $casts = [
        'cumulative_points' => 'integer',
        'issue_date' => 'date',
        'parent_summon_date' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }
}
