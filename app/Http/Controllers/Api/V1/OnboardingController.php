<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\YardRoom;
use App\Services\Onboarding;
use App\Services\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The four onboarding steps (web: /welcome), through App\Services\Onboarding
 * so both clients do the same thing. The app owns which step is showing; the
 * server only records what each step changes.
 */
class OnboardingController extends Controller
{
    public function __construct(private Onboarding $onboarding) {}

    /** Everything the four steps show. Also makes sure the rooms exist. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->onboarding->prepare($user);
        $user->refresh();

        $rooms = $this->onboarding->communityRooms($user);

        return response()->json([
            'welcome' => $this->onboarding->welcomeMessage($user),
            'member_count' => $this->onboarding->memberCount($user),
            'founding_cap' => Registration::foundingCap($user->tenant),
            'rooms' => $rooms->map(fn (YardRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'description' => $room->description,
                'room_type' => $room->room_type?->value,
                'is_national' => $room->room_type === RoomType::National,
                'members_count' => (int) $room->members_count,
                // "💬 Active" on the card: the room has had a message.
                'active' => $room->last_message_at !== null,
                // Both rooms shown are the defaults: joined automatically and locked.
                'is_default' => true,
            ])->values(),
            'user' => new UserResource($user),
        ]);
    }

    /** Kamer's reply in the onboarding chat. The app keeps the transcript. */
    public function kamer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => 'required|array|min:1|max:40',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string|max:2000',
        ]);

        return response()->json([
            'reply' => $this->onboarding->kamerReply($request->user(), $data['messages']),
        ]);
    }

    /** Join the rooms step 2 shows. */
    public function rooms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_ids' => 'present|array|max:20',
            'room_ids.*' => 'integer',
        ]);

        $this->onboarding->joinRooms($request->user(), $data['room_ids']);

        return response()->json(['joined' => count($data['room_ids'])]);
    }

    /** Step 4's "Enter GoConnect": onboarding is done. */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->onboarding->complete($user);

        return response()->json(['user' => new UserResource($user->refresh())]);
    }
}
