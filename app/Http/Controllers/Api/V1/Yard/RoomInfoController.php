<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Presenters\RoomInfoPresenter;
use App\Models\YardRoom;
use App\Services\Yard\RoomInfoService;
use App\Services\Yard\YardMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A chat's info panel for the app (the web's RoomInfo), through the same
 * RoomInfoService. Only members see a room's panel; admin actions answer 403
 * to everyone else, with a `code` the app can word.
 */
class RoomInfoController extends Controller
{
    public function __construct(
        private RoomInfoService $info,
        private YardMessageService $messages,
        private RoomInfoPresenter $present,
    ) {}

    /** GET /yard/rooms/{room}/info */
    public function show(Request $request, YardRoom $room): JsonResponse
    {
        $me = $this->messages->membership($request->user(), $room);
        if (! $me) {
            return $this->notMember();
        }

        return response()->json($this->present->panel($room, $request->user(), $me));
    }

    /** GET /yard/rooms/{room}/members?q= (the panel's member search) */
    public function members(Request $request, YardRoom $room): JsonResponse
    {
        $q = $request->validate(['q' => 'nullable|string|max:60'])['q'] ?? null;
        if (! $this->info->isMember($request->user(), $room)) {
            return $this->notMember();
        }

        return response()->json([
            'members' => $this->info->isDm($room) ? [] : $this->present->members($this->info->members($room, $q), $room, $request->user()),
        ]);
    }

    /** GET /yard/rooms/{room}/member-candidates?q= ("Add members" search) */
    public function candidates(Request $request, YardRoom $room): JsonResponse
    {
        $q = $request->validate(['q' => 'nullable|string|max:60'])['q'] ?? '';
        if (! $this->info->canAddMembers($request->user(), $room)) {
            return $this->forbidden();
        }

        return response()->json(['users' => $this->present->candidates($this->info->candidates($request->user(), $room, $q))]);
    }

    /** POST /yard/rooms/{room}/members {user_ids: int[]} */
    public function addMembers(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate(['user_ids' => 'required|array|min:1|max:50', 'user_ids.*' => 'integer']);
        if (! $this->info->canAddMembers($request->user(), $room)) {
            return $this->forbidden();
        }

        return response()->json(['added' => $this->info->addMembers($request->user(), $room, $data['user_ids'])]);
    }

    /** DELETE /yard/rooms/{room}/members/{userId}: the admin only. */
    public function removeMember(Request $request, YardRoom $room, int $userId): JsonResponse
    {
        return $this->info->removeMember($request->user(), $room, $userId)
            ? response()->json(['removed' => true])
            : $this->forbidden();
    }

    /** POST /yard/rooms/{room}/avatar (multipart: avatar) */
    public function setAvatar(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate(['avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096']);
        if (! $this->info->setAvatar($request->user(), $room, $data['avatar'])) {
            return $this->forbidden();
        }

        return response()->json(['avatar_url' => \App\Support\StorageUrl::for($room->fresh()->avatar)]);
    }

    /** DELETE /yard/rooms/{room}/avatar */
    public function removeAvatar(Request $request, YardRoom $room): JsonResponse
    {
        return $this->info->removeAvatar($request->user(), $room)
            ? response()->json(['avatar_url' => null])
            : $this->forbidden();
    }

    /** POST /yard/rooms/{room}/requests/{requestId}/approve */
    public function approve(Request $request, YardRoom $room, int $requestId): JsonResponse
    {
        return $this->info->approveRequest($request->user(), $room, $requestId)
            ? response()->json(['approved' => true])
            : $this->forbidden();
    }

    /** POST /yard/rooms/{room}/requests/{requestId}/reject */
    public function reject(Request $request, YardRoom $room, int $requestId): JsonResponse
    {
        return $this->info->rejectRequest($request->user(), $room, $requestId)
            ? response()->json(['rejected' => true])
            : $this->forbidden();
    }

    /** POST /yard/rooms/{room}/leave */
    public function leave(Request $request, YardRoom $room): JsonResponse
    {
        $why = $this->info->leave($request->user(), $room);

        return match ($why) {
            null => response()->json(['left' => true]),
            'admin' => response()->json(['message' => __('Group admins cannot leave their own group. Delete it instead.'), 'code' => 'admin'], 422),
            'system_room' => response()->json(['message' => __('You cannot leave system rooms.'), 'code' => 'system_room'], 422),
            default => $this->notMember(),
        };
    }

    /** POST /yard/rooms/{room}/report {reason, details?} */
    public function report(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|in:'.implode(',', array_column(ReportReason::cases(), 'value')),
            'details' => 'nullable|string|max:1000',
        ]);
        if (! $this->info->isMember($request->user(), $room)) {
            return $this->notMember();
        }

        return response()->json([
            'reported' => $this->info->report($request->user(), $room, $data['reason'], $data['details'] ?? ''),
        ]);
    }

    /** POST /yard/rooms/{room}/block: the other person in this DM. */
    public function block(Request $request, YardRoom $room): JsonResponse
    {
        return $this->info->block($request->user(), $room)
            ? response()->json(['connection_state' => 'blocked-by-me'])
            : $this->forbidden();
    }

    /** POST /yard/rooms/{room}/unblock */
    public function unblock(Request $request, YardRoom $room): JsonResponse
    {
        if (! $this->info->isMember($request->user(), $room) || ! $this->info->isDm($room)) {
            return $this->forbidden();
        }

        return response()->json(['unblocked' => $this->info->unblock($request->user(), $room)]);
    }

    /** PUT /yard/rooms/{room}/nickname {nickname}: blank clears it. */
    public function nickname(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate(['nickname' => 'nullable|string|max:60']);
        if (! $this->info->isMember($request->user(), $room) || ! $this->info->isDm($room)) {
            return $this->forbidden();
        }

        return response()->json(['nickname' => $this->info->saveNickname($request->user(), $room, (string) ($data['nickname'] ?? ''))]);
    }

    private function notMember(): JsonResponse
    {
        return response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['message' => __('You cannot do that in this chat.'), 'code' => 'forbidden'], 403);
    }
}
