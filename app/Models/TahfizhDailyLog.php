<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TahfizhDailyLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tahfizh_daily_logs';

    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'class_id',
        'student_id',
        'teacher_id',
        'record_date',
        'day_name',
        'tilawah_text',
        'tilawah_baris',
        'hafalan_surah_number',
        'hafalan_surah_name',
        'hafalan_ayah_start',
        'hafalan_ayah_end',
        'hafalan_baris',
        'murajaah_text',
        'murajaah_lembar',
        'audio_url',
        'notes_teacher',
        'notes_parent',
        'signature_teacher',
        'signature_parent',
        'status',
        'metadata',
    ];

    protected $casts = [
        'tilawah_baris' => 'integer',
        'hafalan_surah_number' => 'integer',
        'hafalan_ayah_start' => 'integer',
        'hafalan_ayah_end' => 'integer',
        'hafalan_baris' => 'integer',
        'murajaah_lembar' => 'float',
        'metadata' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function classModel()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
