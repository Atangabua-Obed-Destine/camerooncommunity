<?php

namespace App\Services;

use App\Events\ConnectionAccepted;
use App\Events\ConnectionRequested;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConnection;
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

        // Notify the original requester — DB row + realtime push.
        $requester = User::find($c->requested_by);
        if ($requester) {
            $notifId = $this->writeNotification(
                user: $requester,
                type: 'connection.accepted',
                actor: $accepter,
                title: '🎉 Connection accepted',
                body: ($accepter->username ?: $accepter->name) . ' accepted your connection request.',
                extra: ['action_url' => route('yard')],
            );

            try {
                broadcast(new ConnectionAccepted($accepter, $requester, $notifId))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('ConnectionAccepted broadcast failed: ' . $e->getMessage());
            }
        }

        return true;
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
        $c->delete();
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

        return DB::transaction(function () use ($blocker, $x, $y) {
            $c = UserConnection::where('user_a_id', $x)
                ->where('user_b_id', $y)
                ->lockForUpdate()
                ->first();

            $payload = [
                'status'       => UserConnection::STATUS_BLOCKED,
                'requested_by' => $blocker->id,
                'accepted_at'  => null,
            ];

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
        $c->delete();
        return true;
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
