<?php

namespace App\Http\Controllers\Yard;

use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class YardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $rooms = YardRoom::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->withCount('members')
            ->orderByDesc('last_message_at')
            ->get();

        return view('yard.index', compact('rooms'));
    }

    public function room(Request $request, YardRoom $room)
    {
        $user = $request->user();

        $isMember = $room->members()->where('user_id', $user->id)->exists();
        if (! $isMember) {
            abort(403);
        }

        return view('yard.room', compact('room'));
    }

    public function joinRoom(Request $request, YardRoom $room)
    {
        $user = $request->user();

        if (! $room->is_system_room) {
            abort(403);
        }

        YardRoomMember::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], [
            'role' => 'member',
        ]);

        $room->increment('members_count');

        if ($request->wantsJson()) {
            return response()->json(['status' => 'joined']);
        }

        return redirect()->route('yard.room', $room);
    }

    /**
     * Create or open existing Direct Message room between two users.
     */
    public function createDm(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $request->user();
        $targetId = (int) $request->input('user_id');

        if ($targetId === $user->id) {
            return response()->json(['error' => 'Cannot DM yourself'], 422);
        }

        // Find existing DM between these two users
        $existingRoom = YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $targetId))
            ->first();

        if ($existingRoom) {
            return response()->json(['room_id' => $existingRoom->id]);
        }

        $target = User::findOrFail($targetId);

        $room = YardRoom::create([
            'tenant_id' => $user->tenant_id,
            'name' => ($user->username ?? $user->name) . ' & ' . ($target->username ?? $target->name),
            'slug' => 'dm-' . Str::uuid()->toString(),
            'country' => $user->current_country ?? 'Cameroon',
            'room_type' => RoomType::DirectMessage,
            'created_by' => $user->id,
            'is_system_room' => false,
            'members_count' => 2,
        ]);

        foreach ([$user->id, $targetId] as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $user->tenant_id,
                'room_id' => $room->id,
                'user_id' => $memberId,
                'role' => 'member',
            ]);
        }

        return response()->json(['room_id' => $room->id]);
    }

    /**
     * Create a private group room.
     */
    public function createGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        $user = $request->user();
        $name = $request->input('name');
        $memberIds = array_unique(array_merge(
            [$user->id],
            $request->input('member_ids', [])
        ));

        $room = YardRoom::create([
            'tenant_id' => $user->tenant_id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'country' => $user->current_country ?? 'Cameroon',
            'room_type' => RoomType::PrivateGroup,
            'created_by' => $user->id,
            'is_system_room' => false,
            'members_count' => count($memberIds),
        ]);

        foreach ($memberIds as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $user->tenant_id,
                'room_id' => $room->id,
                'user_id' => $memberId,
                'role' => $memberId === $user->id ? 'admin' : 'member',
            ]);
        }

        return response()->json(['room_id' => $room->id]);
    }

    /**
     * Search users for DM / group member selection.
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $user = $request->user();
        $query = $request->input('q');

        $users = User::where('tenant_id', $user->tenant_id)
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('email', 'like', '%' . $query . '%')
                  ->orWhere('username', 'like', '%' . $query . '%');
            })
            ->select('id', 'name', 'username', 'avatar', 'current_region')
            ->limit(20)
            ->get();

        return response()->json($users);
    }
}
