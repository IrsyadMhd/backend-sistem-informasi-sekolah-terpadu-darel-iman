<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeTeaching extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'employee_teachings';

    protected $fillable = [
        'employee_id',
        'classroom_id',
        'subject_id',
        'academic_year_id',
        'semester_id',
        'aktif',
        'metadata',
    ];

    protected $casts = [
        'aktif' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'mapel',
        'kelas',
        'tahun',
        'semester',
    ];

    public function getMapelAttribute(): ?string
    {
        return $this->subject?->name
            ?? $this->subject?->nama_mapel
            ?? data_get($this->metadata, 'mapel');
    }

    public function getKelasAttribute(): ?string
    {
        return $this->classroom?->name
            ?? $this->kelasRel?->nama_kelas
            ?? data_get($this->metadata, 'kelas');
    }

    public function getTahunAttribute(): ?string
    {
        return data_get($this->metadata, 'tahun');
    }

    public function getSemesterAttribute(): ?string
    {
        return data_get($this->metadata, 'semester');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function kelasRel()
    {
        return $this->belongsTo(Kelas::class, 'classroom_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
