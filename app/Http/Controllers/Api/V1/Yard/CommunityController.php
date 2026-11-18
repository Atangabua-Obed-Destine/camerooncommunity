<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Http\Presenters\YardPresenter;
use App\Models\User;
use App\Models\YardRoom;
use App\Services\Yard\CommunityService;
use App\Support\StorageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The Communities sheet, for the app (Livewire Communities' twin): the
 * member's groups, the ones to discover, join / ask / leave, and a new group.
 */
class CommunityController extends Controller
{
    public function __construct(private CommunityService $communities) {}

    /** GET /yard/communities?search= : both tabs at once, with their counts. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:100']);
        $user = $request->user();
        $pending = $this->communities->pendingRequestRoomIds($user);

        $mine = $this->communities->mine($user, $data['search'] ?? null);
        $discover = $this->communities->discover($user, $data['search'] ?? null);

        return response()->json([
            'mine' => $this->cards($mine, $user, true, $pending),
            'discover' => $this->cards($discover, $user, false, $pending),
        ]);
    }

    /** POST /yard/communities {name, description?, is_private?} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'description' => 'nullable|string|max:500',
            'is_private' => 'nullable|boolean',
        ]);

        $room = $this->communities->create(
            $request->user(),
            trim($data['name']),
            isset($data['description']) ? trim($data['description']) : null,
            (bool) ($data['is_private'] ?? true),
        );

        return response()->json(['room_id' => $room->id], 201);
    }

    /** POST /yard/communities/{room}/join: joins, or asks a private group. */
    public function join(Request $request, int $room): JsonResponse
    {
        $result = $this->communities->join($request->user(), $room);
        if ($result === null) {
            return response()->json(['message' => __('This community cannot be joined.'), 'code' => 'not_joinable'], 422);
        }

        return response()->json(['result' => $result, 'room_id' => $room]);
    }

    /** DELETE /yard/communities/{room}/request: withdraws a pending ask. */
    public function cancelRequest(Request $request, int $room): JsonResponse
    {
        $this->communities->cancelRequest($request->user(), $room);

        return response()->json(['ok' => true]);
    }

    /** POST /yard/communities/{room}/leave */
    public function leave(Request $request, int $room): JsonResponse
    {
        $result = $this->communities->leave($request->user(), $room);

        if ($result === CommunityService::LEAVE_DEFAULT) {
            return response()->json([
                'message' => __("You can't leave your default country or regional group."),
                'code' => 'default_room',
            ], 422);
        }
        if ($result !== true) {
            return response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 422);
        }

        return response()->json(['ok' => true]);
    }

    /** A community card (partials/community-card.blade.php). */
    private function cards(Collection $rooms, User $user, bool $isMember, array $pending): array
    {
        return $rooms->map(function (YardRoom $room) use ($isMember, $pending) {
            $isDefault = in_array($room->room_type, [RoomType::National, RoomType::Regional], true);

            return [
                'id' => $room->id,
                'type' => $room->room_type->value,
                'name' => $room->name,
                'description' => $room->description,
                'avatar_url' => StorageUrl::for($room->avatar),
                'emoji' => YardPresenter::ROOM_EMOJI[$room->room_type->value] ?? '💬',
                'members_count' => (int) ($room->live_members_count ?? $room->members_count ?? 0),
                'messages_count' => (int) ($room->messages_count ?? 0),
                'is_private' => (bool) $room->is_private,
                'is_default' => $isDefault,
                'is_member' => $isMember,
                'pending' => ! $isMember && in_array((int) $room->id, array_map('intval', $pending), true),
            ];
        })->values()->all();
    }
}
