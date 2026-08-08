<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Notifications\TicketCommentedNotification;
use App\Support\NotificationCatalog;
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

        $categorie = $request->validate([
            'categorie' => 'nullable|in:ticket,systeme',
        ])['categorie'] ?? null;

        if ($categorie === NotificationCatalog::TICKET) {
            $query->whereIn('type', NotificationCatalog::ticketTypes());
        } elseif ($categorie === NotificationCatalog::SYSTEME) {
            $query->whereNotIn('type', NotificationCatalog::ticketTypes());
        }

        $notifications = $query->latest()->paginate(30);

        // Fusionner proprement les données de pagination avec notre variable personnalisée
        return NotificationResource::collection($notifications)
            ->additional([
                'total_non_lues' => $request->user()->unreadNotifications()->count(),
            ])
            ->response();
    }

    /** GET /api/notifications/categories */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => NotificationCatalog::categories(),
        ]);
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
