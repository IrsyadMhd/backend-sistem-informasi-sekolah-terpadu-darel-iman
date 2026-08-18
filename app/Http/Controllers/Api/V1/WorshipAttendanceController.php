<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use App\Services\WorshipAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorshipAttendanceController extends Controller
{
    public function __construct(private WorshipAttendanceService $worshipService) {}

    /**
     * List all worship attendance templates.
     */
    public function templates(Request $request): JsonResponse
    {
        $query = WorshipAttendanceTemplate::with('educationUnit');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }
        if ($request->filled('unit_id')) {
            $query->where('education_unit_id', $request->query('unit_id'));
        }
        if ($request->filled('gender_scope')) {
            $query->where('gender_scope', $request->query('gender_scope'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->get(),
        ]);
    }

    /**
     * Store a new worship attendance template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:worship_attendance_templates,code',
            'category' => 'required|string|in:shalat_wajib,shalat_sunnah,ibadah_lain,program_asrama',
            'obligation_type' => 'required|string|in:wajib,sunnah,opsional',
            'education_unit_id' => 'nullable|uuid|exists:education_units,id',
            'gender_scope' => 'required|string|in:all,male,female',
            'participant_scope' => 'nullable|string',
            'time_source' => 'required|string|in:fixed,prayer_schedule',
            'prayer_name' => 'nullable|string|in:subuh,zuhur,asar,magrib,isya',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'open_offset_minutes' => 'nullable|integer',
            'iqamah_offset_minutes' => 'nullable|integer',
            'late_tolerance_minutes' => 'nullable|integer',
            'close_offset_minutes' => 'nullable|integer',
            'active_days' => 'nullable|array',
            'location_name' => 'nullable|string',
            'attendance_methods' => 'nullable|array',
            'verification_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = $request->user()?->id;
        $template = WorshipAttendanceTemplate::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Template presensi ibadah berhasil dibuat.',
            'data' => $template,
        ], 201);
    }

    /**
     * List worship attendance sessions for a date.
     */
    public function sessions(Request $request): JsonResponse
    {
        $date = $request->query('date', today()->toDateString());

        // Ensure sessions exist for target date
        $this->worshipService->generateDailySessions($date);

        $query = WorshipAttendanceSession::with(['template', 'supervisor'])
            ->whereDate('session_date', $date);

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->query('template_id'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(),
        ]);
    }

    /**
     * Get details / student checklist for a worship session.
     */
    public function showSession(Request $request, WorshipAttendanceSession $session): JsonResponse
    {
        $session->load(['template', 'supervisor', 'details.student']);

        $user = $request->user();

        // Format details with female privacy masking if user lacks permission
        $formattedDetails = $session->details->map(function ($detail) use ($user) {
            return $this->worshipService->formatDetailForUser($detail, $user);
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'session' => $session->only(['id', 'session_date', 'scheduled_start_at', 'scheduled_end_at', 'status', 'location_name']),
                'template' => $session->template,
                'supervisor' => $session->supervisor,
                'details' => $formattedDetails,
            ],
        ]);
    }

    /**
     * Submit scan attendance for santri worship.
     */
    public function scan(Request $request, WorshipAttendanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'nullable|uuid',
            'card_number' => 'nullable|string',
            'method' => 'nullable|string|in:qr,rfid,face,checklist',
            'notes' => 'nullable|string',
        ]);

        $result = $this->worshipService->recordWorshipScan($session, $validated, $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Verify / batch update santri attendance for a worship session (Musyrif / Musyrifah).
     */
    public function verifyStudent(Request $request, WorshipAttendanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|uuid|exists:students,id',
            'attendance_status' => 'required|string|in:hadir_berjamaah,hadir_sendiri,terlambat,tidak_hadir,izin,sakit,uzur_syarii,haid,tugas,piket,safar,dispensasi,perangkat_bermasalah,belum_diverifikasi',
            'notes' => 'nullable|string',
        ]);

        $result = $this->worshipService->verifyStudentWorship(
            $session,
            $validated['student_id'],
            $validated,
            $request->user()
        );

        return response()->json($result);
    }

    /**
     * Close worship session.
     */
    public function closeSession(WorshipAttendanceSession $session): JsonResponse
    {
        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi presensi ibadah berhasil ditutup.',
            'data' => $session,
        ]);
    }
}
