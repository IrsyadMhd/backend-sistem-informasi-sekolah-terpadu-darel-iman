<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PortalMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeChatController extends Controller
{
    /**
     * Get list of employee contacts for chatting.
     *
     * Directory dibatasi (scoped) ke unit kerja peminta agar tidak bocor
     * ke seluruh direktori pengguna. Pengguna tanpa data Employee
     * (mis. akun portal) tidak boleh melihat direktori sama sekali.
     */
    public function employeeContacts(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = $request->query('search');

        $requesterEmployee = Employee::query()->where('user_id', $user->id)->first();
        if (! $requesterEmployee) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $query = Employee::query()
            ->with([
                'user:id,name,email,is_active',
                'unit:id,name,code',
                'position:id,name',
                'division:id,name',
            ])
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->where(fn ($q) => $q
                ->where('unit_id', $requesterEmployee->unit_id)
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('unit_id')
                    ->where('division_id', $requesterEmployee->division_id)));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nama_panggilan', 'like', "%{$search}%")
                  ->orWhere('niy', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('nama_lengkap', 'asc')->get();

        $data = $employees->map(function ($emp) use ($user) {
            // Count unread messages from this employee to logged in user
            $unreadCount = PortalMessage::query()
                ->whereNull('student_id')
                ->where('sender_user_id', $emp->user_id)
                ->where('recipient_user_id', $user->id)
                ->whereNull('read_at')
                ->count();

            // Get last message between them
            $lastMsg = PortalMessage::query()
                ->whereNull('student_id')
                ->where(function ($q) use ($user, $emp) {
                    $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $emp->user_id))
                      ->orWhere(fn ($q2) => $q2->where('sender_user_id', $emp->user_id)->where('recipient_user_id', $user->id));
                })
                ->orderBy('created_at', 'desc')
                ->first();

            return [
                'id' => $emp->id,
                'user_id' => $emp->user_id,
                'name' => $emp->nama_lengkap ?? $emp->user?->name ?? 'Pegawai',
                'nama_panggilan' => $emp->nama_panggilan,
                'nip_niy' => $emp->niy ?? $emp->nik ?? '-',
                'email' => $emp->email ?? $emp->user?->email,
                'no_hp' => $emp->no_hp,
                'foto' => $emp->foto,
                'unit_name' => $emp->unit?->name ?? '-',
                'position_name' => $emp->position?->name ?? 'Staf/Pegawai',
                'division_name' => $emp->division?->name ?? '-',
                'role' => $emp->position?->name ?? 'Pegawai',
                'unread_count' => $unreadCount,
                'last_message' => $lastMsg?->message,
                'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get active conversations for employee chat
     */
    public function employeeConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all messages where student_id IS NULL and user is sender or recipient
        $messages = PortalMessage::query()
            ->with(['sender:id,name,email', 'recipient:id,name,email'])
            ->whereNull('student_id')
            ->where(fn ($q) => $q->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id))
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [];

        foreach ($messages as $msg) {
            $otherUserId = $msg->sender_user_id === $user->id ? $msg->recipient_user_id : $msg->sender_user_id;

            if (! isset($grouped[$otherUserId])) {
                $otherUser = $msg->sender_user_id === $user->id ? $msg->recipient : $msg->sender;

                $employee = Employee::query()
                    ->with(['unit:id,name', 'position:id,name', 'division:id,name'])
                    ->where('user_id', $otherUserId)
                    ->first();

                $unreadCount = PortalMessage::query()
                    ->whereNull('student_id')
                    ->where('sender_user_id', $otherUserId)
                    ->where('recipient_user_id', $user->id)
                    ->whereNull('read_at')
                    ->count();

                $grouped[$otherUserId] = [
                    'id' => $otherUserId,
                    'user_id' => $otherUserId,
                    'name' => $employee?->nama_lengkap ?? $otherUser?->name ?? 'Pegawai',
                    'position_name' => $employee?->position?->name ?? 'Staf/Pegawai',
                    'unit_name' => $employee?->unit?->name ?? '-',
                    'division_name' => $employee?->division?->name ?? '-',
                    'role' => $employee?->position?->name ?? 'Pegawai',
                    'foto' => $employee?->foto,
                    'last_message' => $msg->message,
                    'last_message_at' => $msg->created_at?->toIso8601String(),
                    'unread_count' => $unreadCount,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => array_values($grouped),
        ]);
    }

    /**
     * Get chat messages between current user and recipient employee
     */
    public function employeeMessages(Request $request, string $recipientUserId): JsonResponse
    {
        $user = $request->user();

        // Mark unread messages as read
        PortalMessage::query()
            ->whereNull('student_id')
            ->where('sender_user_id', $recipientUserId)
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = PortalMessage::query()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->whereNull('student_id')
            ->where(function ($q) use ($user, $recipientUserId) {
                $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $recipientUserId))
                  ->orWhere(fn ($q2) => $q2->where('sender_user_id', $recipientUserId)->where('recipient_user_id', $user->id));
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send chat message to an employee
     */
    public function sendEmployeeMessage(Request $request, string $recipientUserId): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $user = $request->user();

        $recipientEmployee = Employee::query()
            ->where('user_id', $recipientUserId)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->first();

        if (! $recipientEmployee) {
            return response()->json(['success' => false, 'message' => 'Penerima bukan pegawai yang aktif.'], 403);
        }

        $message = PortalMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => null,
            'sender_user_id' => $user->id,
            'recipient_user_id' => $recipientUserId,
            'message' => trim($request->input('message')),
        ]);

        try {
            Notification::deliver(
                userId: $recipientUserId,
                title: 'Pesan Pegawai Baru (' . $user->name . ')',
                body: Str::limit($message->message, 100),
                channel: 'chat_employee',
                metadata: [
                    'sender_user_id' => $user->id,
                    'message_id' => $message->id,
                ],
            );
        } catch (\Throwable $e) {
            // Silence notification error
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load(['sender:id,name', 'recipient:id,name']),
        ]);
    }
}
