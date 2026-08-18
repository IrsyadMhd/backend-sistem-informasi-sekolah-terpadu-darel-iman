<?php

namespace App\Models;

use App\Enums\Mutabaah\RecordStatus;
use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahTemplate extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'education_unit_id', 'education_level',
        'academic_year_id', 'semester_id', 'start_date', 'end_date',
        'description', 'status', 'created_by', 'updated_by', 'deleted_by',
        // Kolom legacy dipertahankan selama masa transisi.
        'unit_id', 'level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'end_date' => 'date',
            'status' => RecordStatus::class, 'is_active' => 'boolean',
        ];
    }

    public function items()
    {
        return $this->hasMany(MutabaahTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    public function agendaItems()
    {
        return $this->belongsToMany(MutabaahAgendaItem::class, 'mutabaah_template_items', 'template_id', 'agenda_item_id')->withPivot(['sort_order', 'weight', 'target_value', 'is_required', 'requires_parent_signature', 'instruction', 'is_active'])->withTimestamps();
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assignments()
    {
        return $this->hasMany(MutabaahTemplateAssignment::class, 'template_id');
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(MutabaahSupervisorAssignment::class, 'template_id');
    }

    public function dailyHeaders()
    {
        return $this->hasMany(MutabaahDailyHeader::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', RecordStatus::Active->value);
    }

    public function scopeByUnit($query, string $unitId)
    {
        return $query->where('education_unit_id', $unitId);
    }

    public function scopeByAcademicYear($query, string $yearId)
    {
        return $query->where('academic_year_id', $yearId);
    }

    public function scopeBySemester($query, string $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('start_date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date));
    }
}
