<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TahfizhStoreRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller Tahfizh.
 *
 * Petunjuk fungsi:
 * - inputSetoran() : simpan setoran hafalan
 * - rekapTahfizh() : ambil laporan tahfizh
 */
class TahfizhController extends Controller
{
    public function inputSetoran(TahfizhStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tanggal = $data['record_date'] ?? now()->toDateString();
        $bulan = (int) now()->month;

        $lineCount = isset($data['line_count'])
            ? (int) $data['line_count']
            : max(((int) $data['ayah_end'] - (int) $data['ayah_start']) + 1, 0);

        DB::table('tahfizh_records')->insert([
            'academic_year_id' => $data['academic_year_id'],
            'semester_id' => $data['semester_id'],
            'month' => $bulan,
            'record_date' => $tanggal,
            'student_id' => $data['student_id'],
            'class_id' => $data['class_id'],
            'teacher_id' => $data['teacher_id'],
            'surah_name' => $data['surah_name'],
            'ayah_start' => $data['ayah_start'],
            'ayah_end' => $data['ayah_end'],
            'line_count' => $lineCount,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'submitted',
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Setoran tahfizh berhasil disimpan.',
        ], 201);
    }

    public function rekapTahfizh(Request $request): JsonResponse
    {
        $query = DB::table('tahfizh_records');

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('record_date', '>=', (string) $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('record_date', '<=', (string) $request->query('end_date'));
        }

        $data = $query->orderByDesc('record_date')->limit(100)->get();

        return response()->json([
            'message' => 'Rekap tahfizh berhasil diambil.',
            'data' => $data,
        ]);
    }
}
