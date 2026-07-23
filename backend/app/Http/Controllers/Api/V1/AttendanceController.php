<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\AbsensiCheckinRequest;
use App\Http\Requests\V1\AbsensiCheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller Absensi Digital.
 *
 * Petunjuk fungsi:
 * - absenMasuk()    : endpoint check-in siswa
 * - absenPulang()   : endpoint check-out siswa
 * - rekapKehadiran(): endpoint laporan absensi
 */
class AttendanceController extends Controller
{
    public function absenMasuk(AbsensiCheckinRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tanggal = $data['attendance_date'] ?? now()->toDateString();
        $bulan = (int) now()->month;

        $id = DB::table('attendances')->insertGetId([
            'academic_year_id' => $data['academic_year_id'],
            'semester_id' => $data['semester_id'],
            'month' => $bulan,
            'attendance_date' => $tanggal,
            'student_id' => $data['student_id'],
            'class_id' => $data['class_id'],
            'check_in_time' => now(),
            'status' => $data['status'] ?? 'present',
            'attendance_method' => $data['attendance_method'],
            'location' => $data['location'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id');

        return response()->json([
            'message' => 'Absen masuk berhasil disimpan.',
            'attendance_id' => $id,
        ], 201);
    }

    public function absenPulang(AbsensiCheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attendance = DB::table('attendances')->where('id', $data['attendance_id'])->first();
        if (! $attendance) {
            return response()->json([
                'message' => 'Data absensi tidak ditemukan.',
            ], 404);
        }

        DB::table('attendances')
            ->where('id', $data['attendance_id'])
            ->update([
                'check_out_time' => now(),
                'location' => $data['location'] ?? $attendance->location,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : $attendance->metadata,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Absen pulang berhasil disimpan.',
        ]);
    }

    public function rekapKehadiran(Request $request): JsonResponse
    {
        $query = DB::table('attendances');

        if ($request->filled('student_id')) {
            $query->where('student_id', (string) $request->query('student_id'));
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', (string) $request->query('class_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('attendance_date', '>=', (string) $request->query('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('attendance_date', '<=', (string) $request->query('end_date'));
        }

        $data = $query->orderByDesc('attendance_date')->limit(100)->get();

        return response()->json([
            'message' => 'Rekap kehadiran berhasil diambil.',
            'data' => $data,
        ]);
    }
}
