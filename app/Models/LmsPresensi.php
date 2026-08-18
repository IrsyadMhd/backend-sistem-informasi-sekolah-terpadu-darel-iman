<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsPresensi extends Model
{
    use HasFactory, HasUuidPrimaryKey, SoftDeletes;

    protected $table = 'lms_presensi';

    protected $fillable = [
        'jadwal_pelajaran_id',
        'session_id',
        'siswa_id',
        'tanggal',
        'status_hadir',
        'keterangan',
        'pertemuan_ke',
        'waktu_presensi',
        'arrival_time',
        'verification_status',
        'created_by',
        'updated_by',
        'deleted_by',
        'recorded_method', 'recorded_at', 'recorded_by', 'scan_log_id',
        'confidence_score', 'device_identifier', 'capture_metadata',
    ];

    protected $appends = ['status_label', 'status_badge_color'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'pertemuan_ke' => 'integer',
            'waktu_presensi' => 'datetime',
            'recorded_at' => 'datetime',
            'confidence_score' => 'decimal:2',
            'capture_metadata' => 'array',
        ];
    }

    // --- Relationships ---

    /**
     * Relasi ke Jadwal Pelajaran (ClassSchedule)
     */
    public function jadwalPelajaran(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'jadwal_pelajaran_id');
    }

    /**
     * Relasi ke Siswa (Student)
     */
    /**
     * Relasi ke Siswa via kolom `siswa_id`.
     * CATATAN: Nama kolom adalah `siswa_id` (Bahasa Indonesia), bukan `student_id`.
     * Ini inkonsistensi penamaan yang dipertahankan untuk backward compatibility.
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'siswa_id');
    }

    /**
     * Alias relasi student() → siswa() untuk konsistensi dengan model lain.
     * Kedua relasi mengarah ke FK `siswa_id` yang sama.
     */
    public function student(): BelongsTo
    {
        return $this->siswa();
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LessonAttendanceSession::class, 'session_id');
    }

    // --- Accessors ---

    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status_hadir ?? '')) {
            'hadir' => 'Hadir',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpa' => 'Alpa / Tanpa Keterangan',
            'terlambat' => 'Terlambat',
            'belum_diverifikasi', 'belum_diisi' => 'Belum Diisi',
            default => ucfirst($this->status_hadir ?? 'Belum Presensi'),
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match (strtolower($this->status_hadir ?? '')) {
            'hadir' => 'emerald',
            'terlambat' => 'amber',
            'sakit' => 'sky',
            'izin' => 'indigo',
            'alpa' => 'rose',
            default => 'slate',
        };
    }

    // --- Scopes ---

    public function scopeByJadwal($query, string $jadwalId)
    {
        return $query->where('jadwal_pelajaran_id', $jadwalId);
    }

    public function scopeBySiswa($query, string $siswaId)
    {
        return $query->where('siswa_id', $siswaId);
    }

    public function scopeByTanggal($query, string $tanggal)
    {
        return $query->whereDate('tanggal', $tanggal);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status_hadir', strtolower($status));
    }
}
