<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TahfizhDailyLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tahfizh_daily_logs';

    protected $fillable = [
        'academic_year_id',
        'semester_id',
        'class_id',
        'student_id',
        'teacher_id',
        'record_date',
        'day_name',
        'tilawah_text',
        'tilawah_baris',
        'hafalan_surah_number',
        'hafalan_surah_name',
        'hafalan_ayah_start',
        'hafalan_ayah_end',
        'hafalan_baris',
        'murajaah_text',
        'murajaah_lembar',
        'audio_url',
        'notes_teacher',
        'notes_parent',
        'signature_teacher',
        'signature_parent',
        'status',
        'metadata',
    ];

    protected $casts = [
        'tilawah_baris' => 'integer',
        'hafalan_surah_number' => 'integer',
        'hafalan_ayah_start' => 'integer',
        'hafalan_ayah_end' => 'integer',
        'hafalan_baris' => 'integer',
        'murajaah_lembar' => 'float',
        'metadata' => 'array',
    ];

    protected $appends = [
        'teacher_name',
        'setoran_type',
        'calculated_juz',
        'kelancaran_label',
        'tajwid_label',
        'makhraj_label',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function classModel()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function teacherUser()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Hitung nomor Juz dari nomor surah dan ayat secara dinamis.
     */
    public static function getJuzFromSurahAndAyah(?int $surah, ?int $ayah = 1): ?int
    {
        if (! $surah) {
            return null;
        }
        $ayah = max(1, $ayah ?? 1);
        $juzStarts = [
            1 => [1, 1],
            2 => [2, 142],
            3 => [2, 253],
            4 => [3, 93],
            5 => [4, 24],
            6 => [4, 148],
            7 => [5, 82],
            8 => [6, 111],
            9 => [7, 88],
            10 => [8, 41],
            11 => [9, 93],
            12 => [11, 6],
            13 => [12, 53],
            14 => [15, 1],
            15 => [17, 1],
            16 => [18, 75],
            17 => [21, 1],
            18 => [23, 1],
            19 => [25, 21],
            20 => [27, 56],
            21 => [29, 46],
            22 => [33, 31],
            23 => [36, 28],
            24 => [39, 32],
            25 => [41, 47],
            26 => [46, 1],
            27 => [51, 31],
            28 => [58, 1],
            29 => [67, 1],
            30 => [78, 1],
        ];
        $juz = 1;
        foreach ($juzStarts as $j => [$s, $a]) {
            if ($surah > $s || ($surah === $s && $ayah >= $a)) {
                $juz = $j;
            }
        }
        return $juz;
    }

    public function getTeacherNameAttribute(): ?string
    {
        if ($this->relationLoaded('teacher') && $this->teacher) {
            if ($this->teacher->relationLoaded('user') && $this->teacher->user?->name) {
                return $this->teacher->user->name;
            }
            if ($this->teacher->full_name) {
                return $this->teacher->full_name;
            }
        }

        if ($this->relationLoaded('employee') && $this->employee?->nama_lengkap) {
            return $this->employee->nama_lengkap;
        }

        if ($this->relationLoaded('teacherUser') && $this->teacherUser?->name) {
            return $this->teacherUser->name;
        }

        return $this->signature_teacher ?? null;
    }

    public function getSetoranTypeAttribute(): string
    {
        if (! empty($this->metadata['type'])) {
            return (string) $this->metadata['type'];
        }
        if ($this->murajaah_lembar > 0 && empty($this->hafalan_surah_number)) {
            return 'Murajaah';
        }
        if (! empty($this->hafalan_surah_number)) {
            return 'Ziyadah';
        }
        if ($this->murajaah_lembar > 0 || ! empty($this->murajaah_text)) {
            return 'Murajaah';
        }
        if (! empty($this->tilawah_text)) {
            return 'Tilawah';
        }
        return 'Setoran';
    }

    public function getCalculatedJuzAttribute(): ?int
    {
        if (! empty($this->metadata['juz'])) {
            return (int) $this->metadata['juz'];
        }
        if (! empty($this->hafalan_surah_number)) {
            return self::getJuzFromSurahAndAyah($this->hafalan_surah_number, $this->hafalan_ayah_start ?: 1);
        }
        $combinedText = ($this->murajaah_text ?: '') . ' ' . ($this->tilawah_text ?: '') . ' ' . ($this->surah ?: '');
        if (preg_match('/\bJuz\s*([0-9]{1,2})\b/i', $combinedText, $m)) {
            $num = (int) $m[1];
            if ($num >= 1 && $num <= 30) {
                return $num;
            }
        }
        return null;
    }

    public function getKelancaranLabelAttribute(): ?string
    {
        if (! empty($this->metadata['kelancaran'])) {
            return (string) $this->metadata['kelancaran'];
        }
        if (! empty($this->notes_teacher) && preg_match('/Kelancaran:\s*([^,|\]]+)/i', $this->notes_teacher, $matches)) {
            $val = trim($matches[1]);
            if ($val !== '') {
                return $val;
            }
        }
        if (! empty($this->status)) {
            $statusMap = [
                'lancar' => 'Lancar',
                'sangat_lancar' => 'Sangat Lancar',
                'kurang_lancar' => 'Kurang Lancar',
                'belum_lancar' => 'Belum Lancar',
                'perlu_bimbingan' => 'Perlu Bimbingan',
                'mumtaz' => 'Sangat Lancar (Mumtaz)',
                'jayyid' => 'Baik (Jayyid)',
                'makbul' => 'Cukup (Makbul)',
                'approved' => 'Terverifikasi',
                'verified' => 'Terverifikasi',
                'submitted' => 'Menunggu Evaluasi',
            ];
            $key = strtolower(trim((string) $this->status));
            return $statusMap[$key] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
        }
        return null;
    }

    public function getTajwidLabelAttribute(): ?string
    {
        if (! empty($this->metadata['tajwid'])) {
            return (string) $this->metadata['tajwid'];
        }
        if (! empty($this->notes_teacher)) {
            if (preg_match('/Tajwid:\s*([^,|\]]+)/i', $this->notes_teacher, $matches)) {
                $val = trim($matches[1]);
                if ($val !== '') {
                    return $val;
                }
            }
            if (preg_match('/tajwid.*?baik|mumtaz/i', $this->notes_teacher)) {
                return 'Baik';
            }
            if (preg_match('/tajwid.*?kurang|perlu/i', $this->notes_teacher)) {
                return 'Perlu Bimbingan';
            }
        }
        return null;
    }

    public function getMakhrajLabelAttribute(): ?string
    {
        if (! empty($this->metadata['makhraj'])) {
            return (string) $this->metadata['makhraj'];
        }
        if (! empty($this->notes_teacher)) {
            if (preg_match('/Makhraj:\s*([^,|\]]+)/i', $this->notes_teacher, $matches)) {
                $val = trim($matches[1]);
                if ($val !== '') {
                    return $val;
                }
            }
            if (preg_match('/makhraj.*?baik|makharijul.*?tepat/i', $this->notes_teacher)) {
                return 'Baik';
            }
            if (preg_match('/makhraj.*?kurang|perlu/i', $this->notes_teacher)) {
                return 'Perlu Bimbingan';
            }
        }
        return null;
    }
}
