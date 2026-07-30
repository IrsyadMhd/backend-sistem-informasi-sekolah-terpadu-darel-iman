<?php

namespace App\Services;

use App\Models\AttendanceAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AttendanceAuditService
{
    public function record(Request $request, string $action, Model $reference, ?array $old = null, ?array $new = null, ?string $reason = null): void
    {
        AttendanceAuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
