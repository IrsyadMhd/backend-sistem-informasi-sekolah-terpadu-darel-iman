<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'attendances';

    // ── Status Constants ──────────────────────────────────────────────────────
    public const STATUS_HADIR       = 'HADIR';
    public const STATUS_PRESENT     = 'present';   // alias lama
    public const STATUS_TERLAMBAT   = 'TERLAMBAT';
    public const STATUS_SAKIT       = 'SAKIT';
    public const STATUS_IZIN        = 'IZIN';
    public const STATUS_ALPHA       = 'ALPHA';
    public const STATUS_ABSENT      = 'absent';    // alias lama
    public const STATUS_DINAS_LUAR  = 'DINAS_LUAR';

    /** Status yang dianggap "hadir" untuk kalkulasi persentase */
    public const STATUSES_HADIR     = [self::STATUS_HADIR, self::STATUS_PRESENT];
    /** Status alpha / tidak hadir tanpa keterangan */
    public const STATUSES_ALPHA     = [self::STATUS_ALPHA, self::STATUS_ABSENT];
    /** Status dinas luar / tugas luar */
    public const STATUSES_DINAS     = [self::STATUS_DINAS_LUAR, 'dinas_luar'];

    // ── Tipe Presensi Constants ───────────────────────────────────────────────
    public const TIPE_PEGAWAI = 'Pegawai';
    public const TIPE_SISWA   = 'Siswa';

    // ── Method Constants ──────────────────────────────────────────────────────
    public const METHOD_MANUAL  = 'MANUAL';
    public const METHOD_QR      = 'QR';
    public const METHOD_RFID    = 'RFID';

    // ── Storage ───────────────────────────────────────────────────────────────
    public const STORAGE_DISK   = 'public';
    public const STORAGE_PATH   = 'attendance_attachments';

    // ── Default Values ────────────────────────────────────────────────────────
    public const DEFAULT_LOCATION  = 'SIMS Mobile App';
    public const DEFAULT_KETERANGAN_MASUK = 'Absen masuk terdaftar.';

    protected $fillable = [
        'tipe_presensi',
        'student_id',
        'employee_id',
        'class_id',
        'unit_pendidikan_id',
        'academic_year_id',
        'semester_id',
        'month',
        'attendance_date',
        'check_in_time',
        'check_out_time',
        'check_out_status',
        'check_out_method',
        'checkout_device_id',
        'pickup_person',
        'pickup_relation',
        'pickup_verification',
        'photo_snapshot',
        'approved_by',
        'verified_by',
        'status',
        'attendance_method',
        'location',
        'latitude',
        'longitude',
        'attachment_path',
        'keterangan',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['status_label', 'status_badge_color', 'attachment_url'];

    protected static function booted(): void
    {
        // PostgreSQL partitioned table: month (partition key) wajib terisi.
        // Derive otomatis dari attendance_date bila caller tidak mengisinya.
        static::creating(function (Attendance $model) {
            if (empty($model->month) && $model->attendance_date) {
                $model->month = (int) $model->attendance_date->format('n');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date:Y-m-d',
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'month' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
        ];
    }

    // Relationships
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'unit_pendidikan_id');
    }

    // Scopes
    public function scopeHadir($query)
    {
        return $query->whereIn('status', self::STATUSES_HADIR);
    }

    public function scopeTerlambat($query)
    {
        return $query->where('status', self::STATUS_TERLAMBAT);
    }

    public function scopeIzin($query)
    {
        return $query->where('status', self::STATUS_IZIN);
    }

    public function scopeSakit($query)
    {
        return $query->where('status', self::STATUS_SAKIT);
    }

    public function scopeAlpha($query)
    {
        return $query->whereIn('status', self::STATUSES_ALPHA);
    }

    public function scopeHariIni($query)
    {
        return $query->whereDate('attendance_date', now()->toDateString());
    }

    public function scopeByClass($query, string $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByUnit($query, string $unitId)
    {
        return $query->where('unit_pendidikan_id', $unitId);
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match (strtoupper($this->status ?? '')) {
            'HADIR', 'PRESENT' => 'Hadir Tepat Waktu',
            'TERLAMBAT'        => 'Hadir Terlambat',
            'SAKIT'            => 'Sakit (Surat Dokter)',
            'IZIN'             => 'Izin (Keterangan)',
            'ALPHA', 'ABSENT'  => 'Tanpa Keterangan (Alpha)',
            'DINAS_LUAR'       => 'Dinas Luar',
            default            => $this->status ?? 'Belum Absen',
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match (strtoupper($this->status ?? '')) {
            'HADIR', 'PRESENT' => 'success',
            'TERLAMBAT' => 'warning',
            'SAKIT' => 'info',
            'IZIN' => 'secondary',
            'ALPHA', 'ABSENT' => 'danger',
            'DINAS_LUAR' => 'primary',
            default => 'default',
        };
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        if (str_starts_with($this->attachment_path, 'http://') || str_starts_with($this->attachment_path, 'https://')) {
            return $this->attachment_path;
        }

        return url('storage/' . ltrim($this->attachment_path, '/'));
    }
}
