<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentNote extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'student_notes';

    protected $fillable = [
        'student_id',
        'teacher_id',
        'education_unit_id',
        'academic_year_id',
        'semester_id',
        'date',
        'category',
        'title',
        'content',
        'priority',
        'follow_up',
        'visible_to_parent',
        'visible_to_student',
        'attachment_path',
        'signed_by_user_id',
        'signed_at',
        'signature_content_hash',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'visible_to_parent' => 'boolean',
        'visible_to_student' => 'boolean',
        'signed_at' => 'datetime',
    ];

    /** Hash isi catatan saat ditandatangani, untuk deteksi perubahan versi. */
    public static function contentHash(?string $content): ?string
    {
        return $content !== null ? hash('sha256', trim($content)) : null;
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
}
