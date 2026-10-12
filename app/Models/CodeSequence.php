<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeSequence extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'code_sequences';

    protected $fillable = [
        'entity_type',
        'prefix',
        'separator',
        'padding_length',
        'unit_id',
        'academic_year_id',
        'period_key',
        'current_sequence',
        'format_pattern',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'padding_length' => 'integer',
        'current_sequence' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }
}
