<?php

namespace App\Models;

use App\Enums\Mutabaah\DailyStatus;
use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahDailyHeader extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'student_id', 'template_id', 'supervisor_assignment_id', 'education_unit_id',
        'kelas_id', 'rombel_id', 'academic_year_id', 'semester_id', 'activity_date',
        'status', 'total_items', 'good_count', 'less_count', 'not_done_count', 'na_count',
        'score', 'supervisor_notes', 'finalized_at', 'finalized_by',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date', 'status' => DailyStatus::class,
            'total_items' => 'integer', 'good_count' => 'integer', 'less_count' => 'integer',
            'not_done_count' => 'integer', 'na_count' => 'integer',
            'score' => 'decimal:2', 'finalized_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function template()
    {
        return $this->belongsTo(MutabaahTemplate::class);
    }

    public function supervisorAssignment()
    {
        return $this->belongsTo(MutabaahSupervisorAssignment::class);
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class);
    }

    public function kelas()
    {
        return $this->belongsTo(SchoolClass::class, 'kelas_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Kelas::class, 'rombel_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function details()
    {
        return $this->hasMany(MutabaahDailyDetail::class, 'daily_header_id');
    }

    public function parentSignatures()
    {
        return $this->hasMany(MutabaahParentSignature::class, 'daily_header_id');
    }

    public function activityNotes()
    {
        return $this->hasMany(MutabaahActivityNote::class, 'daily_header_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeByUnit($query, string $id)
    {
        return $query->where('education_unit_id', $id);
    }

    public function scopeByAcademicYear($query, string $id)
    {
        return $query->where('academic_year_id', $id);
    }

    public function scopeBySemester($query, string $id)
    {
        return $query->where('semester_id', $id);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('activity_date', $date);
    }

    public function scopeByRombel($query, string $id)
    {
        return $query->where('rombel_id', $id);
    }

    public function scopeBySupervisor($query, string $assignmentId)
    {
        return $query->where('supervisor_assignment_id', $assignmentId);
    }
}
