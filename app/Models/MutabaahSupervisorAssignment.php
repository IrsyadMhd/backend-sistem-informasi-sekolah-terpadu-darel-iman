<?php

namespace App\Models;

use App\Enums\Mutabaah\RecordStatus;
use App\Enums\Mutabaah\SupervisorType;
use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahSupervisorAssignment extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'employee_id', 'supervisor_type', 'education_unit_id', 'kelas_id', 'rombel_id',
        'dormitory_id', 'room_id', 'mentoring_group', 'template_id',
        'academic_year_id', 'semester_id', 'start_date', 'end_date',
        'is_primary', 'can_input', 'can_edit', 'can_finalize', 'can_view_report',
        'status', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'supervisor_type' => SupervisorType::class,
            'status' => RecordStatus::class,
            'start_date' => 'date', 'end_date' => 'date',
            'is_primary' => 'boolean', 'can_input' => 'boolean', 'can_edit' => 'boolean',
            'can_finalize' => 'boolean', 'can_view_report' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
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

    public function template()
    {
        return $this->belongsTo(MutabaahTemplate::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function dailyHeaders()
    {
        return $this->hasMany(MutabaahDailyHeader::class, 'supervisor_assignment_id');
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

    public function scopeBySupervisor($query, string $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
