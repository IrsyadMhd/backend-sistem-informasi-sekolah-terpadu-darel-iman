<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PortalMessage;
use App\Models\User;
use App\Models\UserPresence;
use App\Services\ChatAccessService;
use App\Services\RealtimeBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeChatController extends Controller
{
    protected ChatAccessService $chatAccess;

    public function __construct(ChatAccessService $chatAccess)
    {
        $this->chatAccess = $chatAccess;
    }

    /**
     * Get list of employee contacts for chatting.
     */
    public function employeeContacts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $search = $request->query('search');
        $unitId = $request->query('unit_id');
        $statusFilter = strtolower($request->query('status', 'all'));
        $categoryFilter = strtolower($request->query('category', 'all'));

        $scope = $this->chatAccess->resolveChatScope($user);
        $userUnitId = $scope['user_unit_id'];
        $isGlobalScope = $scope['can_view_all_units'];

        $query = Employee::query()
            ->with([
                'user:id,name,email,is_active',
                'unit:id,name,code',
                'position:id,name',
                'division:id,name',
            ]);

        // Scoping per unit (Super Admin / Yayasan / Divisi Pendidikan see all units)
        if (filled($unitId) && $unitId !== 'all') {
            $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q2) use ($unitId, $operator) {
                $q2->where('unit_id', $unitId)
                   ->orWhereHas('unit', function ($u) use ($unitId, $operator) {
                       $u->where('id', $unitId)
                         ->orWhere('name', $operator, "%{$unitId}%")
                         ->orWhere('code', $operator, "%{$unitId}%");
                   });
            });
        } elseif (! $isGlobalScope && $userUnitId && blank($search)) {
            $query->where('unit_id', $userUnitId);
        }

        // Null-safe search filter
        $query->when(filled($search), function ($q) use ($search) {
            $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $q->where(function ($q2) use ($search, $operator) {
                $q2->where('nama_lengkap', $operator, "%{$search}%")
                   ->orWhere('nama_panggilan', $operator, "%{$search}%")
                   ->orWhere('niy', $operator, "%{$search}%")
                   ->orWhere('nik', $operator, "%{$search}%")
                   ->orWhere('email', $operator, "%{$search}%")
                   ->orWhereHas('unit', fn ($u) => $u->where('name', $operator, "%{$search}%"))
                   ->orWhereHas('position', fn ($p) => $p->where('name', $operator, "%{$search}%"))
                   ->orWhereHas('division', fn ($d) => $d->where('name', $operator, "%{$search}%"));
            });
        });

        $employees = $query->orderBy('nama_lengkap', 'asc')->get();

        // Exclude logged in user
        $employees = $employees->filter(fn ($e) => (string) ($e->user_id ?: $e->id) !== (string) $user->id);

        $allUserIds = $employees->map(fn ($e) => (string) ($e->user_id ?: $e->id))->unique()->filter()->toArray();
        $presenceMap = $this->chatAccess->getPresenceMap($allUserIds);

        // Bulk fetch unread message counts in 1 query
        $unreadCountsMap = DB::table('portal_messages')
            ->select('sender_user_id', DB::raw('count(*) as total'))
            ->whereNull('student_id')
            ->where('recipient_user_id', $user->id)
            ->whereNull('read_at')
            ->groupBy('sender_user_id')
            ->pluck('total', 'sender_user_id');

        // Bulk fetch last messages for all contacts in 1 query
        $lastMessagesMap = PortalMessage::query()
            ->select('sender_user_id', 'recipient_user_id', 'message', 'created_at')
            ->whereNull('student_id')
            ->where(fn ($q) => $q->where('sender_user_id', $user->id)->orWhere('recipient_user_id', $user->id))
            ->orderBy('created_at', 'desc')
            ->take(1000)
            ->get()
            ->groupBy(function ($msg) use ($user) {
                return (string) ($msg->sender_user_id === $user->id ? $msg->recipient_user_id : $msg->sender_user_id);
            });

        $data = $employees->map(function ($emp) use ($user, $presenceMap, $unreadCountsMap, $lastMessagesMap) {
            $effectiveUserId = (string) ($emp->user_id ?: $emp->id);
            $presence = $presenceMap[$effectiveUserId] ?? ['status' => 'offline', 'is_online' => false];

            $unreadCount = (int) ($unreadCountsMap->get($effectiveUserId) ?? 0);
            $userMsgs = $lastMessagesMap->get($effectiveUserId);
            $lastMsg = $userMsgs ? $userMsgs->first() : null;

            return [
                'id' => $emp->id,
                'user_id' => $effectiveUserId,
                'name' => $emp->nama_lengkap ?? $emp->user?->name ?? 'Pegawai',
                'nama_panggilan' => $emp->nama_panggilan,
                'nip_niy' => $emp->niy ?? $emp->nik ?? '-',
                'email' => $emp->email ?? $emp->user?->email,
                'no_hp' => $emp->no_hp,
                'foto' => $emp->foto,
                'unit_id' => $emp->unit_id,
                'unit_name' => $emp->unit?->name ?? 'Yayasan / Lintas Unit',
                'position_name' => $emp->position?->name ?? 'Staf/Pegawai',
                'division_name' => $emp->division?->name ?? '-',
                'role' => $emp->position?->name ?? 'Pegawai',
                'unread_count' => $unreadCount,
                'last_message' => $lastMsg?->message,
                'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
                'is_online' => $presence['is_online'],
                'status' => $presence['status'],
                'last_seen_at' => $presence['last_seen_at'] ?? null,
            ];
        });

        // Filter by status if requested
        if (filled($statusFilter) && $statusFilter !== 'all') {
            $data = $data->filter(function ($item) use ($statusFilter) {
                if ($statusFilter === 'online') return $item['is_online'];
                if ($statusFilter === 'busy') return $item['status'] === 'busy';
                if ($statusFilter === 'offline') return ! $item['is_online'] && $item['status'] !== 'busy';
                return true;
            });
        }

        return response()->json([
            'success' => true,
            'data' => array_values($data->toArray()),
            'meta' => [
                'scope' => $scope['category'],
                'user_unit_id' => $userUnitId,
                'total' => $data->count(),
            ],
        ]);
    }

    /**
     * Get active conversations (1-on-1 and groups) for employee chat.
     */
    public function employeeConversations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }
        $userId = $user->id;

        // 1. Fetch group conversations in batch (Zero N+1)
        $groupConversations = Conversation::query()
            ->with(['participants.user'])
            ->withCount('participants')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $groupConvIds = $groupConversations->pluck('id')->toArray();
        $groupLastMsgs = PortalMessage::query()
            ->whereIn('conversation_id', $groupConvIds)
            ->orderBy('created_at', 'desc')
            ->get(['conversation_id', 'message', 'created_at']);

        $groupLastMsgMap = [];
        foreach ($groupLastMsgs as $gm) {
            if (! isset($groupLastMsgMap[$gm->conversation_id])) {
                $groupLastMsgMap[$gm->conversation_id] = $gm;
            }
        }

        $groupsData = $groupConversations->map(function ($conv) use ($userId, $groupLastMsgMap) {
            $lastMsg = $groupLastMsgMap[$conv->id] ?? null;
            $participant = $conv->participants->firstWhere('user_id', $userId);

            $unreadCount = 0;
            if ($participant?->last_read_at && $lastMsg && $lastMsg->created_at > $participant->last_read_at) {
                $unreadCount = 1;
            }

            return [
                'id' => $conv->id,
                'type' => 'group',
                'name' => $conv->name ?? 'Grup Sekolah',
                'avatar' => $conv->avatar,
                'members_count' => $conv->participants_count ?? $conv->participants->count(),
                'last_message' => $lastMsg?->message,
                'last_message_at' => $lastMsg?->created_at?->toIso8601String() ?? $conv->created_at->toIso8601String(),
                'unread_count' => $unreadCount,
                'is_muted' => (bool) $participant?->is_muted,
                'is_archived' => (bool) $participant?->is_archived,
            ];
        });

        // 2. Fetch direct conversations from portal_messages (including parent-to-teacher messages)
        $messages = PortalMessage::query()
            ->with(['sender.roles', 'recipient.roles', 'student:id,full_name'])
            ->where(fn ($q) => $q->where('sender_user_id', $userId)->orWhere('recipient_user_id', $userId))
            ->orderBy('created_at', 'desc')
            ->take(500)
            ->get();

        $allOtherUserIds = $messages->map(function ($msg) use ($userId) {
            return $msg->sender_user_id === $userId ? $msg->recipient_user_id : $msg->sender_user_id;
        })->unique()->filter()->toArray();

        $presenceMap = $this->chatAccess->getPresenceMap($allOtherUserIds);

        $employeesMap = Employee::query()
            ->with(['unit:id,name', 'position:id,name', 'division:id,name'])
            ->whereIn('user_id', $allOtherUserIds)
            ->get()
            ->keyBy('user_id');

        $unreadCountsMap = DB::table('portal_messages')
            ->select('sender_user_id', DB::raw('count(*) as total'))
            ->whereIn('sender_user_id', $allOtherUserIds)
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->groupBy('sender_user_id')
            ->pluck('total', 'sender_user_id');

        $groupedDirect = [];
        foreach ($messages as $msg) {
            $otherUserId = $msg->sender_user_id === $userId ? $msg->recipient_user_id : $msg->sender_user_id;

            if (! isset($groupedDirect[$otherUserId])) {
                $otherUser = $msg->sender_user_id === $userId ? $msg->recipient : $msg->sender;
                $employee = $employeesMap->get($otherUserId);
                $unreadCount = (int) ($unreadCountsMap->get($otherUserId) ?? 0);
                $presence = $presenceMap[(string) $otherUserId] ?? ['status' => 'offline', 'is_online' => false];

                $isParentContact = ! $employee && ($msg->student_id || ($otherUser && $otherUser->roles->contains(fn ($r) => in_array($r->name, ['Orang Tua', 'Parent', 'Wali Murid']))));
                $posName = $employee?->position?->name
                    ?? ($msg->student ? ('Wali dari ' . $msg->student->full_name) : ($isParentContact ? 'Orang Tua Murid' : 'Staf/Pegawai'));
                $roleLabel = $employee?->position?->name ?? ($isParentContact ? 'Orang Tua Murid' : 'Pegawai');

                $groupedDirect[$otherUserId] = [
                    'id' => $otherUserId,
                    'user_id' => $otherUserId,
                    'type' => 'direct',
                    'name' => $employee?->nama_lengkap ?? $otherUser?->name ?? ($isParentContact ? 'Orang Tua Murid' : 'Pegawai'),
                    'parent_name' => $otherUser?->name,
                    'student_id' => $msg->student_id,
                    'student_name' => $msg->student?->full_name,
                    'position_name' => $posName,
                    'unit_name' => $employee?->unit?->name ?? '-',
                    'division_name' => $employee?->division?->name ?? '-',
                    'role' => $roleLabel,
                    'foto' => $employee?->foto ?? $otherUser?->avatar_url,
                    'photo' => $employee?->foto ?? $otherUser?->avatar_url,
                    'last_message' => $msg->message,
                    'last_message_at' => $msg->created_at?->toIso8601String(),
                    'unread_count' => $unreadCount,
                    'is_online' => $presence['is_online'],
                    'status' => $presence['status'],
                ];
            }
        }

        $allConversations = array_merge($groupsData->toArray(), array_values($groupedDirect));

        usort($allConversations, function ($a, $b) {
            return strtotime($b['last_message_at'] ?? '1970-01-01') - strtotime($a['last_message_at'] ?? '1970-01-01');
        });

        return response()->json([
            'success' => true,
            'data' => $allConversations,
        ]);
    }

    /**
     * Get chat messages between current user and recipient employee
     */
    public function employeeMessages(Request $request, string $recipientUserId): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }
        $userId = $user->id;

        // Mark unread messages as read only if unread messages exist
        $hasUnread = PortalMessage::query()
            ->where('sender_user_id', $recipientUserId)
            ->where('recipient_user_id', $userId)
            ->whereNull('read_at')
            ->exists();

        if ($hasUnread) {
            PortalMessage::query()
                ->where('sender_user_id', $recipientUserId)
                ->where('recipient_user_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $messages = PortalMessage::query()
            ->with(['sender:id,name,email', 'recipient:id,name,email', 'attachments'])
            ->where(function ($q) use ($userId, $recipientUserId) {
                $q->where(fn ($q2) => $q2->where('sender_user_id', $userId)->where('recipient_user_id', $recipientUserId))
                  ->orWhere(fn ($q2) => $q2->where('sender_user_id', $recipientUserId)->where('recipient_user_id', $userId));
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $senderUserIds = $messages->pluck('sender_user_id')->unique()->filter()->toArray();
        $employeesMap = Employee::query()
            ->with(['unit:id,name', 'position:id,name', 'division:id,name'])
            ->whereIn('user_id', $senderUserIds)
            ->get()
            ->keyBy('user_id');

        // Load message reactions
        $messageIds = $messages->pluck('id')->toArray();
        $reactionsGrouped = DB::table('portal_message_reactions')
            ->whereIn('message_id', $messageIds)
            ->get()
            ->groupBy('message_id');

        $messagesData = $messages->map(function ($msg) use ($employeesMap, $reactionsGrouped) {
            $emp = $employeesMap->get($msg->sender_user_id);
            $msgArr = $msg->toArray();
            $msgArr['sender_name'] = $emp?->nama_lengkap ?? $msg->sender?->name ?? 'Pengguna';
            $msgArr['sender_position'] = $emp?->position?->name ?? 'Staf/Pegawai';
            $msgArr['sender_unit'] = $emp?->unit?->name ?? 'Yayasan / Lintas Unit';
            $msgArr['sender_division'] = $emp?->division?->name ?? '-';
            $msgArr['sender_role'] = $emp?->position?->name ?? 'Pegawai';

            $rawReactions = $reactionsGrouped->get($msg->id, collect());
            $reactionsSummary = [];
            foreach ($rawReactions as $r) {
                $emoji = $r->reaction;
                if (! isset($reactionsSummary[$emoji])) {
                    $reactionsSummary[$emoji] = ['reaction' => $emoji, 'count' => 0];
                }
                $reactionsSummary[$emoji]['count']++;
            }
            $msgArr['reactions'] = array_values($reactionsSummary);

            return $msgArr;
        });

        return response()->json([
            'success' => true,
            'data' => $messagesData,
        ]);
    }

    /**
     * Send chat message to an employee
     */
    public function sendEmployeeMessage(Request $request, string $recipientUserId): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:attachment|nullable|string|max:5000',
            'reply_to_message_id' => 'nullable|uuid',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $recipientUser = User::query()->where('id', $recipientUserId)->first();
        if (! $recipientUser) {
            return response()->json(['success' => false, 'message' => 'Penerima tidak ditemukan.'], 404);
        }

        $existingStudentId = PortalMessage::query()
            ->where(fn ($q) => $q->where('sender_user_id', $recipientUserId)->orWhere('recipient_user_id', $recipientUserId))
            ->whereNotNull('student_id')
            ->latest()
            ->value('student_id');

        $message = PortalMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $existingStudentId,
            'sender_user_id' => $user->id,
            'recipient_user_id' => $recipientUserId,
            'message' => trim((string) ($request->input('message') ?? '')),
            'reply_to_message_id' => $request->input('reply_to_message_id'),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat/attachments', 'public');
            \App\Models\PortalMessageAttachment::create([
                'message_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

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
        } catch (\Throwable $e) {}

        // Realtime Broadcast chat message to recipient employee
        try {
            app(RealtimeBroadcastService::class)->broadcastChatMessage(
                $recipientUserId,
                [
                    'id' => $message->id,
                    'sender_user_id' => $user->id,
                    'sender_name' => $user->name,
                    'recipient_user_id' => $recipientUserId,
                    'message' => $message->message,
                    'created_at' => $message->created_at ? $message->created_at->toISOString() : now()->toISOString(),
                ],
                $user->id
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load(['sender:id,name', 'recipient:id,name', 'attachments']),
        ]);
    }

    /**
     * Create group conversation
     */
    public function storeGroupConversation(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'required',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $conv = Conversation::create([
            'id' => (string) Str::uuid(),
            'type' => 'group',
            'name' => trim($request->input('name')),
            'created_by' => $user->id,
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $conv->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $pIds = array_unique($request->input('participant_ids'));
        foreach ($pIds as $pId) {
            if ((string) $pId !== (string) $user->id) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conv->id,
                    'user_id' => $pId,
                ], [
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Grup percakapan berhasil dibuat.',
            'data' => $conv->load('participants.user'),
        ]);
    }

    /**
     * Add reaction to a message
     */
    public function addReaction(Request $request, string $messageId): JsonResponse
    {
        $request->validate([
            'reaction' => 'required|string|max:16',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        DB::table('portal_message_reactions')->updateOrInsert([
            'message_id' => $messageId,
            'user_id' => $user->id,
            'reaction' => $request->input('reaction'),
        ], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reaksi berhasil ditambahkan.',
        ]);
    }

    /**
     * Remove reaction from a message
     */
    public function removeReaction(Request $request, string $messageId, string $reaction): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        DB::table('portal_message_reactions')
            ->where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->where('reaction', $reaction)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reaksi berhasil dihapus.',
        ]);
    }

    /**
     * Update user presence status
     */
    public function updatePresence(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:online,busy,offline',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        UserPresence::updateOrCreate([
            'user_id' => $user->id,
        ], [
            'status' => $request->input('status'),
            'last_seen_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => $request->input('status'),
        ]);
    }

    /**
     * Get chat capabilities for current user
     */
    public function getCapabilities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $scope = $this->chatAccess->resolveChatScope($user);

        return response()->json([
            'success' => true,
            'can_chat' => true,
            'can_create_group' => true,
            'can_view_all_units' => $scope['can_view_all_units'],
            'category' => $scope['category'],
            'allowed_categories' => $scope['allowed_categories'],
        ]);
    }
}
