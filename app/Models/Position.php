<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'positions';

    public const LEVEL_JABATAN_MAP = [
        1 => 'Pengurus Yayasan',
        2 => 'Divisi Pendidikan',
        3 => 'Kepala Sekolah',
        4 => 'Wakil Kepala Sekolah',
        5 => 'Kepala Divisi',
        6 => 'Tata Usaha',
        7 => 'Operator Sekolah',
        8 => 'Guru',
        9 => 'Musyrif',
        10 => 'Staf Administrasi',
    ];

    public const SATUAN_KERJA_OPTIONS = [
        'Pengurus' => 'Pengurus',
        'Bidang Pendidikan' => 'Bidang Pendidikan',
        'Unit Pendidikan' => 'Unit Pendidikan',
    ];

    public const SCOPE_AKSES_OPTIONS = [
        'semua_unit' => 'Semua Unit',
        'bidang_pendidikan' => 'Bidang Pendidikan',
        'unit_sendiri' => 'Unit Pendidikan Sendiri',
        'rombel_sendiri' => 'Rombel Sendiri',
        'kelas_mapel_sendiri' => 'Kelas & Mata Pelajaran Sendiri',
        'siswa_binaan' => 'Siswa Binaan',
    ];

    protected $fillable = [
        'code',
        'name',
        'satuan_kerja',
        'unit_sekolah_id',
        'level_jabatan',
        'atasan_langsung_id',
        'atasan_pegawai_id',
        'role_sistem_id',
        'scope_akses',
        'urutan',
        'warna',
        'ikon',
        'description',
        'is_active',
        'tampil_struktur',
        'boleh_login',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'level_jabatan' => 'integer',
        'urutan' => 'integer',
        'is_active' => 'boolean',
        'tampil_struktur' => 'boolean',
        'boleh_login' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Auto-generate kode_jabatan (e.g. JBT-001)
     */
    public static function generateKode(): string
    {
        $lastNumber = static::withTrashed()
            ->where('code', 'LIKE', 'JBT-%')
            ->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/[^0-9]/', '', $code))
            ->max() ?? 0;

        return 'JBT-'.str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    // Accessor level label
    public function getLevelLabelAttribute(): string
    {
        return static::LEVEL_JABATAN_MAP[$this->level_jabatan] ?? "Level {$this->level_jabatan}";
    }

    public function getDeskripsiAttribute(): ?string
    {
        return $this->attributes['description'] ?? null;
    }

    public function setDeskripsiAttribute(?string $value): void
    {
        $this->attributes['description'] = $value;
    }

    /**
     * Periksa apakah posisi/jabatan ini tergolong struktur Pengurus Yayasan.
     */
    public function isPengurusYayasan(): bool
    {
        if ((int) $this->level_jabatan === 1) {
            return true;
        }

        if ($this->satuan_kerja === 'Pengurus') {
            return true;
        }

        $name = strtolower($this->name ?? '');
        if (str_contains($name, 'pengurus yayasan') || str_contains($name, 'yayasan')) {
            return true;
        }

        $levelLabel = strtolower($this->level_label ?? '');
        if (str_contains($levelLabel, 'pengurus yayasan') || str_contains($levelLabel, 'yayasan')) {
            return true;
        }

        return false;
    }

    // Relations
    public function unitSekolah()
    {
        return $this->belongsTo(EducationUnit::class, 'unit_sekolah_id');
    }

    public function atasanLangsung()
    {
        return $this->belongsTo(Position::class, 'atasan_langsung_id');
    }

    /**
     * Atasan Langsung berupa Pegawai tertentu (untuk pelaporan).
     */
    public function atasanPegawai()
    {
        return $this->belongsTo(Employee::class, 'atasan_pegawai_id');
    }

    public function bawahan()
    {
        return $this->hasMany(Position::class, 'atasan_langsung_id');
    }

    public function roleSistem()
    {
        return $this->belongsTo(Role::class, 'role_sistem_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'jabatan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($sq) use ($search) {
                $sq->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        });

        $query->when($filters['unit_sekolah_id'] ?? null, function ($q, $unitId) {
            $q->where('unit_sekolah_id', $unitId);
        });

        $query->when($filters['satuan_kerja'] ?? null, function ($q, $satuanKerja) {
            $q->where('satuan_kerja', $satuanKerja);
        });

        $query->when($filters['level_jabatan'] ?? null, function ($q, $level) {
            $q->where('level_jabatan', (int) $level);
        });

        $query->when(isset($filters['status']), function ($q) use ($filters) {
            $status = $filters['status'];
            if ($status === 'Aktif' || $status === 'aktif' || $status === 'true' || $status === '1') {
                $q->where('is_active', true);
            } elseif ($status === 'Nonaktif' || $status === 'nonaktif' || $status === 'false' || $status === '0') {
                $q->where('is_active', false);
            }
        });

        $query->when($filters['dengan_sampah'] ?? null, function ($q, $trash) {
            if ($trash === 'hanya' || $trash === 'only') {
                $q->onlyTrashed();
            } elseif ($trash === 'ya' || $trash === 'with' || $trash === '1') {
                $q->withTrashed();
            }
        });

        return $query;
    }
}
