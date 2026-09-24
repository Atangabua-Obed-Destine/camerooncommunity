<?php

namespace App\Services;

use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Models\YardMessage;
use App\Models\YardMessageRead;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp-style delivery + read receipts for Yard messages.
 *
 * Tick semantics (own messages only):
 *   - sent       (single grey  ✓ )    : message persisted server-side
 *   - delivered  (double grey  ✓✓)    : every recipient has a row with delivered_at set
 *   - read       (double blue  ✓✓)    : every recipient has read_at set
 */
class ReceiptService
{
    /**
     * Mark a single message as delivered to the given user.
     * Called when the recipient's browser receives the broadcast.
     */
    public function markDelivered(YardMessage $message, int $userId): void
    {
        // Don't track delivery to the sender themselves.
        if ($message->user_id === $userId) {
            return;
        }

        $now = Carbon::now();

        // Upsert (delivered_at only set on insert; do not overwrite existing).
        $existing = YardMessageRead::where('message_id', $message->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->delivered_at === null) {
                $existing->delivered_at = $now;
                $existing->save();
            } else {
                // Already delivered — nothing to broadcast.
                return;
            }
        } else {
            YardMessageRead::create([
                'tenant_id' => $message->tenant_id,
                'message_id' => $message->id,
                'user_id' => $userId,
                'delivered_at' => $now,
                'read_at' => null,
            ]);
        }

        $allDelivered = $this->isAllDelivered($message);

        try {
            broadcast(new MessageDelivered(
                tenantId: $message->tenant_id,
                senderId: $message->user_id,
                messageId: $message->id,
                roomId: $message->room_id,
                byUserId: $userId,
                deliveredAt: $now->toIso8601String(),
                allDelivered: $allDelivered
            ));
        } catch (\Throwable $e) {
            \Log::warning('Broadcast MessageDelivered failed: '.$e->getMessage());
        }
    }

    /**
     * Mark all unread messages in a room as read by the given user.
     * Returns array of [senderId => [messageIds...]] that were just transitioned.
     */
    public function markRoomRead(YardRoom $room, int $userId): void
    {
        $now = Carbon::now();

        // Find unread messages from other users in this room (no read_at yet, or no row at all).
        $messages = YardMessage::where('room_id', $room->id)
            ->where('user_id', '!=', $userId)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->whereDoesntHave('reads', function ($r) use ($userId) {
                    $r->where('user_id', $userId);
                })->orWhereHas('reads', function ($r) use ($userId) {
                    $r->where('user_id', $userId)->whereNull('read_at');
                });
            })
            ->get(['id', 'tenant_id', 'user_id', 'room_id']);

        if ($messages->isEmpty()) {
            return;
        }

        // Group messages by sender for batched broadcasts.
        $bySender = $messages->groupBy('user_id');

        // Bulk, not per message: opening a busy room used to run a SELECT plus an
        // INSERT/UPDATE for every unread message, then another two queries per
        // message to work out the tick state. That was the bulk of the delay when
        // opening a chat.
        $messageIds = $messages->pluck('id')->all();

        DB::transaction(function () use ($messages, $messageIds, $userId, $now) {
            $existingIds = YardMessageRead::whereIn('message_id', $messageIds)
                ->where('user_id', $userId)
                ->pluck('message_id')
                ->all();

            if ($existingIds) {
                YardMessageRead::whereIn('message_id', $existingIds)
                    ->where('user_id', $userId)
                    ->update([
                        'read_at' => $now,
                        // Keep the original delivery time if we already had one.
                        'delivered_at' => DB::raw("COALESCE(delivered_at, '" . $now->toDateTimeString() . "')"),
                    ]);
            }

            $missing = $messages->whereNotIn('id', $existingIds)->map(fn ($msg) => [
                'tenant_id'    => $msg->tenant_id,
                'message_id'   => $msg->id,
                'user_id'      => $userId,
                'delivered_at' => $now,
                'read_at'      => $now,
            ])->all();

            if ($missing) {
                // upsert, not insert: (message_id, user_id) is unique and two
                // tabs can open the same room at the same moment.
                YardMessageRead::upsert($missing, ['message_id', 'user_id'], ['read_at', 'delivered_at']);
            }
        });

        // Tick state for all of them in two queries rather than two per message.
        $recipients = $this->recipientCount($room->id);
        $readCounts = YardMessageRead::whereIn('message_id', $messageIds)
            ->whereNotNull('read_at')
            ->selectRaw('message_id, COUNT(*) as c')
            ->groupBy('message_id')
            ->pluck('c', 'message_id');

        foreach ($bySender as $senderId => $msgs) {
            $messageIds = $msgs->pluck('id')->all();
            $allReadMap = [];
            foreach ($msgs as $m) {
                $allReadMap[$m->id] = $recipients <= 0 || (int) ($readCounts[$m->id] ?? 0) >= $recipients;
            }

            try {
                broadcast(new MessageRead(
                    tenantId: $msgs->first()->tenant_id,
                    senderId: (int) $senderId,
                    roomId: $room->id,
                    byUserId: $userId,
                    messageIds: $messageIds,
                    readAt: $now->toIso8601String(),
                    allReadMap: $allReadMap
                ));
            } catch (\Throwable $e) {
                \Log::warning('Broadcast MessageRead failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Compute the tick status for a single own message.
     * Returns one of: 'sent', 'delivered', 'read'.
     */
    public function statusFor(YardMessage $message): string
    {
        if ($this->isAllRead($message)) {
            return 'read';
        }
        if ($this->isAllDelivered($message)) {
            return 'delivered';
        }
        return 'sent';
    }

    /**
     * Bulk compute statuses for a collection of own messages — single query per metric.
     *
     * @param  iterable<YardMessage>  $messages
     * @return array<int, string>     [messageId => status]
     */
    public function bulkStatusFor(iterable $messages, int $roomId): array
    {
        $ids = collect($messages)->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $recipients = $this->recipientCount($roomId);
        if ($recipients <= 0) {
            // No one else in the room (e.g. note-to-self) — always "read".
            return array_fill_keys($ids, 'read');
        }

        $rows = YardMessageRead::whereIn('message_id', $ids)
            ->selectRaw('message_id, COUNT(delivered_at) as d, COUNT(read_at) as r')
            ->groupBy('message_id')
            ->get()
            ->keyBy('message_id');

        $out = [];
        foreach ($ids as $id) {
            $row = $rows->get($id);
            $d = $row?->d ?? 0;
            $r = $row?->r ?? 0;
            if ($r >= $recipients) {
                $out[$id] = 'read';
            } elseif ($d >= $recipients) {
                $out[$id] = 'delivered';
            } else {
                $out[$id] = 'sent';
            }
        }
        return $out;
    }

    private function recipientCount(int $roomId): int
    {
        // Number of room members other than the sender. We assume sender IS a member;
        // total members - 1.
        $total = YardRoomMember::where('room_id', $roomId)->count();
        return max(0, $total - 1);
    }

    private function isAllDelivered(YardMessage $message): bool
    {
        $recipients = $this->recipientCount($message->room_id);
        if ($recipients <= 0) {
            return true;
        }
        $count = YardMessageRead::where('message_id', $message->id)
            ->whereNotNull('delivered_at')
            ->count();
        return $count >= $recipients;
    }

    private function isAllRead(YardMessage $message): bool
    {
        $recipients = $this->recipientCount($message->room_id);
        if ($recipients <= 0) {
            return true;
        }
        $count = YardMessageRead::where('message_id', $message->id)
            ->whereNotNull('read_at')
            ->count();
        return $count >= $recipients;
    }
}
