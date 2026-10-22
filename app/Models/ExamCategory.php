<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamCategory extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'exam_categories';

    protected $fillable = [
        'unit_pendidikan_id',
        'name',
        'code',
        'color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function unitPendidikan()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    public function examSchedules()
    {
        return $this->hasMany(ExamSchedule::class, 'exam_category_id');
    }
}
