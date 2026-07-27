<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentModel extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'occupation',
        'address',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    // Scopes
    public function scopeSearch($query, string $keyword)
    {
        return $query->where('full_name', 'ILIKE', "%{$keyword}%")
            ->orWhere('phone', 'ILIKE', "%{$keyword}%")
            ->orWhere('email', 'ILIKE', "%{$keyword}%");
    }
}
