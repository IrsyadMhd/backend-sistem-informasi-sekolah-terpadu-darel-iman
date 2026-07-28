<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationUnit extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'education_units';

    protected $fillable = [
        'code',
        'name',
        'level',
        'description',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    // === Relasi Baru (SAFE REFACTOR — backward compatible) ===

    /**
     * Jenis unit pendidikan (FK via jenis_unit_id yang ditambah Migration 01).
     * Relasi ini sudah ada di JenisUnitPendidikan model (hasMany),
     * sekarang ditambahkan sisi belongsTo untuk kelengkapan.
     */
    public function jenisUnit()
    {
        return $this->belongsTo(JenisUnitPendidikan::class, 'jenis_unit_id', 'uuid');
    }

    /** Pegawai di unit ini */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'unit_id');
    }

    /** Kelas (rombel) di unit ini */
    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'unit_pendidikan_id');
    }
}
