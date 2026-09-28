<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Helpers\QuranJuzHelper;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Services\TahfizhAchievementService;
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
     * Pastikan user memiliki wewenang untuk mengakses data tahfizh siswa target.
     */
    protected function assertStudentAccess(Request $request, string $studentId): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $accessScope = app(\App\Services\AccessScopeService::class);
        if ($accessScope->hasGlobalScope($user)) {
            return;
        }

        $isAccessible = $accessScope->accessibleStudents($user)
            ->where('students.id', $studentId)
            ->exists();

        abort_unless($isAccessible, 403, 'Akses data tahfizh siswa ditolak: berada di luar wewenang akun.');
    }

    /**
     * Ambil data lembar formulir 7 hari (Senin - Ahad) untuk siswa tertentu.
     */
    public function getWeeklySheet(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required',
            'start_date' => 'nullable|date',
        ]);

        $studentId = (string) $request->query('student_id');
        $this->assertStudentAccess($request, $studentId);
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
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Siswa dan Alumni dilarang mencatat setoran sekolah secara langsung
        $userRoleNames = $user->roles->pluck('name')->map(fn ($r) => strtolower($r))->toArray();
        $isStudentOrAlumni = collect($userRoleNames)->contains(fn ($r) => in_array($r, ['siswa', 'student', 'alumni']));
        abort_if($isStudentOrAlumni, 403, 'Siswa / Alumni tidak memiliki wewenang untuk memverifikasi setoran tahfizh.');

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

            // Tugas Murajaah di Rumah dari Guru
            'murajaah_required' => 'nullable|boolean',
            'murajaah_required_surah_number' => 'nullable|integer|between:1,114',
            'murajaah_required_surah_name' => 'nullable|string',
            'murajaah_required_ayah_start' => 'nullable|integer|min:1',
            'murajaah_required_ayah_end' => 'nullable|integer|min:1',

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

        $this->assertStudentAccess($request, (string) $validated['student_id']);

        // Resolve teacher_id secara aman dari user context
        $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
        $employee = \App\Models\Employee::where('user_id', $user->id)->first();
        $assignedTeacherId = $teacher?->id ?? $employee?->id ?? $validated['teacher_id'] ?? null;
        $validated['teacher_id'] = $assignedTeacherId;
        $validated['signature_teacher'] = $validated['signature_teacher'] ?? ($teacher?->full_name ?? $employee?->nama_lengkap ?? $user->name);

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

            // Cegah melompat jika siswa masih ada kewajiban Murajaah Rumah yang belum diverifikasi
            $activeMurajaah = TahfizhDailyLog::where('student_id', $validated['student_id'])
                ->where('murajaah_required', true)
                ->whereIn('murajaah_status', ['pending_home', 'submitted_by_parent'])
                ->where('record_date', '!=', $validated['record_date'])
                ->first();

            if ($activeMurajaah) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Siswa memiliki kewajiban Murajaah di Rumah yang belum diverifikasi: Surah {$activeMurajaah->murajaah_required_surah_name} (Ayat {$activeMurajaah->murajaah_required_ayah_start}-{$activeMurajaah->murajaah_required_ayah_end}). Harap selesaikan dan verifikasi murajaah terlebih dahulu sebelum melanjutkan hafalan baru.",
                ], 422);
            }
        }

        // Kalkulasi Juz otomatis
        $juzNumber = null;
        if (!empty($validated['hafalan_surah_number']) && !empty($validated['hafalan_ayah_start'])) {
            $juzNumber = QuranJuzHelper::getJuz((int) $validated['hafalan_surah_number'], (int) $validated['hafalan_ayah_start']);
        }

        $murajaahStatus = 'none';
        if (!empty($validated['murajaah_required'])) {
            $murajaahStatus = 'pending_home';
        }

        $existingLog = TahfizhDailyLog::where('student_id', $validated['student_id'])
            ->where('record_date', $validated['record_date'])
            ->where(function ($q) {
                $q->whereNull('metadata->submitted_by_parent')
                  ->orWhere('metadata->submitted_by_parent', false);
            })
            ->first();

        $metadata = $existingLog && is_array($existingLog->metadata) ? $existingLog->metadata : [];
        if ($juzNumber) {
            $metadata['juz'] = $juzNumber;
        }

        $saveData = array_merge($validated, [
            'day_name' => $validated['day_name'] ?? Carbon::parse($validated['record_date'])->locale('id')->isoFormat('dddd'),
            'murajaah_status' => $murajaahStatus,
            'metadata' => $metadata,
            'status' => 'approved', // Setoran langsung dengan guru di sekolah berstatus approved
            'updated_at' => now(),
        ]);

        if ($existingLog) {
            $existingLog->update($saveData);
            $log = $existingLog;
        } else {
            $log = TahfizhDailyLog::create($saveData);
        }

        // Sinkronisasi capaian per-ayat siswa
        $student = Student::find($validated['student_id']);
        if ($student) {
            try {
                app(TahfizhAchievementService::class)->synchronize($student);
            } catch (\Throwable $e) {
                // Ignore sync error if formula not found
            }
        }

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
        $this->assertStudentAccess($request, (string) $studentId);

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
        $query = TahfizhDailyLog::with([
            'student.educationUnit',
            'student.kelas.unitPendidikan',
            'schoolClass.unitPendidikan',
            'teacher.user',
            'teacher.employee',
            'employee.user',
            'teacherUser',
        ]);

        $user = $request->user();
        if ($user) {
            $accessScope = app(\App\Services\AccessScopeService::class);
            if (!$accessScope->hasGlobalScope($user)) {
                $userRoleNames = $user->roles->pluck('name')->map(fn ($r) => strtolower($r))->toArray();
                $isMusyrif = collect($userRoleNames)->contains(fn ($r) => in_array($r, ['musyrif', 'musyrifah', 'pengasuh', 'wali asrama', 'pembimbing asrama']));

                if ($isMusyrif) {
                    $ponpesUnitIds = \App\Models\EducationUnit::query()
                        ->where(function ($q) {
                            $q->where('name', 'like', '%ponpes%')
                              ->orWhere('name', 'like', '%pesantren%')
                              ->orWhere('name', 'like', '%mahad%')
                              ->orWhere('code', 'like', '%PONPES%')
                              ->orWhere('code', 'like', '%MAHAD%');
                        })
                        ->pluck('id');
                    $query->whereHas('student', function ($q) use ($ponpesUnitIds) {
                        $q->whereIn('unit_id', $ponpesUnitIds);
                    });
                } else {
                    $accessibleStudentIds = $accessScope->accessibleStudents($user)->pluck('id');
                    $query->whereIn('student_id', $accessibleStudentIds);
                }
            }
        }

        if ($request->filled('unit_id')) {
            $unitId = (string) $request->query('unit_id');
            $query->where(function ($q) use ($unitId) {
                $q->whereHas('student', function ($sq) use ($unitId) {
                    $sq->where('unit_id', $unitId);
                })->orWhereHas('schoolClass', function ($cq) use ($unitId) {
                    $cq->where('unit_pendidikan_id', $unitId);
                });
            });
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $classId = (string) $request->query('class_id');
            $query->where(function ($q) use ($classId) {
                $q->where('class_id', $classId)
                  ->orWhereHas('student', function ($sq) use ($classId) {
                      $sq->where('kelas_id', $classId)
                         ->orWhere('class_id', $classId);
                  });
            });
        }

        if ($request->filled('type')) {
            $type = (string) $request->query('type');
            if ($type === 'Ziyadah') {
                $query->where(function ($q) {
                    $q->where('metadata->type', 'Ziyadah')
                      ->orWhere(function ($sq) {
                          $sq->whereNull('metadata->type')
                             ->whereNotNull('hafalan_surah_number');
                      });
                });
            } elseif ($type === 'Murajaah') {
                $query->where(function ($q) {
                    $q->where('metadata->type', 'Murajaah')
                      ->orWhere(function ($sq) {
                          $sq->whereNull('metadata->type')
                             ->whereNull('hafalan_surah_number')
                             ->where(function ($mq) {
                                 $mq->where('murajaah_lembar', '>', 0)
                                    ->orWhereNotNull('murajaah_text');
                             });
                      });
                });
            } else {
                $query->where('metadata->type', $type);
            }
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

        $perPage = min(max((int) $request->query('per_page', 150), 15), 500);
        $logs = (clone $query)->orderByDesc('record_date')->limit($perPage)->get();

        $totalHafalanBaris = (int) (clone $query)->sum('hafalan_baris');
        $totalTilawahBaris = (int) (clone $query)->sum('tilawah_baris');
        $totalMurajaahLembar = (float) (clone $query)->sum('murajaah_lembar');
        $totalSiswaSertifikat = (int) (clone $query)->distinct('student_id')->count('student_id');
        $totalLogs = (int) (clone $query)->count();

        $totalZiyadah = (clone $query)->where(function ($q) {
            $q->where('metadata->type', 'Ziyadah')
              ->orWhere(function ($sq) {
                  $sq->whereNull('metadata->type')->whereNotNull('hafalan_surah_number');
              });
        })->count();

        $totalMurajaah = (clone $query)->where(function ($q) {
            $q->where('metadata->type', 'Murajaah')
              ->orWhere(function ($sq) {
                  $sq->whereNull('metadata->type')
                     ->whereNull('hafalan_surah_number')
                     ->where(function ($mq) {
                         $mq->where('murajaah_lembar', '>', 0)->orWhereNotNull('murajaah_text');
                     });
              });
        })->count();

        $totalTasmi = (clone $query)->where('metadata->type', 'Tasmi')->count();
        $totalUjian = (clone $query)->where('metadata->type', 'Ujian')->count();

        // Target tahunan dihitung dinamis berdasarkan jumlah siswa aktif * target baris kurikulum
        $targetTahunan = max($totalSiswaSertifikat * 300, $totalHafalanBaris > 0 ? $totalHafalanBaris : 1);
        $persentase = $targetTahunan > 0 ? round(($totalHafalanBaris / $targetTahunan) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Rekap tahfizh berhasil diambil.',
            'summary' => [
                'total_logs' => $totalLogs,
                'total_siswa' => $totalSiswaSertifikat,
                'total_hafalan_baris' => $totalHafalanBaris,
                'total_tilawah_baris' => $totalTilawahBaris,
                'total_murajaah_lembar' => $totalMurajaahLembar,
                'total_ziyadah' => $totalZiyadah,
                'total_murajaah' => $totalMurajaah,
                'total_tasmi' => $totalTasmi,
                'total_ujian' => $totalUjian,
                'target_tahunan' => $targetTahunan,
                'persentase' => $persentase,
            ],
            'data' => $logs,
        ]);
    }

    /**
     * Ambil data hafalan terakhir yang sah/terverifikasi untuk seorang siswa
     * dan tentukan surah & rentang ayat kelanjutan otomatis berikutnya.
     */
    public function getLastHafalan(Request $request, string $studentId): JsonResponse
    {
        $this->assertStudentAccess($request, $studentId);

        // 1. Cek apakah siswa memiliki tugas Murajaah di Rumah yang masih pending/belum diverifikasi
        $pendingMurajaah = TahfizhDailyLog::where('student_id', $studentId)
            ->where('murajaah_required', true)
            ->whereIn('murajaah_status', ['pending_home', 'submitted_by_parent'])
            ->orderByDesc('record_date')
            ->first();

        // 2. Cari log Ziyadah (Hafalan Baru) terakhir dari guru
        $latestLog = TahfizhDailyLog::where('student_id', $studentId)
            ->whereNotNull('hafalan_surah_number')
            ->whereNotNull('hafalan_ayah_start')
            ->whereNotNull('hafalan_ayah_end')
            ->where(function ($q) {
                $q->whereNull('metadata->submitted_by_parent')
                  ->orWhere('metadata->submitted_by_parent', false);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['rejected', 'needs_revision']);
            })
            ->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->first();

        $allSurahs = QuranSurah::orderBy('nomor')->get()->keyBy('nomor');

        if (! $latestLog) {
            // Belum pernah setor hafalan: default mulai dari Surah 1 (Al-Fatihah) Ayat 1
            $firstSurah = $allSurahs->get(1) ?? $allSurahs->first();
            return response()->json([
                'status' => 'success',
                'data' => [
                    'student_id' => $studentId,
                    'is_first_hafalan' => true,
                    'last_surah_number' => null,
                    'last_surah_name' => null,
                    'last_ayah_start' => null,
                    'last_ayah_end' => null,
                    'next_surah_number' => (int) ($firstSurah ? $firstSurah->nomor : 1),
                    'next_surah_name' => $firstSurah ? ($firstSurah->nama_latin ?? $firstSurah->nama) : "Al-Fatihah",
                    'next_surah_total_ayah' => (int) ($firstSurah ? $firstSurah->jumlah_ayat : 7),
                    'next_ayah_start' => 1,
                    'next_juz_number' => 1,
                    'is_ayah_start_locked' => true,
                    'has_pending_murajaah' => (bool) $pendingMurajaah,
                    'pending_murajaah_info' => $pendingMurajaah ? [
                        'id' => $pendingMurajaah->id,
                        'surah_number' => $pendingMurajaah->murajaah_required_surah_number,
                        'surah_name' => $pendingMurajaah->murajaah_required_surah_name,
                        'ayah_start' => $pendingMurajaah->murajaah_required_ayah_start,
                        'ayah_end' => $pendingMurajaah->murajaah_required_ayah_end,
                        'status' => $pendingMurajaah->murajaah_status,
                    ] : null,
                ],
            ]);
        }

        $lastSurahNum = (int) $latestLog->hafalan_surah_number;
        $lastAyahEnd = (int) $latestLog->hafalan_ayah_end;
        $currentSurah = $allSurahs->get($lastSurahNum);
        $totalAyatCurrent = $currentSurah ? (int) $currentSurah->jumlah_ayat : 7;

        $nextSurahNum = $lastSurahNum;
        $nextAyahStart = $lastAyahEnd + 1;

        // Jika surah saat ini sudah tamat (ayat terakhir >= total ayat surah)
        if ($nextAyahStart > $totalAyatCurrent) {
            $nextSurahNum = min(114, $lastSurahNum + 1);
            $nextAyahStart = 1;
        }

        $targetNextSurah = $allSurahs->get($nextSurahNum) ?? $currentSurah;
        $nextJuz = QuranJuzHelper::getJuz($nextSurahNum, $nextAyahStart);

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $studentId,
                'is_first_hafalan' => false,
                'last_surah_number' => $lastSurahNum,
                'last_surah_name' => $latestLog->hafalan_surah_name,
                'last_ayah_start' => (int) $latestLog->hafalan_ayah_start,
                'last_ayah_end' => $lastAyahEnd,
                'next_surah_number' => $nextSurahNum,
                'next_surah_name' => $targetNextSurah ? ($targetNextSurah->nama_latin ?? $targetNextSurah->nama) : "Surah {$nextSurahNum}",
                'next_surah_total_ayah' => $targetNextSurah ? (int) $targetNextSurah->jumlah_ayat : 286,
                'next_ayah_start' => $nextAyahStart,
                'next_juz_number' => $nextJuz,
                'is_ayah_start_locked' => true,
                'has_pending_murajaah' => (bool) $pendingMurajaah,
                'pending_murajaah_info' => $pendingMurajaah ? [
                    'id' => $pendingMurajaah->id,
                    'surah_number' => $pendingMurajaah->murajaah_required_surah_number,
                    'surah_name' => $pendingMurajaah->murajaah_required_surah_name,
                    'ayah_start' => $pendingMurajaah->murajaah_required_ayah_start,
                    'ayah_end' => $pendingMurajaah->murajaah_required_ayah_end,
                    'status' => $pendingMurajaah->murajaah_status,
                ] : null,
            ],
        ]);
    }
}
