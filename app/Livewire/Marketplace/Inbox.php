<?php

namespace App\Livewire\Marketplace;

use App\Enums\MessageType;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\MarketplaceChatService;
use App\Services\ShareableResolver;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * GoMarket Inbox — Facebook-Marketplace-style two-pane inbox.
 * Buying / Selling tabs, a conversation list (product + partner + last message),
 * and the active thread with the product pinned + composer. Marketplace-scoped
 * (only origin='marketplace' DM rooms); no connection gate.
 */
#[Layout('components.layouts.rails', ['active' => 'marketplace'])]
#[Title('Inbox · GoMarket')]
class Inbox extends Component
{
    /** 'buying' | 'selling' (mirrors FB's targetTab=BUYER/SELLER). */
    #[Url(except: 'buying')]
    public string $tab = 'buying';

    /** Active conversation room id (reflected in the URL like FB). */
    #[Url(as: 'c', except: null)]
    public ?int $activeRoomId = null;

    public string $newMessage = '';

    /** Mobile only: whether the thread pane (vs the list) is showing. */
    public bool $threadOpen = false;

    public function updatedTab(): void
    {
        $this->activeRoomId = null;
        $this->threadOpen = false;
        unset($this->conversations, $this->messages, $this->active);
    }

    public function backToList(): void
    {
        $this->threadOpen = false;
    }

    /** All of the viewer's marketplace conversations (both roles). */
    #[Computed]
    public function allConversations(): Collection
    {
        $me = auth()->id();
        if (! $me) {
            return collect();
        }
        $svc = app(MarketplaceChatService::class);

        return YardRoom::where('origin', 'marketplace')
            ->whereHas('members', fn ($q) => $q->where('user_id', $me))
            ->with(['members.user'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (YardRoom $room) use ($me, $svc) {
                $listing = $svc->listingForRoom($room);
                $role = ($listing && (int) $listing->user_id === (int) $me) ? 'selling' : 'buying';
                $myMember = $room->members->firstWhere('user_id', $me);
                $partner = $room->members->first(fn ($m) => (int) $m->user_id !== (int) $me)?->user;

                $unread = YardMessage::where('room_id', $room->id)
                    ->where('user_id', '!=', $me)
                    ->where('is_deleted', false)
                    ->when($myMember?->last_read_at, fn ($q, $lr) => $q->where('created_at', '>', $lr))
                    ->count();

                return (object) [
                    'room_id' => $room->id,
                    'tenant_id' => $room->tenant_id,
                    'listing' => $listing,
                    'partner' => $partner,
                    'preview' => $room->last_message_preview,
                    'time'    => $room->last_message_at,
                    'unread'  => $unread,
                    'role'    => $role,
                ];
            });
    }

    #[Computed]
    public function conversations(): Collection
    {
        return $this->allConversations->where('role', $this->tab)->values();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'buying'  => (int) $this->allConversations->where('role', 'buying')->sum('unread'),
            'selling' => (int) $this->allConversations->where('role', 'selling')->sum('unread'),
        ];
    }

    /** Resolve which conversation is open: the chosen one (if valid) else the first. */
    public function resolvedActiveId(): ?int
    {
        $convs = $this->conversations;
        if ($this->activeRoomId && $convs->firstWhere('room_id', $this->activeRoomId)) {
            return $this->activeRoomId;
        }
        return $convs->first()->room_id ?? null;
    }

    #[Computed]
    public function active(): ?object
    {
        $id = $this->resolvedActiveId();
        return $id ? $this->conversations->firstWhere('room_id', $id) : null;
    }

    #[Computed]
    public function messages(): Collection
    {
        $id = $this->resolvedActiveId();
        if (! $id) {
            return collect();
        }
        return YardMessage::with('user:id,name,username,avatar')
            ->where('room_id', $id)
            ->where('is_deleted', false)
            ->whereIn('message_type', [MessageType::Text->value, MessageType::ShareCard->value, MessageType::Image->value])
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    public function channelName(): ?string
    {
        $a = $this->active;
        return $a ? 'tenant.' . $a->tenant_id . '.room.' . $a->room_id : null;
    }

    public function cardPreview(YardMessage $m): ?array
    {
        if ($m->message_type !== MessageType::ShareCard || ! $m->shareable_id) {
            return null;
        }
        $resolver = app(ShareableResolver::class);
        return $resolver->preview($resolver->resolve('marketplace', (int) $m->shareable_id));
    }

    public function selectRoom(int $roomId): void
    {
        // Only allow rooms the viewer participates in.
        if (! $this->allConversations->firstWhere('room_id', $roomId)) {
            return;
        }
        $this->activeRoomId = $roomId;
        $this->threadOpen = true;
        $this->markRead($roomId);
        unset($this->messages, $this->active, $this->allConversations, $this->conversations, $this->counts);
        $this->dispatch('inbox-scroll');
    }

    public function send(): void
    {
        $me = auth()->user();
        $id = $this->resolvedActiveId();
        if (! $me || ! $id) {
            return;
        }
        $room = YardRoom::find($id);
        if (! $room) {
            return;
        }
        app(MarketplaceChatService::class)->postMessage($room, $me, $this->newMessage);
        $this->newMessage = '';
        $this->markRead($id);
        unset($this->messages, $this->allConversations, $this->conversations, $this->counts);
        $this->dispatch('inbox-scroll');
    }

    #[On('inbox-refresh')]
    public function refresh(): void
    {
        $id = $this->resolvedActiveId();
        if ($id) {
            $this->markRead($id);
        }
        unset($this->messages, $this->allConversations, $this->conversations, $this->counts, $this->active);
        $this->dispatch('inbox-scroll');
    }

    protected function markRead(int $roomId): void
    {
        YardRoomMember::where('room_id', $roomId)
            ->where('user_id', auth()->id())
            ->update(['last_read_at' => now()]);

        // Keep the sidebar Inbox badge fresh.
        \Illuminate\Support\Facades\Cache::forget('mp:inbox-unread:' . auth()->id());
    }

    public function mount(): void
    {
        // Mark the opened conversation read on first load.
        $id = $this->resolvedActiveId();
        if ($id) {
            $this->markRead($id);
        }
    }

    public function render()
    {
        return view('livewire.marketplace.inbox');
    }
}
