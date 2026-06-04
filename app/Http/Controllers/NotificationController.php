<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Notification;

class NotificationController extends Controller
{
    /** GET /api/notifications */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->orderByDesc('date_envoi')
            ->paginate(30);

        return response()->json([
            'data'       => $notifications,
            'non_lues'   => $request->user()->notifications()->nonLues()->count(),
        ]);
    }

    /** PATCH /api/notifications/{id}/lire */
    public function marquerLue(Request $request, string $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->marquerLue();

        return response()->json(['message' => 'Notification lue.']);
    }

    /** POST /api/notifications/tout-lire */
    public function toutMarquerLu(Request $request): JsonResponse
    {
        $request->user()->notifications()->nonLues()->update(['est_lue' => true]);

        return response()->json(['message' => 'Toutes les notifications marquées comme lues.']);
    }

    /** DELETE /api/notifications/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Notification supprimée.']);
    }
}
