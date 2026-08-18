<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSchedule extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'class_schedules';

    protected $fillable = [
        'kelas_id',
        'class_id',
        'employee_id',
        'teacher_id',
        'subject_id',
        'classroom_id',
        'academic_year_id',
        'semester_id',
        'day_of_week',
        'time_start',
        'time_end',
        'week_type',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected $appends = [
        'day_name',
        'nama_hari',
        'start_time',
        'end_time',
    ];

    // Peta nama hari
    public const DAY_NAMES = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    // --- Accessors ---

    public function getNamaHariAttribute(): string
    {
        return static::DAY_NAMES[$this->day_of_week] ?? "Hari {$this->day_of_week}";
    }

    public function getDayNameAttribute(): string
    {
        return $this->getNamaHariAttribute();
    }

    public function getStartTimeAttribute(): ?string
    {
        return $this->attributes['time_start'] ?? null;
    }

    public function getEndTimeAttribute(): ?string
    {
        return $this->attributes['time_end'] ?? null;
    }

    // --- Relations (Primer) ---

    /** Kelas (dari tbl_kelas — sumber primer) */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /** Guru (dari employees — sumber primer) */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // --- Relations (Legacy / Backward Compat) ---

    /** Kelas legacy (dari classes — sumber sekunder) */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Guru legacy (dari teachers — sumber sekunder) */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    // --- Relations (Umum) ---

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function classroom()
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function presensis()
    {
        return $this->hasMany(LmsPresensi::class, 'jadwal_pelajaran_id');
    }

    public function teachingAttendances()
    {
        return $this->hasMany(TeachingAttendance::class, 'schedule_id');
    }

    // --- Scopes ---

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeByPeriod($query, string $academicYearId, string $semesterId)
    {
        return $query
            ->where('academic_year_id', $academicYearId)
            ->where('semester_id', $semesterId);
    }
}
