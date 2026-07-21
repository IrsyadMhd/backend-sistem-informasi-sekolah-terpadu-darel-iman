<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): DashboardResource
    {
        $today = now()->toDateString();

        $todayAttendance = DB::table('attendances')->whereDate('attendance_date', $today)->count();
        $lateCount = DB::table('attendances')->whereDate('attendance_date', $today)->where('status', 'late')->count();
        $absenceCount = DB::table('attendances')->whereDate('attendance_date', $today)->where('status', 'absent')->count();

        $weeklyAttendance = DB::table('attendances')
            ->selectRaw('attendance_date, count(*) as total')
            ->whereBetween('attendance_date', [now()->subDays(6)->toDateString(), $today])
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get();

        return new DashboardResource([
            'total_students' => Student::query()->count(),
            'total_teachers' => Teacher::query()->count(),
            'today_attendance' => $todayAttendance,
            'late_count' => $lateCount,
            'absence_count' => $absenceCount,
            'weekly_attendance' => $weeklyAttendance,
            'tahfizh_progress' => [],
            'mutabaah_summary' => [],
        ]);
    }
}
