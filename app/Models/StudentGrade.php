<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class StudentGrade extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'student_grades';

    protected $fillable = [
        'student_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'kelas_id',
        'class_id',
        'score_assignment',
        'score_quiz',
        'score_project',
        'score_midterm',
        'score_final',
        'final_score',
        'grade_letter',
        'is_passed',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'score_assignment' => 'float',
        'score_quiz' => 'float',
        'score_project' => 'float',
        'score_midterm' => 'float',
        'score_final' => 'float',
        'final_score' => 'float',
        'is_passed' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Hitung final_score dari komponen berdasarkan bobot di modul_semesters.
     * Jika modul_semesters tidak ditemukan, gunakan bobot default.
     */
    public function hitungNilaiAkhir(): float
    {
        $modul = ModulSemester::where('kelas_id', $this->kelas_id)
            ->where('mata_pelajaran_id', $this->subject_id)
            ->where('semester_id', $this->semester_id)
            ->first();

        $bobotTugas = $modul?->bobot_tugas ?? 20.0;
        $bobotQuiz = $modul?->bobot_quiz ?? 15.0;
        $bobotProjek = $modul?->bobot_projek ?? 25.0;
        $bobotUTS = $modul?->bobot_uts ?? 20.0;
        $bobotUAS = $modul?->bobot_uas ?? 20.0;

        $total = ($this->score_assignment ?? 0) * ($bobotTugas / 100)
            + ($this->score_quiz ?? 0) * ($bobotQuiz / 100)
            + ($this->score_project ?? 0) * ($bobotProjek / 100)
            + ($this->score_midterm ?? 0) * ($bobotUTS / 100)
            + ($this->score_final ?? 0) * ($bobotUAS / 100);

        return round($total, 2);
    }

    /**
     * Tentukan grade letter dari nilai akhir.
     */
    public static function getGradeLetter(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    // --- Relations ---

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /** Kelas primer (tbl_kelas) */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /** Kelas legacy (classes) */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    // --- Scopes ---

    public function scopeByPeriod($query, string $academicYearId, string $semesterId)
    {
        return $query
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId);
    }

    public function scopeLulus($query)
    {
        return $query->where('is_passed', true);
    }
}
