<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentCardSetting extends Model
{
    use HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id', 'education_unit_id', 'orientation', 'template_color',
        'show_photo', 'show_logo', 'show_qrcode', 'show_nis', 'show_nisn',
        'show_class', 'show_rombel', 'show_unit', 'show_academic_year',
        'show_motto', 'is_default', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'show_photo' => 'boolean', 'show_logo' => 'boolean',
            'show_qrcode' => 'boolean', 'show_nis' => 'boolean',
            'show_nisn' => 'boolean', 'show_class' => 'boolean',
            'show_rombel' => 'boolean', 'show_unit' => 'boolean',
            'show_academic_year' => 'boolean', 'show_motto' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
