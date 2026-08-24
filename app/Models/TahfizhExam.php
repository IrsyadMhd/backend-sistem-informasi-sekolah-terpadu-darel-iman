<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TahfizhExam extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'tahfizh_exams';

    protected $fillable = [
        'student_id',
        'examiner_id',
        'exam_type',
        'juz_number',
        'tajwid_grade',
        'makhraj_grade',
        'final_score',
        'certificate_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'juz_number' => 'integer',
        'final_score' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function examiner()
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }
}
