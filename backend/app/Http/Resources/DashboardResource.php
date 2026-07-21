<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kpi' => [
                'total_students' => $this['total_students'] ?? 0,
                'total_teachers' => $this['total_teachers'] ?? 0,
                'today_attendance' => $this['today_attendance'] ?? 0,
                'late_count' => $this['late_count'] ?? 0,
                'absence_count' => $this['absence_count'] ?? 0,
            ],
            'charts' => [
                'weekly_attendance' => $this['weekly_attendance'] ?? [],
                'tahfizh_progress' => $this['tahfizh_progress'] ?? [],
                'mutabaah_summary' => $this['mutabaah_summary'] ?? [],
            ],
        ];
    }
}
