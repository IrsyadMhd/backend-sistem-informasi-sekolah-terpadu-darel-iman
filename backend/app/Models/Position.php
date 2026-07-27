<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Position extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'positions';

    public const LEVEL_JABATAN_MAP = [
        1 => 'Ketua Yayasan',
        2 => 'Pengurus Yayasan',
        3 => 'Kepala Sekolah',
        4 => 'Wakil Kepala Sekolah',
        5 => 'Kepala Divisi',
        6 => 'Kepala Tata Usaha',
        7 => 'Operator Sekolah',
        8 => 'Bendahara',
        9 => 'Guru',
        10 => 'Wali Kelas',
        11 => 'Pembimbing Tahfizh',
        12 => 'Staf Administrasi',
        13 => 'Satpam',
        14 => 'Cleaning Service',
    ];

    protected $fillable = [
        'code',
        'name',
        'unit_sekolah_id',
        'level_jabatan',
        'atasan_langsung_id',
        'atasan_pegawai_id',
        'role_sistem_id',
        'urutan',
        'warna',
        'ikon',
        'deskripsi',
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
        $last = static::withTrashed()
            ->where('code', 'LIKE', 'JBT-%')
            ->orderByRaw("CAST(SUBSTRING(code FROM '[0-9]+') AS INTEGER) DESC")
            ->first();

        if (!$last) {
            return 'JBT-001';
        }

        $num = (int) preg_replace('/[^0-9]/', '', $last->code);
        $next = $num + 1;

        return 'JBT-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    // Accessor level label
    public function getLevelLabelAttribute(): string
    {
        return static::LEVEL_JABATAN_MAP[$this->level_jabatan] ?? "Level {$this->level_jabatan}";
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
