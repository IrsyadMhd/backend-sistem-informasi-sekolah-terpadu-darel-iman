<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentVocabularySubmission extends Model
{
    use HasFactory;

    protected $table = 'student_vocabulary_submissions';

    protected $fillable = [
        'student_id',
        'vocabulary_id',
        'teacher_id',
        'unit_id',
        'submission_date',
        'fluency_rating',
        'score',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'score' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(VocabularyBank::class, 'vocabulary_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }
}
