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
        'level',
        'code',
        'name',
        'point_weight',
        'is_active',
    ];

    protected $casts = [
        'point_weight' => 'integer',
        'is_active' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(StudentPointTransaction::class, 'category_id');
    }
}
