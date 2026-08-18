<?php

namespace App\Models;

use App\Traits\HasPersonPhoto;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentModel extends Model
{
    use HasFactory, HasPersonPhoto, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'parents';

    protected $appends = ['photo_url', 'avatar_url'];

    protected $fillable = [
        'user_id',
        'nik',
        'father_nik',
        'mother_nik',
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

    /** Relasi anak aktif, termasuk tautan wali non-primer pada pivot. */
    public function studentsPivot()
    {
        return $this->belongsToMany(Student::class, 'student_parents', 'parent_id', 'student_id')
            ->withPivot(['relationship_type', 'is_primary'])
            ->withTimestamps();
    }

    // Scopes
    public function scopeSearch($query, string $keyword)
    {
        return $query->where('full_name', 'ILIKE', "%{$keyword}%")
            ->orWhere('nik', 'ILIKE', "%{$keyword}%")
            ->orWhere('father_nik', 'ILIKE', "%{$keyword}%")
            ->orWhere('mother_nik', 'ILIKE', "%{$keyword}%")
            ->orWhere('phone', 'ILIKE', "%{$keyword}%")
            ->orWhere('email', 'ILIKE', "%{$keyword}%");
    }
}
