<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuranSurah;
use App\Models\TahfizhDailyLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TahfizhController extends Controller
{
    /**
     * Ambil data lembar formulir 7 hari (Senin - Ahad) untuk siswa tertentu.
     */
    public function getWeeklySheet(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required',
            'start_date' => 'nullable|date',
        ]);

        $studentId = $request->query('student_id');
        $startDate = $request->query('start_date') 
            ? Carbon::parse($request->query('start_date'))->startOfWeek(Carbon::MONDAY) 
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $daysIndo = [
            0 => 'Senin',
            1 => 'Selasa',
            2 => 'Rabu',
            3 => 'Kamis',
            4 => 'Jumat',
            5 => 'Sabtu',
            6 => 'Ahad',
        ];

        $weekDates = [];
        for ($i = 0; $i < 7; $i++) {
            $dateObj = (clone $startDate)->addDays($i);
            $dateStr = $dateObj->toDateString();
            $weekDates[$dateStr] = [
                'record_date' => $dateStr,
                'day_name' => $daysIndo[$i],
                'day_index' => $i + 1,
                'log' => null,
            ];
        }

        $existingLogs = TahfizhDailyLog::where('student_id', $studentId)
            ->whereBetween('record_date', [$startDate->toDateString(), (clone $startDate)->addDays(6)->toDateString()])
            ->get()
            ->keyBy('record_date');

        foreach ($weekDates as $dateStr => &$item) {
            if (isset($existingLogs[$dateStr])) {
                $item['log'] = $existingLogs[$dateStr];
            }
        }

        // Ambil rekap catatan umum mingguan jika ada di log terakhir
        $latestLog = $existingLogs->sortByDesc('record_date')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentId,
                'start_date' => $startDate->toDateString(),
                'end_date' => (clone $startDate)->addDays(6)->toDateString(),
                'days' => array_values($weekDates),
                'notes_teacher_summary' => $latestLog ? $latestLog->notes_teacher : '',
                'notes_parent_summary' => $latestLog ? $latestLog->notes_parent : '',
            ],
        ]);
    }

    /**
     * Simpan / update entri log harian Tahfizh & Murajaah.
     */
    public function saveDailyLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required',
            'record_date' => 'required|date',
            'day_name' => 'nullable|string',
            'class_id' => 'nullable',
            'teacher_id' => 'nullable',
            'academic_year_id' => 'nullable',
            'semester_id' => 'nullable',

            // Tilawah (Guru input)
            'tilawah_text' => 'nullable|string',
            'tilawah_baris' => 'nullable|integer',

            // Hafalan Baru (Dari Master Al-Qur'an)
            'hafalan_surah_number' => 'nullable|integer|between:1,114',
            'hafalan_surah_name' => 'nullable|string',
            'hafalan_ayah_start' => 'nullable|integer|min:1',
            'hafalan_ayah_end' => 'nullable|integer|min:1',
            'hafalan_baris' => 'nullable|integer',

            // Murajaah (Ortu input)
            'murajaah_text' => 'nullable|string',
            'murajaah_lembar' => 'nullable|numeric',
            'audio_url' => 'nullable|string',

            // Catatan & Ttd
            'notes_teacher' => 'nullable|string',
            'notes_parent' => 'nullable|string',
            'signature_teacher' => 'nullable|string',
            'signature_parent' => 'nullable|string',
        ]);

        if (!empty($validated['hafalan_ayah_start']) && !empty($validated['hafalan_ayah_end'])) {
            if ((int)$validated['hafalan_ayah_start'] > (int)$validated['hafalan_ayah_end']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ayat awal tidak boleh lebih besar dari ayat akhir.',
                ], 422);
            }
        }

        if (!empty($validated['hafalan_surah_number'])) {
            $surah = QuranSurah::where('nomor', $validated['hafalan_surah_number'])->first();
            if ($surah) {
                if (!empty($validated['hafalan_ayah_end']) && (int)$validated['hafalan_ayah_end'] > $surah->jumlah_ayat) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Ayat akhir melebihi jumlah ayat Surah {$surah->nama_latin} ({$surah->jumlah_ayat} ayat).",
                    ], 422);
                }
                if (!empty($validated['hafalan_ayah_start']) && (int)$validated['hafalan_ayah_start'] > $surah->jumlah_ayat) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Ayat awal melebihi jumlah ayat Surah {$surah->nama_latin} ({$surah->jumlah_ayat} ayat).",
                    ], 422);
                }
            }
        }

        $log = TahfizhDailyLog::updateOrCreate(
            [
                'student_id' => $validated['student_id'],
                'record_date' => $validated['record_date'],
            ],
            array_merge($validated, [
                'day_name' => $validated['day_name'] ?? Carbon::parse($validated['record_date'])->locale('id')->isoFormat('dddd'),
                'updated_at' => now(),
            ])
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan Tahfizh & Murajaah harian berhasil disimpan.',
            'data' => $log,
        ]);
    }

    /**
     * Unggah rekaman suara audio Murajaah dari orang tua.
     */
    public function uploadAudio(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,m4a,wav,webm,ogg,aac|max:20480', // Maks 20MB
        ]);

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $fileName = 'murajaah_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('tahfizh_audio', $fileName, 'public');

            $audioUrl = Storage::url($path);

            return response()->json([
                'status' => 'success',
                'message' => 'Rekaman suara murajaah berhasil diunggah.',
                'audio_url' => $audioUrl,
                'file_name' => $fileName,
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengunggah file audio.',
        ], 400);
    }

    /**
     * Helper untuk menghitung total ayat unik dari susunan range [start, end] (Interval Merging).
     */
    private function calculateMergedAyatCount(array $ranges): int
    {
        if (empty($ranges)) {
            return 0;
        }

        usort($ranges, fn ($a, $b) => $a['start'] <=> $b['start']);

        $merged = [];
        $current = $ranges[0];

        for ($i = 1; $i < count($ranges); $i++) {
            $next = $ranges[$i];
            if ($next['start'] <= $current['end'] + 1) {
                $current['end'] = max($current['end'], $next['end']);
            } else {
                $merged[] = $current;
                $current = $next;
            }
        }
        $merged[] = $current;

        $totalAyats = 0;
        foreach ($merged as $r) {
            $totalAyats += max($r['end'] - $r['start'] + 1, 0);
        }

        return $totalAyats;
    }

    /**
     * Ambil rekap kemajuan Tahfizh siswa (dihafal vs sisa target 30 Juz / 6236 Ayat).
     */
    public function getStudentProgress(Request $request, $studentId): JsonResponse
    {
        $totalQuranAyats = 6236; // Total ayat dalam 30 Juz Al-Qur'an
        $totalQuranSurahs = 114;

        // Ambil semua setoran hafalan baru siswa yang valid (abaikan murajaah murni)
        $logs = TahfizhDailyLog::where('student_id', $studentId)
            ->whereNotNull('hafalan_surah_number')
            ->whereNotNull('hafalan_ayah_start')
            ->whereNotNull('hafalan_ayah_end')
            ->get();

        $surahsRawRanges = [];

        foreach ($logs as $log) {
            // Jika log ditandai sebagai Murajaah murni di metadata, abaikan dari hafalan baru
            if (isset($log->metadata['type']) && strtolower($log->metadata['type']) === 'murajaah' && empty($log->hafalan_baris)) {
                continue;
            }

            $surahNum = $log->hafalan_surah_number;
            if (!isset($surahsRawRanges[$surahNum])) {
                $surahsRawRanges[$surahNum] = [
                    'surah_number' => $surahNum,
                    'surah_name' => $log->hafalan_surah_name,
                    'ranges' => [],
                ];
            }

            $surahsRawRanges[$surahNum]['ranges'][] = [
                'start' => (int) $log->hafalan_ayah_start,
                'end' => (int) $log->hafalan_ayah_end,
                'date' => $log->record_date,
            ];
        }

        $surahsMemorized = [];
        $totalAyatsMemorized = 0;

        foreach ($surahsRawRanges as $surahNum => $data) {
            $uniqueAyatCount = $this->calculateMergedAyatCount($data['ranges']);
            $surahsMemorized[$surahNum] = [
                'surah_number' => $surahNum,
                'surah_name' => $data['surah_name'],
                'total_ayats' => $uniqueAyatCount,
                'ranges' => $data['ranges'],
            ];
            $totalAyatsMemorized += $uniqueAyatCount;
        }

        $completedSurahsCount = count($surahsMemorized);
        $remainingSurahsCount = max($totalQuranSurahs - $completedSurahsCount, 0);
        $remainingAyatsCount = max($totalQuranAyats - $totalAyatsMemorized, 0);
        $progressPercentage = round(($totalAyatsMemorized / $totalQuranAyats) * 100, 2);

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentId,
                'total_quran_surahs' => $totalQuranSurahs,
                'total_quran_ayats' => $totalQuranAyats,
                'total_ayats_memorized' => $totalAyatsMemorized,
                'remaining_ayats' => $remainingAyatsCount,
                'total_surahs_memorized' => $completedSurahsCount,
                'remaining_surahs' => $remainingSurahsCount,
                'progress_percentage' => min($progressPercentage, 100),
                'surahs_detail' => array_values($surahsMemorized),
            ],
        ]);
    }

    /**
     * Endpoint pendukung legacy (inputSetoran)
     */
    public function inputSetoran(Request $request): JsonResponse
    {
        return $this->saveDailyLog($request);
    }

    /**
     * Endpoint pendukung legacy (rekapTahfizh)
     */
    public function rekapTahfizh(Request $request): JsonResponse
    {
        $query = TahfizhDailyLog::with(['student', 'schoolClass']);

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('record_date', '>=', (string) $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('record_date', '<=', (string) $request->query('end_date'));
        }

        $logs = (clone $query)->orderByDesc('record_date')->limit(150)->get();

        $totalHafalanBaris = (int) (clone $query)->sum('hafalan_baris');
        $totalTilawahBaris = (int) (clone $query)->sum('tilawah_baris');
        $totalMurajaahLembar = (float) (clone $query)->sum('murajaah_lembar');
        $totalSiswaSertifikat = (int) (clone $query)->distinct('student_id')->count('student_id');
        $totalLogs = (int) (clone $query)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap tahfizh berhasil diambil.',
            'summary' => [
                'total_logs' => $totalLogs,
                'total_siswa' => $totalSiswaSertifikat,
                'total_hafalan_baris' => $totalHafalanBaris,
                'total_tilawah_baris' => $totalTilawahBaris,
                'total_murajaah_lembar' => $totalMurajaahLembar,
                'target_tahunan' => 50000,
                'persentase' => $totalHafalanBaris > 0 ? round(($totalHafalanBaris / 50000) * 100, 2) : 0,
            ],
            'data' => $logs,
        ]);
    }
}

