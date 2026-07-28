<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $fillable = [
        'user_id',
        'parent_id',
        'class_id',
        'unit_id',
        'nis',
        'nisn',
        'full_name',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(ParentModel::class, 'parent_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function bills()
    {
        return $this->hasMany(StudentBill::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByClass($query, string $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByUnit($query, string $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    // === Relasi Baru (SAFE REFACTOR — backward compatible) ===

    /**
     * Relasi many-to-many ke orang tua (via tabel pivot student_parents baru).
     * students.parent_id lama tetap ada untuk backward compat.
     */
    public function parentsPivot()
    {
        return $this->belongsToMany(
            ParentModel::class,
            'student_parents',
            'student_id',
            'parent_id'
        )->withPivot(['relationship_type', 'is_primary'])->withTimestamps();
    }

    /** Nilai raport siswa */
    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'student_id');
    }

    /** Jadwal kelas siswa (via kelas) */
    public function schedules()
    {
        return $this->hasManyThrough(
            ClassSchedule::class,
            Kelas::class,
            'id',        // kelas.id
            'kelas_id',  // class_schedules.kelas_id
            'class_id',  // students.class_id
            'id'         // kelas.id
        );
    }
}
