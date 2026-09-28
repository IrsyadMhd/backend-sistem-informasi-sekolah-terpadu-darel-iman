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
     * Broadcast a new student activity (Attendance / Tahfizh) to the student's parents channel.
     */
    public function broadcastStudentActivity(string $studentId, array $activityData): array
    {
        return $this->broadcast(
            channel: 'student.' . $studentId,
            event: 'student.activity.logged',
            payload: $activityData
        );
    }
}
