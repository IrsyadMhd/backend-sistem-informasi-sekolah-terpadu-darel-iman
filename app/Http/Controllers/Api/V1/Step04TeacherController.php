<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LessonAttendanceSession;
use App\Services\TeacherMonitoringService;
use App\Services\TeachingAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Step04TeacherController extends Controller
{
    public function __construct(
        private TeachingAttendanceService $teachingAttendance,
        private TeacherMonitoringService $monitoring,
    ) {}

    public function schedules(Request $request): JsonResponse
    {
        $date = ($request->date('date') ?: now())->startOfDay();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'server_time' => now()->toIso8601String(),
                'timezone' => config('app.timezone'),
                'schedules' => $this->teachingAttendance->todaySchedules($request->user(), $date),
            ],
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'uuid', 'exists:class_schedules,id'],
            'qr_token' => ['required', 'string', 'max:2048'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->teachingAttendance->scan($request, $data['schedule_id'], $data['qr_token']),
        ]);
    }

    public function startSession(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        $data = $request->validate([
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->teachingAttendance->startSession(
                $request,
                $session,
                (int) ($data['duration_minutes'] ?? config('attendance.session_duration_minutes', 60)),
            ),
        ]);
    }

    public function closeSession(Request $request, LessonAttendanceSession $session): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->teachingAttendance->closeSession($request, $session),
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->teachingAttendance->heartbeat($request, $data['device_id'], $data['device_name'] ?? null),
        ]);
    }

    public function monitoring(Request $request): JsonResponse
    {
        $date = ($request->date('date') ?: now())->startOfDay();

        return response()->json([
            'success' => true,
            'data' => $this->monitoring->overview($request->user(), $date),
        ]);
    }
}
