<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($from, $to, $x, $y) {
            $existing = UserConnection::where('user_a_id', $x)
                ->where('user_b_id', $y)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Don't override accepted/blocked
                return $existing;
            }

            return UserConnection::create([
                'tenant_id'    => $from->tenant_id ?? Tenant::first()?->id,
                'user_a_id'    => $x,
                'user_b_id'    => $y,
                'requested_by' => $from->id,
                'status'       => UserConnection::STATUS_PENDING,
            ]);
        });
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
}
