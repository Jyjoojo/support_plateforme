<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Notifications\TicketCommentedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    /** GET /api/notifications */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();

        // Permet de filtrer via /api/notifications?non_lues=true
        if ($request->boolean('non_lues')) {
            $query->unread();
        }

        $notifications = $query->latest()->paginate(30);

        // Fusionner proprement les données de pagination avec notre variable personnalisée
        $response = $notifications->toArray();
        $response['total_non_lues'] = $request->user()->unreadNotifications()->count();

        return response()->json($response);
    }

    /** GET /api/notifications/messages */
    public function messages(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->where('type', TicketCommentedNotification::class)
            ->latest()
            ->limit(5)
            ->get();

        return NotificationResource::collection($notifications);
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
