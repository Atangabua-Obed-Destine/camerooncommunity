<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Events\ConnectionAccepted;
use App\Events\ConnectionRequested;
use App\Events\ConnectionStateChanged;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConnectionService
{
    /**
     * Send (or refresh) a connection request from $from to $to.
     * Idempotent: if a record already exists in any state, returns it without
     * mutating an accepted/blocked relationship.
     */
    public function request(User $from, User $to): UserConnection
    {
        if ($from->id === $to->id) {
            throw new \InvalidArgumentException('Cannot connect with yourself.');
        }

        [$x, $y] = UserConnection::canonicalPair($from->id, $to->id);

        $result = DB::transaction(function () use ($from, $to, $x, $y) {
            $existing = UserConnection::where('user_a_id', $x)
                ->where('user_b_id', $y)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Don't override accepted/blocked
                return ['connection' => $existing, 'created' => false];
            }

            $conn = UserConnection::create([
                'tenant_id'    => $from->tenant_id ?? Tenant::first()?->id,
                'user_a_id'    => $x,
                'user_b_id'    => $y,
                'requested_by' => $from->id,
                'status'       => UserConnection::STATUS_PENDING,
            ]);

            return ['connection' => $conn, 'created' => true];
        });

        // Notify the recipient — DB row + realtime push — only on first request.
        if ($result['created']) {
            $notifId = $this->writeNotification(
                user: $to,
                type: 'connection.requested',
                actor: $from,
                title: 'New connection request',
                body: ($from->username ?: $from->name) . ' wants to connect with you.',
                extra: ['action_url' => route('yard') . '?open=connections&tab=requests'],
            );

            try {
                broadcast(new ConnectionRequested($from, $to, $notifId))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('ConnectionRequested broadcast failed: ' . $e->getMessage());
            }
        }

        return $result['connection'];
    }

    /**
     * Accept a pending request directed at $accepter from $other.
     */
    public function accept(User $accepter, int $otherUserId): bool
    {
        $c = UserConnection::between($accepter->id, $otherUserId);
        if (! $c || $c->status !== UserConnection::STATUS_PENDING) {
            return false;
        }
        // Only the recipient (i.e. NOT the requester) may accept
        if ($c->requested_by === $accepter->id) {
            return false;
        }
        $c->update([
            'status'      => UserConnection::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        // Get the original requester so we can both notify them and ensure a
        // DM room exists for both sides — so the conversation appears in each
        // user's room list immediately, even before the first message is sent.
        $requester = User::find($c->requested_by);
        $dmRoomId = null;
        if ($requester) {
            $dmRoomId = $this->ensureDmRoom($accepter, $requester);
        }

        // Notify the original requester — DB row + realtime push.
        if ($requester) {
            $notifId = $this->writeNotification(
                user: $requester,
                type: 'connection.accepted',
                actor: $accepter,
                title: '🎉 Connection accepted',
                body: ($accepter->username ?: $accepter->name) . ' accepted your connection request.',
                extra: ['action_url' => route('yard'), 'room_id' => $dmRoomId],
            );

            try {
                broadcast(new ConnectionAccepted($accepter, $requester, $notifId))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('ConnectionAccepted broadcast failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Get-or-create the 1:1 DM room between two now-connected users so it
     * shows up in both of their chat lists straight away.
     *
     * Returns the room id (or null on failure).
     */
    protected function ensureDmRoom(User $a, User $b): ?int
    {
        try {
            $existing = YardRoom::where('room_type', RoomType::DirectMessage)
                ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
                ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }

            return DB::transaction(function () use ($a, $b) {
                $tenantId = $a->tenant_id ?? $b->tenant_id;
                $room = YardRoom::create([
                    'tenant_id'      => $tenantId,
                    'name'           => ($a->username ?: $a->name) . ' & ' . ($b->username ?: $b->name),
                    'slug'           => 'dm-' . Str::uuid()->toString(),
                    'country'        => $a->current_country ?? $b->current_country ?? 'Cameroon',
                    'room_type'      => RoomType::DirectMessage,
                    'created_by'     => $a->id,
                    'is_system_room' => false,
                    'members_count'  => 2,
                ]);

                foreach ([$a->id, $b->id] as $memberId) {
                    YardRoomMember::create([
                        'tenant_id' => $tenantId,
                        'room_id'   => $room->id,
                        'user_id'   => $memberId,
                        'role'      => 'member',
                    ]);
                }

                return (int) $room->id;
            });
        } catch (\Throwable $e) {
            Log::warning('ensureDmRoom failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Decline a pending request (recipient only). */
    public function decline(User $decliner, int $otherUserId): bool
    {
        $c = UserConnection::between($decliner->id, $otherUserId);
        if (! $c || $c->status !== UserConnection::STATUS_PENDING) {
            return false;
        }
        if ($c->requested_by === $decliner->id) {
            return false;
        }
        $requesterId = $c->requested_by;
        $c->delete();

        // Notify the original requester so their UI flips from "Pending" → "Connect".
        $this->pushStateChange($decliner, $requesterId, 'declined');
        return true;
    }

    /** Cancel a pending outgoing request (requester only). */
    public function cancel(User $requester, int $otherUserId): bool
    {
        $c = UserConnection::between($requester->id, $otherUserId);
        if (! $c || $c->status !== UserConnection::STATUS_PENDING) {
            return false;
        }
        if ($c->requested_by !== $requester->id) {
            return false;
        }
        $c->delete();

        // Notify the recipient so their incoming-request badge clears.
        $this->pushStateChange($requester, $otherUserId, 'cancelled');
        return true;
    }

    /** Disconnect (either side). */
    public function disconnect(User $user, int $otherUserId): bool
    {
        $c = UserConnection::between($user->id, $otherUserId);
        if (! $c || $c->status !== UserConnection::STATUS_ACCEPTED) {
            return false;
        }
        $c->delete();

        // Notify the other party so their "Connected" state flips.
        $this->pushStateChange($user, $otherUserId, 'disconnected');
        return true;
    }

    /**
     * Block a user. Replaces any existing record with a blocked one
     * owned by $blocker.
     */
    public function block(User $blocker, int $otherUserId): UserConnection
    {
        if ($blocker->id === $otherUserId) {
            throw new \InvalidArgumentException('Cannot block yourself.');
        }

        [$x, $y] = UserConnection::canonicalPair($blocker->id, $otherUserId);

        $row = DB::transaction(function () use ($blocker, $x, $y) {
            $c = UserConnection::where('user_a_id', $x)
                ->where('user_b_id', $y)
                ->lockForUpdate()
                ->first();

            // Only set accepted_at to null if it wasn't previously accepted
            // (so we can restore the connection on unblock if they were connected before)
            $payload = [
                'status'       => UserConnection::STATUS_BLOCKED,
                'requested_by' => $blocker->id,
            ];
            if (! $c || ! $c->accepted_at) {
                $payload['accepted_at'] = null;
            }

            if ($c) {
                $c->update($payload);
                return $c;
            }

            return UserConnection::create(array_merge([
                'tenant_id' => $blocker->tenant_id ?? Tenant::first()?->id,
                'user_a_id' => $x,
                'user_b_id' => $y,
            ], $payload));
        });

        // Notify the blocked user so their UI removes the connection silently.
        $this->pushStateChange($blocker, $otherUserId, 'blocked');
        return $row;
    }

    /** Unblock — only the user who placed the block may lift it. */
    public function unblock(User $unblocker, int $otherUserId): bool
    {
        $c = UserConnection::between($unblocker->id, $otherUserId);
        if (! $c || $c->status !== UserConnection::STATUS_BLOCKED) {
            return false;
        }
        if ($c->requested_by !== $unblocker->id) {
            return false;
        }

        // If they were previously connected (accepted_at is not null), restore the connection.
        // Otherwise, delete the record.
        if ($c->accepted_at) {
            $c->update(['status' => UserConnection::STATUS_ACCEPTED]);
            $this->pushStateChange($unblocker, $otherUserId, 'unblocked-restored');
        } else {
            $c->delete();
            $this->pushStateChange($unblocker, $otherUserId, 'unblocked');
        }

        return true;
    }

    /**
     * Helper: broadcast a ConnectionStateChanged event to the target user.
     * Swallows broadcast errors so user-facing actions never fail because
     * Reverb is down.
     */
    private function pushStateChange(User $actor, int $targetUserId, string $kind): void
    {
        $target = User::find($targetUserId);
        if (! $target) return;
        try {
            broadcast(new ConnectionStateChanged($actor, $target, $kind))->toOthers();
        } catch (\Throwable $e) {
            Log::warning("ConnectionStateChanged({$kind}) broadcast failed: " . $e->getMessage());
        }
    }

    /**
     * Insert a row into the custom `notifications` table for a user.
     * Returns the new notification UUID (or null on failure — never throws).
     */
    private function writeNotification(User $user, string $type, User $actor, string $title, string $body, array $extra = []): ?string
    {
        try {
            $id = (string) Str::uuid();
            DB::table('notifications')->insert([
                'id'              => $id,
                'tenant_id'       => $user->tenant_id ?? Tenant::first()?->id,
                'user_id'         => $user->id,
                'type'            => $type,
                'notifiable_type' => User::class,
                'notifiable_id'   => $user->id,
                'data'            => json_encode(array_merge([
                    'title'      => $title,
                    'body'       => $body,
                    'actor_id'   => $actor->id,
                    'actor_name' => $actor->username ?: $actor->name,
                    'actor_avatar' => $actor->avatar,
                ], $extra)),
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $id;
        } catch (\Throwable $e) {
            Log::warning('writeNotification failed: ' . $e->getMessage());
            return null;
        }
    }
}
