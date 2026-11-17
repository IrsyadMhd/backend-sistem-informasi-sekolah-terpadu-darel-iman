<?php

namespace App\Models;

use App\Traits\HasPersonPhoto;
use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, HasPersonPhoto, HasUuidPrimaryKey, SoftDeletes;

    protected $appends = [
        'photo_url',
        'avatar_url',
        'nama_lengkap',
        'name',
        'nama',
        'unit_name',
    ];

    protected $fillable = [
        'user_id',
        'parent_id',
        'class_id',
        'kelas_id',   // FK primer ke tbl_kelas (migration 2026_08_01_000001)
        'unit_id',
        'nis',
        'nisn',
        'full_name',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'photo',
        'photo_thumb',
        'is_active',
        'tahun_masuk',
        'metadata',
        'created_by',
        'updated_by',
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

    /**
     * Relasi ke tabel classes lama (backward compat).
     * CATATAN: data aktual ada di tbl_kelas, gunakan kelas() untuk data terkini.
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Relasi PRIMER ke tbl_kelas (modul kelas aktif).
     * Gunakan relasi ini untuk semua query kelas/rombel yang terbaru.
     * FK: students.kelas_id → tbl_kelas.id
     */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function educationUnit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    /**
     * Relasi ke unit pendidikan (alias dari educationUnit).
     * Memungkinkan eager loading $student->unit maupun User::with('student.unit')
     * selaras dengan Employee::unit.
     */
    public function unit()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_id');
    }

    public function pointTransactions()
    {
        return $this->hasMany(StudentPointTransaction::class, 'student_id');
    }

    public function warningLetters()
    {
        return $this->hasMany(StudentWarningLetter::class, 'student_id');
    }

    public function bills()
    {
        return $this->hasMany(StudentBill::class, 'student_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id');
    }

    public function mutations()
    {
        return $this->hasMany(StudentMutation::class, 'student_id')->latest('created_at');
    }

    public function activeMutation()
    {
        return $this->hasOne(StudentMutation::class, 'student_id')
            ->where('status', 'menunggu_persetujuan')
            ->latest('created_at');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByClass($query, string $classId)
    {
        // Support both kelas_id (tbl_kelas, primary) and class_id (classes, legacy)
        return $query->where(function ($q) use ($classId) {
            $q->where('kelas_id', $classId)
              ->orWhere('class_id', $classId);
        });
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

    /** Alias respons portal untuk daftar orang tua/wali pada pivot existing. */
    public function parents()
    {
        return $this->parentsPivot();
    }

    /** Nilai raport siswa */
    public function grades()
    {
        return $this->hasMany(StudentGrade::class, 'student_id');
    }

    /**
     * Jadwal kelas siswa via kelas_id (tbl_kelas) — relasi primer.
     * Menggunakan kolom kelas_id (baru) sebagai foreign key ke tbl_kelas.
     */
    public function schedules()
    {
        return $this->hasManyThrough(
            ClassSchedule::class,
            Kelas::class,
            'id',        // tbl_kelas.id
            'kelas_id',  // class_schedules.kelas_id
            'kelas_id',  // students.kelas_id (FK primer ke tbl_kelas)
            'id'         // tbl_kelas.id
        );
    }

    public function presensiPembelajarans()
    {
        return $this->hasMany(LmsPresensi::class, 'siswa_id');
    }

    // Accessors for backward compatibility with existing controllers/resources
    public function getNameAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? null;
    }

    public function getNamaLengkapAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? null;
    }

    public function getNamaAttribute(): ?string
    {
        return $this->attributes['full_name'] ?? null;
    }

    /**
     * Accessor foto siswa — cek kolom `photo` dulu, fallback ke metadata['photo'].
     * Backward compatible: frontend yang memakai metadata.photo tetap bekerja.
     */
    public function getPhotoAttribute(): ?string
    {
        // Cek kolom photo langsung (migration 2026_08_01_000005)
        if (! empty($this->attributes['photo'])) {
            return $this->attributes['photo'];
        }

        // Fallback ke metadata['photo'] untuk data lama
        $meta = is_array($this->metadata) ? $this->metadata : json_decode($this->attributes['metadata'] ?? '{}', true);

        return $meta['photo'] ?? null;
    }

    /**
     * Resolusi nama unit pendidikan siswa (hanya jika relasi sudah dimuat untuk mencegah ribuan N+1 query).
     */
    public function getUnitNameAttribute(): ?string
    {
        if ($this->relationLoaded('educationUnit') && $this->educationUnit) {
            return $this->educationUnit->name;
        }
        if ($this->relationLoaded('unit') && $this->unit) {
            return $this->unit->name;
        }
        if ($this->relationLoaded('kelas') && $this->kelas && $this->kelas->relationLoaded('unitPendidikan') && $this->kelas->unitPendidikan) {
            return $this->kelas->unitPendidikan->name;
        }

        return $this->attributes['unit_name'] ?? null;
    }

    /**
     * Resolusi daftar kandidat nomor identitas pembayaran resmi (SIPDEI / VA) untuk ananda.
     * Mengambil nomor yang valid (non-kosong) dari metadata pembiayaan, no pendaftaran,
     * nomor VA, atau NIS dengan memfilter string kosong.
     *
     * @return array<string>
     */
    public function getPaymentIdentifierCandidates(): array
    {
        $meta = is_array($this->metadata) ? $this->metadata : (json_decode($this->attributes['metadata'] ?? '{}', true) ?: []);
        $pembiayaan = is_array($meta['pembiayaan'] ?? null) ? $meta['pembiayaan'] : [];

        $rawCandidates = [
            $meta['nis_pembayaran'] ?? null,
            $pembiayaan['nip_pembayaran'] ?? null,
            $pembiayaan['nis_pembayaran'] ?? null,
            $meta['no_pendaftaran'] ?? null,
            $this->attributes['nis'] ?? null,
            $meta['no_va'] ?? null,
            $this->attributes['nisn'] ?? null,
        ];

        $candidates = [];
        foreach ($rawCandidates as $val) {
            $cleaned = trim((string) $val);
            if ($cleaned !== '' && !in_array($cleaned, $candidates, true)) {
                $candidates[] = $cleaned;
            }
        }

        return $candidates;
    }

    /**
     * Mengembalikan identitas pembayaran terbaik (pertama yang tidak kosong).
     */
    public function resolvePaymentIdentifier(): ?string
    {
        $candidates = $this->getPaymentIdentifierCandidates();
        return $candidates[0] ?? null;
    }

    /**
     * Resolusi nomor Virtual Account BSI dinamis.
     * Menggabungkan kode biller BSI (default: 9023) dengan nomor pembayaran atau NIS.
     */
    public function resolveVirtualAccount(?string $bsiPrefix = null): ?string
    {
        $meta = is_array($this->metadata) ? $this->metadata : (json_decode($this->attributes['metadata'] ?? '{}', true) ?: []);
        if (!empty($meta['no_va'])) {
            $cleanVa = preg_replace('/\D/', '', (string) $meta['no_va']);
            if (strlen($cleanVa) >= 8) {
                return $cleanVa;
            }
        }

        $prefix = $bsiPrefix ?: (\App\Models\SiteSetting::current()->bsi_va_prefix ?: '9023');
        $identifier = $this->resolvePaymentIdentifier();
        if (!$identifier) {
            return null;
        }

        $cleanId = preg_replace('/\D/', '', $identifier);
        if ($cleanId === '') {
            return null;
        }

        // Jika nomor sudah diawali prefix biller dan panjangnya cukup, gunakan langsung
        if (str_starts_with($cleanId, $prefix) && strlen($cleanId) >= (strlen($prefix) + 6)) {
            return $cleanId;
        }

        return $prefix . $cleanId;
    }
}
