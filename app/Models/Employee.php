<?php

namespace App\Models;

use App\Traits\HasPersonPhoto;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, HasPersonPhoto, HasUuids, SoftDeletes;

    protected $table = 'employees';

    protected $appends = ['photo_url', 'avatar_url'];

    protected $fillable = [
        'niy',
        'nik',
        'nama_lengkap',
        'nama_panggilan',
        'gelar_depan',
        'gelar_belakang',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'foto',
        'unit_id',
        'jabatan_id',
        'status_pegawai',
        'tanggal_masuk',
        'tanggal_keluar',
        'status',
        'no_hp',
        'email',
        'alamat',
        'provinsi',
        'kota',
        'kecamatan',
        'kelurahan',
        'kode_pos',
        'user_id',
        'role_id',
        'metadata',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
        'tanggal_keluar' => 'date',
        'metadata' => 'array',
    ];

    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'jabatan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function teachings()
    {
        return $this->hasMany(EmployeeTeaching::class, 'employee_id');
    }

    // === Relasi Baru (SAFE REFACTOR — backward compatible) ===

    /** Divisi pegawai (dari tabel divisions baru) */
    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /** Jadwal mengajar pegawai */
    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'employee_id');
    }

    public function teachingAttendances()
    {
        return $this->hasMany(TeachingAttendance::class, 'employee_id');
    }

    /** Bridge ke tabel teachers lama (jika ada entri yang di-match) */
    public function teacherBridge()
    {
        return $this->hasOne(Teacher::class, 'employee_id');
    }

    /** Relasi entitas Guru resmi (teachers table) */
    public function teacher()
    {
        return $this->hasOne(Teacher::class, 'employee_id');
    }

    /** Nilai yang diinput oleh pegawai/guru (sebagai pembuat) */
    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'created_by', 'user_id');
    }

    // === Accessors (Backward Compatibility) ===

    public function getNameAttribute(): ?string
    {
        return $this->nama_lengkap ?? $this->nama_panggilan ?? null;
    }

    public function getFullNameAttribute(): ?string
    {
        return $this->nama_lengkap ?? null;
    }

    public function getNipAttribute(): ?string
    {
        return $this->niy ?? $this->nik ?? null;
    }
}
