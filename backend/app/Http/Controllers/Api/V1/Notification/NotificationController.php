<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Notification\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi in-app milik user yang sedang login.
 *
 * Setiap query di-scope ke `user_id` pemanggil — tenant scope global saja
 * tidak cukup, karena satu tenant berisi banyak user.
 */
class NotificationController extends ApiController
{
    /** GET /notifications */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::forUser($request->user()->id)
            ->latest('created_at');

        if ($request->boolean('unread_only')) {
            $query->unread();
        }

        return $this->success(
            $query->paginate((int) $request->input('per_page', 25))->withQueryString()
        );
    }

    /** GET /notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'count' => Notification::forUser($request->user()->id)->unread()->count(),
        ]);
    }

    /** POST /notifications/{notification}/read */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = Notification::forUser($request->user()->id)->find($notification);

        if (!$item) {
            return $this->notFound('Notifikasi tidak ditemukan.');
        }

        if ($item->read_at === null) {
            $item->update(['read_at' => now()]);
        }

        return $this->success(null, 'Notifikasi ditandai terbaca.');
    }

    /** POST /notifications/read-all */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = Notification::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $updated], 'Semua notifikasi ditandai terbaca.');
    }

    /** DELETE /notifications/{notification} */
    public function destroy(Request $request, string $notification): JsonResponse
    {
        $item = Notification::forUser($request->user()->id)->find($notification);

        if (!$item) {
            return $this->notFound('Notifikasi tidak ditemukan.');
        }

        $item->delete();

        return $this->success(null, 'Notifikasi dihapus.');
    }
}
