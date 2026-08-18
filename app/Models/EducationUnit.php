<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EducationUnit extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'education_units';

    protected $fillable = [
        'jenis_unit_id',
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

    protected static function booted(): void
    {
        static::creating(function (EducationUnit $unit) {
            if (empty($unit->jenis_unit_id)) {
                $jenisUnitId = null;
                if (! empty($unit->level)) {
                    $jenisUnitId = JenisUnitPendidikan::query()
                        ->where('singkatan', $unit->level)
                        ->orWhere('kode_jenis', $unit->level)
                        ->orWhere('nama_jenis', $unit->level)
                        ->value('uuid');
                }

                if (! $jenisUnitId) {
                    $jenisUnitId = JenisUnitPendidikan::query()->value('uuid');
                }

                if (! $jenisUnitId) {
                    $default = JenisUnitPendidikan::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'kode_jenis' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $unit->level ?? 'GEN'), 0, 10)) ?: 'GEN',
                        'nama_jenis' => 'Jenis Unit '.($unit->level ?? 'Umum'),
                        'singkatan' => strtoupper(substr($unit->level ?? 'UMUM', 0, 10)),
                        'jenjang' => 'Lainnya',
                        'status' => true,
                    ]);
                    $jenisUnitId = $default->uuid;
                }

                $unit->jenis_unit_id = $jenisUnitId;
            }
        });
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

    /** Siswa di unit ini */
    public function students()
    {
        return $this->hasMany(Student::class, 'unit_id');
    }

    /** Guru di unit ini (melalui relasi Employee) */
    public function teachers()
    {
        return $this->hasMany(Employee::class, 'unit_id')->where(function ($q) {
            $q->where('status_pegawai', 'like', '%Guru%')
              ->orWhereHas('position', function ($p) {
                  $p->where('name', 'like', '%Guru%');
              });
        });
    }

    /** Kelas (rombel) di unit ini */
    public function kelas()
    {
        return $this->hasMany(Kelas::class, 'unit_pendidikan_id');
    }

    /** Alias kelas untuk relasi Eloquent withCount(['classes']) */
    public function classes()
    {
        return $this->hasMany(Kelas::class, 'unit_pendidikan_id');
    }
}
