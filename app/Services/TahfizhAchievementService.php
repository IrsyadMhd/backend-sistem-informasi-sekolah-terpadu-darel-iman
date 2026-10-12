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

        DB::transaction(function () use ($student, $logs) {
            foreach ($logs as $log) {
                $surah = QuranSurah::where('nomor', $log->hafalan_surah_number)->first();
                if (! $surah) continue;
                $start = max(1, (int) $log->hafalan_ayah_start);
                $end = min((int) $surah->jumlah_ayat, (int) $log->hafalan_ayah_end);
                $classId = $log->class_id && Kelas::whereKey($log->class_id)->exists() ? $log->class_id : null;
                for ($ayah = $start; $ayah <= $end; $ayah++) {
                    $ayahJuz = \App\Helpers\QuranJuzHelper::getJuz((int) $surah->nomor, $ayah);
                    TahfizhAyahAchievement::firstOrCreate(
                        ['student_id' => $student->id, 'surah_number' => $surah->nomor, 'ayah_number' => $ayah],
                        ['academic_year_id' => $log->academic_year_id, 'semester_id' => $log->semester_id,
                            'class_id' => $classId, 'source_log_id' => $log->id, 'juz_number' => $ayahJuz,
                            'status' => 'validated', 'validated_at' => $log->updated_at ?? $log->created_at,
                            'metadata' => ['source' => 'tahfizh_daily_log']]
                    );
                }
            }
        });
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
        $achievement = $targetAyat > 0 ? min(100, round(($rows->count() / $targetAyat) * 100, 2)) : null;
        $kelas = $student->kelas;
        $formula = $kelas?->tahun_ajaran_id ? $this->formulas->resolveActive('tahfizh', $kelas->tahun_ajaran_id, $kelas->semester_id, $kelas->unit_pendidikan_id, $kelas->id) : null;

        return [
            'student_id' => $student->id,
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
