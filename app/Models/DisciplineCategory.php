<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineCategory extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'discipline_categories';

    protected $fillable = [
        'type',
        'cluster',
        'pasal_number',
        'rule_name',
        'name',
        'violation_detail',
        'level',
        'code',
        'point_weight',
        'direct_consequence',
        'is_permanent',
        'target_unit_type',
        'education_unit_id',
        'is_active',
    ];

    protected $casts = [
        'point_weight' => 'integer',
        'is_permanent' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    public function transactions()
    {
        return $this->hasMany(StudentPointTransaction::class, 'category_id');
    }
}
