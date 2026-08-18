<?php

return [
    'late_tolerance_minutes' => (int) env('ATTENDANCE_LATE_TOLERANCE_MINUTES', 10),
    'active_schedule_early_minutes' => (int) env('ATTENDANCE_ACTIVE_SCHEDULE_EARLY_MINUTES', 15),
    'active_schedule_late_minutes' => (int) env('ATTENDANCE_ACTIVE_SCHEDULE_LATE_MINUTES', 0),
    'face_confidence_threshold' => (float) env('ATTENDANCE_FACE_CONFIDENCE_THRESHOLD', 85),
    'session_duration_minutes' => (int) env('ATTENDANCE_SESSION_DURATION_MINUTES', 60),
    'presence_online_threshold_seconds' => (int) env('ATTENDANCE_PRESENCE_ONLINE_THRESHOLD_SECONDS', 90),
];
