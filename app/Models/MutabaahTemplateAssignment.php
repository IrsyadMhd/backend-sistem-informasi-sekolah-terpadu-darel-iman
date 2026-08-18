<?php

namespace App\Models;

use App\Enums\Mutabaah\RecordStatus;
use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahTemplateAssignment extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = ['template_id', 'education_unit_id', 'education_level', 'kelas_id', 'rombel_id', 'student_id', 'academic_year_id', 'semester_id', 'start_date', 'end_date', 'priority', 'status', 'created_by', 'updated_by', 'deleted_by', 'unit_id', 'level', 'class_id', 'group_name', 'is_active'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'priority' => 'integer', 'status' => RecordStatus::class, 'is_active' => 'boolean'];
    }

    public function template()
    {
        return $this->belongsTo(MutabaahTemplate::class, 'template_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function kelas()
    {
        return $this->belongsTo(SchoolClass::class, 'kelas_id');
    }

    public function rombel()
    {
        return $this->belongsTo(Kelas::class, 'rombel_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', RecordStatus::Active->value);
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
        return $query->whereDate('start_date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }

    public function scopeByRombel($query, string $id)
    {
        return $query->where('rombel_id', $id);
    }
}
