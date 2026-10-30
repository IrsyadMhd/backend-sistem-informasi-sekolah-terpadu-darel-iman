<?php

namespace App\Services;

use App\Models\AssessmentFormula;
use App\Models\QuranSurah;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\TahfizhAyahAchievement;
use App\Models\TahfizhDailyLog;
use Illuminate\Support\Facades\DB;

class TahfizhAchievementService
{
    public function __construct(private readonly AssessmentFormulaService $formulas) {}

    public function synchronize(Student $student): void
    {
        $logs = TahfizhDailyLog::query()->where('student_id', $student->id)
            ->whereNotNull('hafalan_surah_number')->whereNotNull('hafalan_ayah_start')->whereNotNull('hafalan_ayah_end')
            ->where(function ($query) {
                $query->whereIn('status', ['approved', 'verified', 'finalized'])
                    ->orWhereNotNull('signature_teacher');
            })
            ->where(function ($query) {
                $query->whereNull('metadata->type')
                    ->orWhereNotIn(DB::raw("LOWER(COALESCE(metadata->>'type', ''))"), ['murajaah', 'murojaah']);
            })
            ->orderBy('record_date')->get();

        if ($logs->isEmpty()) {
            return;
        }

        // Preload all 114 surahs once to eliminate N+1 queries
        $surahs = QuranSurah::all()->keyBy('nomor');

        // Preload existing achievements for this student in a single query
        $existingKeys = TahfizhAyahAchievement::query()
            ->where('student_id', $student->id)
            ->select('surah_number', 'ayah_number')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->surah_number . '-' . $r->ayah_number => true])
            ->all();

        // Pre-validate class IDs in memory
        $classIds = $logs->pluck('class_id')->filter()->unique()->values();
        $validClassIds = $classIds->isNotEmpty()
            ? Kelas::whereIn('id', $classIds)->pluck('id')->flip()->all()
            : [];

        $toInsert = [];
        $now = now();

        foreach ($logs as $log) {
            $surah = $surahs->get($log->hafalan_surah_number);
            if (! $surah) {
                continue;
            }
            $start = max(1, (int) $log->hafalan_ayah_start);
            $end = min((int) $surah->jumlah_ayat, (int) $log->hafalan_ayah_end);
            $classId = ($log->class_id && isset($validClassIds[$log->class_id])) ? $log->class_id : null;
            $validatedAt = $log->updated_at ?? $log->created_at ?? $now;

            for ($ayah = $start; $ayah <= $end; $ayah++) {
                $key = $surah->nomor . '-' . $ayah;
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $existingKeys[$key] = true;
                $ayahJuz = \App\Helpers\QuranJuzHelper::getJuz((int) $surah->nomor, $ayah);

                $toInsert[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'student_id' => $student->id,
                    'surah_number' => $surah->nomor,
                    'ayah_number' => $ayah,
                    'academic_year_id' => $log->academic_year_id,
                    'semester_id' => $log->semester_id,
                    'class_id' => $classId,
                    'source_log_id' => $log->id,
                    'juz_number' => $ayahJuz,
                    'status' => 'validated',
                    'validated_at' => $validatedAt,
                    'metadata' => json_encode(['source' => 'tahfizh_daily_log']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                TahfizhAyahAchievement::insert($chunk);
            }
        }
    }

    public function summary(Student $student): array
    {
        $this->synchronize($student);
        $rows = TahfizhAyahAchievement::where('student_id', $student->id)->where('status', 'validated')->get();
        $surahs = QuranSurah::whereIn('nomor', $rows->pluck('surah_number')->unique())->get()->keyBy('nomor');
        $completedSurahs = $rows->groupBy('surah_number')->filter(function ($items, $number) use ($surahs) {
            return $items->pluck('ayah_number')->unique()->count() >= (int) ($surahs->get($number)?->jumlah_ayat ?? PHP_INT_MAX);
        });
        $completedJuz = $rows->whereNotNull('juz_number')->groupBy('juz_number')->filter(function ($items) {
            return $items->every(fn ($item) => data_get($item->metadata, 'juz_complete') === true);
        })->keys()->values();

        $target = DB::table('memorization_targets')->where('student_id', $student->id)->whereNull('deleted_at')->latest('target_date')->first();
        $targetMetadata = $target?->metadata ?? [];
        if (is_string($targetMetadata)) $targetMetadata = json_decode($targetMetadata, true) ?: [];
        $targetAyat = (int) (data_get($targetMetadata, 'target_ayat') ?: ($target->target_lines ?? 0));
        $targetName = data_get($targetMetadata, 'target_name') ?: ($target?->target_name ?? null);
        $targetJuz = data_get($targetMetadata, 'target_juz');

        if ($targetAyat <= 0) {
            $cls = $student->kelas;
            $grade = $cls?->tingkat;
            $unit = $student->educationUnit ?? $cls?->unitPendidikan;
            $unitLevel = $unit?->level ?? $cls?->jenjang ?? $unit?->jenjang ?? null;
            $eduUnitId = $student->education_unit_id ?? $student->unit_id ?? $cls?->unit_pendidikan_id ?? $unit?->id;

            $levels = array_filter([$unitLevel]);
            if (in_array(strtoupper((string) $unitLevel), ['MAHAD', "MA'HAD", 'PONPES', 'PESANTREN', 'BOARDING'])) {
                $levels = array_unique(array_merge($levels, ['PONPES', 'MAHAD', 'PESANTREN', 'BOARDING']));
            }
            if (in_array(strtoupper((string) $unitLevel), ['TAUD', 'TKIT', 'TK'])) {
                $levels = array_unique(array_merge($levels, ['TKIT', 'TAUD', 'TK']));
            }

            $gradeVariants = $grade ? [(string) $grade, 'ALL'] : ['ALL'];
            if ((int) $grade === 4) $gradeVariants[] = '3';
            if ((int) $grade === 6) $gradeVariants[] = '5';

            $kpiTarget = DB::table('tahfizh_kpi_targets')
                ->where('is_active', true)
                ->where(function ($q) use ($levels, $eduUnitId) {
                    if (!empty($levels)) {
                        $q->whereIn('unit_level', $levels);
                    }
                    if ($eduUnitId) {
                        $q->orWhere('education_unit_id', $eduUnitId);
                    }
                })
                ->whereIn('grade_level', $gradeVariants)
                ->orderByRaw("CASE WHEN grade_level = ? THEN 1 WHEN grade_level IN ('3','5') THEN 2 WHEN grade_level = 'ALL' THEN 3 ELSE 4 END", [(string) ($grade ?? 0)])
                ->first();

            if ($kpiTarget) {
                $targetAyat = (int) ($kpiTarget->target_ayats ?? 0);
                $targetName = $kpiTarget->target_name;
                $targetJuz = $kpiTarget->target_juz;
            }
        }

        $achievement = $targetAyat > 0 ? min(100, round(($rows->count() / $targetAyat) * 100, 2)) : null;
        $kelas = $student->kelas;
        $formula = $kelas?->tahun_ajaran_id ? $this->formulas->resolveActive('tahfizh', $kelas->tahun_ajaran_id, $kelas->semester_id, $kelas->unit_pendidikan_id, $kelas->id) : null;

        return [
            'student_id' => $student->id,
            'target_name' => $targetName,
            'target_juz' => $targetJuz,
            'validated_unique_ayah' => $rows->count(),
            'completed_surahs' => $completedSurahs->keys()->map(fn ($number) => ['number' => (int) $number, 'name' => $surahs->get($number)?->nama_latin])->values(),
            'completed_surah_count' => $completedSurahs->count(),
            'completed_juz' => $completedJuz,
            'completed_juz_count' => $completedJuz->count(),
            'juz_with_validated_ayah' => $rows->whereNotNull('juz_number')->pluck('juz_number')->unique()->sort()->values(),
            'juz_with_validated_ayah_count' => $rows->whereNotNull('juz_number')->pluck('juz_number')->unique()->count(),
            'target_ayah' => $targetAyat ?: null,
            'achievement_percentage' => $achievement,
            'formula' => $formula ? ['id' => $formula->id, 'version' => $formula->version] : null,
            'score' => null,
            'score_note' => $formula ? 'Nilai menunggu komponen kualitas yang lengkap.' : 'Belum ada rumus Tahfizh aktif.',
        ];
    }
}
