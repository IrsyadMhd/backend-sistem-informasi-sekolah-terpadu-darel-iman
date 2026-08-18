<?php

namespace App\Models;

use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahMentorAssignment extends Model
{
    use HasFactory, HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = ['employee_id', 'mentor_type', 'unit_id', 'level', 'class_id', 'dormitory', 'room', 'group_name', 'template_id', 'start_date', 'end_date', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function template()
    {
        return $this->belongsTo(MutabaahTemplate::class, 'template_id');
    }

    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }
}
