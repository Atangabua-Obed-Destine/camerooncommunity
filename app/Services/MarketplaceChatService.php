<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Events\MessageSent;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Opens (or reuses) a buyer↔seller direct-message conversation started from a
 * GoMarket listing. Unlike a normal Yard DM, this does NOT require a mutual
 * connection — exactly like Facebook Marketplace, where a buyer can message a
 * seller about an item without being friends. Blocks are still respected.
 *
 * The room is tagged origin = 'marketplace' so the chat input bypasses the
 * connection gate in both the floating dock and the full Yard.
 */
class MarketplaceChatService
{
    /**
     * @return array{room: YardRoom, error?: string}|array{error: string}
     */
    public function openConversation(User $buyer, User $seller, ?MarketplaceListing $listing = null): array
    {
        if ($buyer->id === $seller->id) {
            return ['error' => 'self'];
        }

        // Either side blocking the other stops the conversation.
        if ($buyer->hasBlockedOrIsBlockedBy($seller->id)) {
            return ['error' => 'blocked'];
        }

        $room = $this->findOrCreateRoom($buyer, $seller);

        if ($listing) {
            $this->seedListingCard($room, $buyer, $listing);
        }

        return ['room' => $room];
    }

    /**
     * Post a text message into a marketplace conversation (shared by the
     * floating dock and the inbox). Broadcasts + updates room meta.
     */
    public function postMessage(YardRoom $room, User $sender, string $text, ?int $parentMessageId = null): ?YardMessage
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $isMember = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $sender->id)->exists();
        if (! $isMember) {
            return null;
        }

        $message = YardMessage::create([
            'tenant_id'    => $sender->tenant_id,
            'uuid'         => Str::uuid()->toString(),
            'room_id'      => $room->id,
            'user_id'      => $sender->id,
            'parent_message_id' => $parentMessageId,
            'message_type' => MessageType::Text,
            'content'      => mb_substr($text, 0, 4000),
        ]);

        $room->forceFill([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($text, 100),
            'last_message_user_id' => $sender->id,
            'messages_count'       => ($room->messages_count ?? 0) + 1,
        ])->save();

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Marketplace postMessage broadcast failed: ' . $e->getMessage());
        }

        return $message;
    }

    /** The listing a marketplace conversation is about (latest seeded card). */
    public function listingForRoom(YardRoom $room): ?MarketplaceListing
    {
        $card = YardMessage::where('room_id', $room->id)
            ->where('shareable_type', MarketplaceListing::class)
            ->latest('id')->first();

        return $card ? MarketplaceListing::with('media')->find($card->shareable_id) : null;
    }

    protected function findOrCreateRoom(User $buyer, User $seller): YardRoom
    {
        $existing = YardRoom::where('room_type', RoomType::DirectMessage)
            ->whereHas('members', fn ($q) => $q->where('user_id', $buyer->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $seller->id))
            ->first();

        if ($existing) {
            // Promote a pre-existing DM to a marketplace conversation so the
            // input gate relaxes once they start talking about an item.
            if ($existing->origin !== 'marketplace') {
                $existing->forceFill(['origin' => 'marketplace'])->save();
            }
            return $existing;
        }

        $room = YardRoom::create([
            'tenant_id'     => $buyer->tenant_id,
            'name'          => ($buyer->username ?? $buyer->name) . ' & ' . ($seller->username ?? $seller->name),
            'slug'          => 'dm-' . Str::uuid()->toString(),
            'country'       => $buyer->current_country ?? 'Cameroon',
            'room_type'     => RoomType::DirectMessage,
            'origin'        => 'marketplace',
            'created_by'    => $buyer->id,
            'is_system_room' => false,
            'members_count' => 2,
        ]);

        foreach ([$buyer->id, $seller->id] as $memberId) {
            YardRoomMember::create([
                'tenant_id' => $buyer->tenant_id,
                'room_id'   => $room->id,
                'user_id'   => $memberId,
                'role'      => 'member',
            ]);
        }

        return $room;
    }

    /**
     * Drop the product card into the thread once per listing, so both parties
     * see what the conversation is about (FB shows the item card in-thread).
     */
    protected function seedListingCard(YardRoom $room, User $buyer, MarketplaceListing $listing): void
    {
        $already = YardMessage::where('room_id', $room->id)
            ->where('message_type', MessageType::ShareCard)
            ->where('shareable_type', MarketplaceListing::class)
            ->where('shareable_id', $listing->id)
            ->exists();

        if ($already) {
            return;
        }

        $message = YardMessage::create([
            'tenant_id'      => $buyer->tenant_id,
            'uuid'           => Str::uuid()->toString(),
            'room_id'        => $room->id,
            'user_id'        => $buyer->id,
            'message_type'   => MessageType::ShareCard,
            'content'        => $listing->title,
            'shareable_type' => MarketplaceListing::class,
            'shareable_id'   => $listing->id,
        ]);

        $room->forceFill([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit('🛍️ ' . $listing->title, 100),
            'last_message_user_id' => $buyer->id,
            'messages_count'       => ($room->messages_count ?? 0) + 1,
        ])->save();

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('Marketplace chat seed broadcast failed: ' . $e->getMessage());
        }
    }
}
