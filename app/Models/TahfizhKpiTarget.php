<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TahfizhKpiTarget extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $table = 'tahfizh_kpi_targets';

    protected $fillable = [
        'education_unit_id',
        'unit_level',
        'grade_level',
        'program_type',
        'target_name',
        'target_juz',
        'target_surahs',
        'target_ayats',
        'minimum_kkm_ayats',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_surahs' => 'integer',
            'target_ayats' => 'integer',
            'minimum_kkm_ayats' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function educationUnit(): BelongsTo
    {
        return $this->belongsTo(EducationUnit::class, 'education_unit_id');
    }

    /**
     * Cari target tahfizh yang paling cocok untuk siswa tertentu berdasarkan Unit, Jenjang, dan Tingkat Kelas.
     */
    public static function resolveTargetForStudent($student): ?self
    {
        if (! $student) {
            return self::where('is_active', true)->where('grade_level', 'ALL')->first();
        }

        $unitId = $student->education_unit_id ?? $student->unit_id ?? null;
        $unit = $student->educationUnit ?? ($unitId ? EducationUnit::find($unitId) : null);
        $unitLevel = $unit?->level;
        $programType = $unit?->program_type ?? 'fullday';

        $gradeLevel = null;
        if (! empty($student->kelas)) {
            $gradeLevel = (string) ($student->kelas->level ?? $student->kelas->tingkat ?? '');
        } elseif (! empty($student->kelas_id)) {
            $class = SchoolClass::find($student->kelas_id);
            $gradeLevel = (string) ($class?->level ?? '');
        }

        // 1. Coba cari target spesifik unit_id dan grade_level
        if ($unitId && $gradeLevel) {
            $match = self::where('is_active', true)
                ->where('education_unit_id', $unitId)
                ->where('grade_level', $gradeLevel)
                ->first();
            if ($match) return $match;
        }

        // 2. Coba cari target spesifik unit_level dan grade_level
        if ($unitLevel && $gradeLevel) {
            $match = self::where('is_active', true)
                ->where('unit_level', $unitLevel)
                ->where('grade_level', $gradeLevel)
                ->first();
            if ($match) return $match;
        }

        // 3. Coba cari target unit_id dengan grade_level ALL
        if ($unitId) {
            $match = self::where('is_active', true)
                ->where('education_unit_id', $unitId)
                ->whereIn('grade_level', ['ALL', 'all', '', null])
                ->first();
            if ($match) return $match;
        }

        // 4. Coba cari target unit_level dengan grade_level ALL
        if ($unitLevel) {
            $match = self::where('is_active', true)
                ->where('unit_level', $unitLevel)
                ->whereIn('grade_level', ['ALL', 'all', '', null])
                ->first();
            if ($match) return $match;
        }

        // 5. Coba cari target berdasarkan program_type (boarding vs fullday)
        if ($programType === 'boarding') {
            $match = self::where('is_active', true)
                ->where('program_type', 'boarding')
                ->first();
            if ($match) return $match;
        }

        // 6. Fallback ke target aktif pertama
        return self::where('is_active', true)->first();
    }
}
