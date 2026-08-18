<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentParent extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'student_parents';

    // Tidak ada soft delete — pivot table tidak memerlukan soft delete
    // Hapus record pivot = putus relasi, bukan hapus orang tua/siswa

    protected $fillable = [
        'student_id',
        'parent_id',
        'relationship_type',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    // --- Relations ---

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    // --- Scopes ---

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeByRelationship($query, string $type)
    {
        return $query->where('relationship_type', $type);
    }
}
