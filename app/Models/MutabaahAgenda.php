<?php

namespace App\Models;

use App\Traits\HasMutabaahAudit;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MutabaahAgenda extends Model
{
    use HasMutabaahAudit, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'jenis_unit_id', 'unit_id', 'category_id', 'category', 'code', 'name',
        'input_type', 'target', 'weight', 'icon', 'color', 'level', 'description',
        'sort_order', 'is_active', 'effective_from', 'effective_until',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'target' => 'decimal:2',
            'weight' => 'decimal:2',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function jenisUnit()
    {
        return $this->belongsTo(JenisUnitPendidikan::class, 'jenis_unit_id', 'uuid');
    }

    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function categoryMaster()
    {
        return $this->belongsTo(MutabaahCategory::class, 'category_id');
    }
}
