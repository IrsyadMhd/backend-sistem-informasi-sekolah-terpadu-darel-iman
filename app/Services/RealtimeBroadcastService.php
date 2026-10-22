<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RealtimeBroadcastService
{
    /**
     * Broadcast an event to a specific channel.
     */
    public function broadcast(string $channel, string $event, array $payload, ?string $senderId = null): array
    {
        $id = (string) Str::uuid();
        $record = [
            'id' => $id,
            'channel' => $channel,
            'event' => $event,
            'payload' => json_encode($payload),
            'sender_id' => $senderId,
            'delivered' => false,
            'created_at' => now(),
        ];

        DB::table('realtime_events')->insert($record);

        // Ping WebSocket Gateway server if explicitly configured
        try {
            $gatewayUrl = env('WEBSOCKET_GATEWAY_URL');
            if (!empty($gatewayUrl)) {
                $publishUrl = rtrim($gatewayUrl, '/') . '/publish';
                Http::timeout(0.5)->post($publishUrl, [
                    'id' => $id,
                    'channel' => $channel,
                    'event' => $event,
                    'payload' => $payload,
                    'sender_id' => $senderId,
                    'timestamp' => now()->toISOString(),
                ]);
            }
        } catch (\Throwable) {
            // Non-blocking: will still be caught by polling or SSE fallback
        }

        return [
            'id' => $id,
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
        ];
    }

    /**
     * Broadcast a new chat message to the recipient user.
     */
    public function broadcastChatMessage(string $recipientUserId, array $messageData, string $senderUserId): array
    {
        return $this->broadcast(
            channel: 'user.' . $recipientUserId,
            event: 'chat.message.sent',
            payload: $messageData,
            senderId: $senderUserId
        );
    }

    /**
     * Broadcast a new student activity (Attendance / Tahfizh) to the student's parents channel,
     * simultaneously delivering in-app push notification to parents' notification inbox.
     */
    public function broadcastStudentActivity(string $studentId, array $activityData): array
    {
        // Pastikan student_id selalu tersedia dalam payload untuk filtering ketat di frontend
        $activityData['student_id'] = (string) $studentId;

        // 1. Broadcast ke channel WebSocket privat santri
        $result = $this->broadcast(
            channel: 'student.' . $studentId,
            event: 'student.activity.logged',
            payload: $activityData
        );

        // 2. Deliver notifikasi ke inbox akun orang tua & trigger push notification
        try {
            $student = \App\Models\Student::with(['parent.user', 'parentsPivot.user'])->find($studentId);
            if ($student) {
                $parentUserIds = collect();
                if ($student->parent?->user_id) {
                    $parentUserIds->push($student->parent->user_id);
                }
                foreach ($student->parentsPivot ?? [] as $pPivot) {
                    if ($pPivot->user_id) {
                        $parentUserIds->push($pPivot->user_id);
                    }
                }
                // Fallback relasi pivot table parent_students
                $pivotParents = DB::table('parent_students')
                    ->join('parents', 'parent_students.parent_id', '=', 'parents.id')
                    ->where('parent_students.student_id', $studentId)
                    ->whereNotNull('parents.user_id')
                    ->pluck('parents.user_id');
                $parentUserIds = $parentUserIds->concat($pivotParents)->unique()->filter();

                $studentName = $student->nama_lengkap ?? $student->full_name ?? 'Ananda';
                $title = $activityData['title'] ?? 'Aktivitas Santri';
                $body = $activityData['subtitle'] ?? "{$studentName} memiliki pembaruan aktivitas kegiatan di sekolah.";
                $channel = $activityData['type'] ?? 'student_activity';

                foreach ($parentUserIds as $pUid) {
                    \App\Models\Notification::deliver(
                        userId: (string) $pUid,
                        title: $title,
                        body: $body,
                        channel: $channel,
                        metadata: array_merge($activityData, [
                            'student_id' => (string) $studentId,
                            'student_name' => $studentName,
                        ])
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mendistribusikan notifikasi orang tua: ' . $e->getMessage());
        }

        return $result;
    }
}
