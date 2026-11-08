<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisciplineThresholdRule extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'discipline_threshold_rules';

    protected $fillable = [
        'stage_order',
        'min_points',
        'max_points',
        'sanction_name',
        'sanction_details',
        'notes',
        'letter_type',
        'requires_parent_call',
        'is_expulsion',
        'target_unit_type',
        'is_active',
    ];

    protected $casts = [
        'stage_order' => 'integer',
        'min_points' => 'integer',
        'max_points' => 'integer',
        'requires_parent_call' => 'boolean',
        'is_expulsion' => 'boolean',
        'is_active' => 'boolean',
    ];
}
