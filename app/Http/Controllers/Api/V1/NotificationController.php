<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The header bell (Livewire Notifications\NotificationBell): every row with
 * the per-tab counts, in one answer, so switching tabs on the phone costs
 * nothing. Built by NotificationFeed, which the web's bell uses too.
 *
 * `user_channel` is where the app hears that the list changed (connection
 * requests and answers, mentions), as the web's bell listens.
 */
class NotificationController extends Controller
{
    public function __construct(private NotificationFeed $feed) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $built = $this->feed->build($user);

        return response()->json([
            'items' => $built['items']->map(fn (array $n) => [
                'kind' => $n['kind'],
                'id' => $n['id'],
                'room_id' => $n['room_id'],
                'user_id' => $n['user_id'],
                'title' => (string) $n['title'],
                'body' => (string) $n['body'],
                'time' => $n['time'] ? Carbon::parse($n['time'])->toIso8601String() : null,
                'unread' => (int) $n['unread'],
                'icon' => $n['icon'],
                'seed' => $n['seed'],
                'initial' => $n['initial'],
                'link' => $n['link'],
            ])->values(),
            'counts' => $built['counts'],
            'user_channel' => "tenant.{$user->tenant_id}.user.{$user->id}",
        ]);
    }

    /** "Mark all read": every chat, and the stored notifications. */
    public function readAll(Request $request): JsonResponse
    {
        $this->feed->markAllChatsRead($request->user());

        return response()->json(['message' => __('All notifications marked as read')]);
    }

    /** Opening a stored notification (an order event): read, and where it leads. */
    public function open(Request $request, string $id): JsonResponse
    {
        $data = $this->feed->openStored($request->user(), $id);
        abort_if($data === null, 404);

        return response()->json(['url' => $data['url'] ?? null, 'data' => $data]);
    }
}
