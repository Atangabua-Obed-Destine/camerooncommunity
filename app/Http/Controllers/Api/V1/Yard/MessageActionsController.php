<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\YardPresenter;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Services\Yard\MessageActionsService;
use App\Support\StorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The selection bar's star, pin and forward, for the app. */
class MessageActionsController extends Controller
{
    public function __construct(private MessageActionsService $actions) {}

    /** POST /yard/messages/star {ids: int[]}: stars them all, or clears them when all were starred. */
    public function star(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer']);

        return response()->json($this->actions->star($request->user(), $data['ids']));
    }

    /** POST /yard/messages/{message}/pin: toggles, for the whole room. */
    public function pin(Request $request, YardMessage $message): JsonResponse
    {
        $on = $this->actions->togglePin($request->user(), $message);

        return $on === null
            ? response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403)
            : response()->json(['id' => $message->id, 'is_pinned' => $on]);
    }

    /** GET /yard/forward-targets?except={roomId} */
    public function targets(Request $request): JsonResponse
    {
        $except = $request->validate(['except' => 'nullable|integer'])['except'] ?? null;

        return response()->json([
            'rooms' => $this->actions->targets($request->user(), $except ? (int) $except : null)->map(function (YardRoom $r) {
                $isDm = $r->room_type === RoomType::DirectMessage;

                return [
                    'id' => $r->id,
                    'type' => $r->room_type->value,
                    'is_dm' => $isDm,
                    'name' => $isDm && $r->dm_other_name ? $r->dm_other_name : $r->name,
                    'avatar_url' => StorageUrl::for($isDm ? $r->dm_other_avatar : $r->avatar),
                    'emoji' => YardPresenter::ROOM_EMOJI[$r->room_type->value] ?? '💬',
                    'avatar_seed' => $isDm ? 'user:'.($r->dm_other_id ?? $r->id) : 'room:'.$r->id,
                ];
            })->values(),
        ]);
    }

    /** POST /yard/messages/forward {ids: int[], room_id} */
    public function forward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'integer',
            'room_id' => 'required|integer',
        ]);
        $target = YardRoom::find($data['room_id']);
        $result = $target ? $this->actions->forward($request->user(), $data['ids'], $target) : 'not_member';

        if (is_int($result)) {
            return response()->json(['forwarded' => $result, 'room_id' => $target->id]);
        }

        $message = match ($result) {
            'not_connected', 'blocked' => __('Cannot forward to this user.'),
            'nothing' => __('These messages cannot be forwarded.'),
            default => __('You are not a member of that chat.'),
        };

        return response()->json(['message' => $message, 'code' => $result], $result === 'nothing' ? 422 : 403);
    }
}
