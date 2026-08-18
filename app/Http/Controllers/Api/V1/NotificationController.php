<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /**
     * Ambil daftar notifikasi pengguna yang sedang login.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $query = Notification::userQuery($userId, [
            'search' => $request->query('search'),
            'type' => $request->query('type'),
            'is_read' => $request->query('is_read'),
        ]);

        $notifications = $query->latest()
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 100));

        return response()->json($notifications);
    }

    /**
     * Ambil jumlah notifikasi yang belum dibaca.
     * Cache 30 detik per user — aman karena diinvalidasi saat mark-read.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $userId   = $request->user()->id;
        $cacheKey = "notif_unread:{$userId}";

        $count = Cache::remember($cacheKey, 30, function () use ($userId) {
            return Notification::byUser($userId)
                ->unread()
                ->count();
        });

        return response()->json([
            'status'       => 'success',
            'unread_count' => $count,
        ]);
    }

    /**
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = Notification::byUser($request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        // Invalidate unread count cache for this user
        Cache::forget("notif_unread:{$request->user()->id}");

        return response()->json([
            'status'  => 'success',
            'message' => 'Notifikasi ditandai telah dibaca.',
            'data'    => $notification,
        ]);
    }

    /**
     * Tandai semua notifikasi pengguna sebagai sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::byUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        // Invalidate unread count cache
        Cache::forget("notif_unread:{$request->user()->id}");

        return response()->json([
            'status'  => 'success',
            'message' => 'Semua notifikasi ditandai telah dibaca.',
        ]);
    }

}
