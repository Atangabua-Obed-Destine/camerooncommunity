<?php

namespace App\Livewire\Marketplace;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\MarketplaceChatService;
use App\Services\ShareableResolver;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Facebook-Marketplace-style floating chat dock. Mounted app-wide. Opens a
 * buyer↔seller conversation referencing a listing, with NO connection request
 * required (see MarketplaceChatService). Realtime via Reverb on the room's
 * public channel — same as the full Yard.
 */
class ChatDock extends Component
{
    public bool $open = false;
    public bool $minimized = false;
    public ?int $roomId = null;
    public ?int $listingId = null;
    public string $newMessage = '';

    /** Opened from a listing/seller profile: $dispatch('open-gomarket-chat', {sellerId, listingId}). */
    #[On('open-gomarket-chat')]
    public function openFor(int $sellerId, ?int $listingId = null): void
    {
        $buyer = auth()->user();
        if (! $buyer) {
            $this->redirectRoute('login');
            return;
        }

        $seller = User::where('tenant_id', $buyer->tenant_id)->find($sellerId);
        if (! $seller) {
            $this->dispatch('toast', type: 'error', message: __('Seller unavailable.'));
            return;
        }

        $listing = $listingId ? MarketplaceListing::find($listingId) : null;

        $result = app(MarketplaceChatService::class)->openConversation($buyer, $seller, $listing);

        if (isset($result['error'])) {
            $msg = $result['error'] === 'blocked'
                ? __('You can no longer message this user.')
                : __('Could not open the conversation.');
            $this->dispatch('toast', type: 'error', message: $msg);
            return;
        }

        $this->roomId    = $result['room']->id;
        $this->listingId = $listing?->id;
        $this->open      = true;
        $this->minimized = false;

        // FB-style prefill on a brand-new conversation.
        if ($this->newMessage === '' && ! $this->hasOwnText()) {
            $this->newMessage = __('Is this still available?');
        }

        unset($this->room, $this->messages, $this->partner, $this->listing);
        $this->markRead();
        $this->dispatch('gomarket-scroll');
    }

    public function close(): void
    {
        $this->open = false;
        $this->minimized = false;
    }

    public function toggleMinimize(): void
    {
        $this->minimized = ! $this->minimized;
        if (! $this->minimized) {
            $this->markRead();
            $this->dispatch('gomarket-scroll');
        }
    }

    #[Computed]
    public function room(): ?YardRoom
    {
        return $this->roomId ? YardRoom::find($this->roomId) : null;
    }

    #[Computed]
    public function partner(): ?User
    {
        if (! $this->room) {
            return null;
        }
        $partnerId = YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', '!=', auth()->id())
            ->value('user_id');

        return $partnerId ? User::find($partnerId) : null;
    }

    #[Computed]
    public function listing(): ?MarketplaceListing
    {
        if ($this->listingId) {
            return MarketplaceListing::with('media')->find($this->listingId);
        }
        // Fall back to the latest listing card seeded into this room.
        if ($this->room) {
            $card = YardMessage::where('room_id', $this->room->id)
                ->where('shareable_type', MarketplaceListing::class)
                ->latest('id')->first();
            if ($card) {
                return MarketplaceListing::with('media')->find($card->shareable_id);
            }
        }
        return null;
    }

    #[Computed]
    public function messages()
    {
        if (! $this->room) {
            return collect();
        }
        return YardMessage::with('user:id,name,username,avatar')
            ->where('room_id', $this->room->id)
            ->where('is_deleted', false)
            ->whereIn('message_type', [MessageType::Text->value, MessageType::ShareCard->value, MessageType::Image->value])
            ->orderBy('id')
            ->limit(80)
            ->get();
    }

    public function channelName(): ?string
    {
        return $this->room ? 'tenant.' . $this->room->tenant_id . '.room.' . $this->room->id : null;
    }

    /** Renders a share_card listing into a product mini-card payload. */
    public function cardPreview(YardMessage $m): ?array
    {
        if ($m->message_type !== MessageType::ShareCard || ! $m->shareable_id) {
            return null;
        }
        $resolver = app(ShareableResolver::class);
        return $resolver->preview($resolver->resolve('marketplace', (int) $m->shareable_id));
    }

    public function send(): void
    {
        $buyer = auth()->user();
        $room = $this->room;
        if (! $buyer || ! $room) {
            return;
        }

        $text = trim($this->newMessage);
        if ($text === '') {
            return;
        }

        $isMember = YardRoomMember::where('room_id', $room->id)
            ->where('user_id', $buyer->id)->exists();
        if (! $isMember) {
            return;
        }

        // No connection gate — marketplace conversations are open (FB-style).
        $message = YardMessage::create([
            'tenant_id'    => $buyer->tenant_id,
            'uuid'         => Str::uuid()->toString(),
            'room_id'      => $room->id,
            'user_id'      => $buyer->id,
            'message_type' => MessageType::Text,
            'content'      => mb_substr($text, 0, 4000),
        ]);

        $room->forceFill([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($text, 100),
            'last_message_user_id' => $buyer->id,
            'messages_count'       => ($room->messages_count ?? 0) + 1,
        ])->save();

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('GoMarket dock broadcast failed: ' . $e->getMessage());
        }

        $this->newMessage = '';
        unset($this->messages);
        $this->dispatch('gomarket-scroll');
    }

    /** Called by the Echo listener when a realtime message arrives. */
    public function refreshThread(): void
    {
        unset($this->messages);
        $this->markRead();
        $this->dispatch('gomarket-scroll');
    }

    protected function hasOwnText(): bool
    {
        if (! $this->room) {
            return false;
        }
        return YardMessage::where('room_id', $this->room->id)
            ->where('user_id', auth()->id())
            ->where('message_type', MessageType::Text->value)
            ->exists();
    }

    protected function markRead(): void
    {
        if (! $this->room) {
            return;
        }
        YardRoomMember::where('room_id', $this->room->id)
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.marketplace.chat-dock');
    }
}
