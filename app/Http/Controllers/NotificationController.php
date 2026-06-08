<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /** GET /api/notifications */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data'     => $notifications,
            'non_lues' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /** PATCH /api/notifications/{id}/lire */
    public function marquerLue(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    /** POST /api/notifications/tout-lire */
    public function toutMarquerLu(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues.']);
    }

    /** DELETE /api/notifications/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json(['message' => 'Notification supprimée.']);
    }
}
