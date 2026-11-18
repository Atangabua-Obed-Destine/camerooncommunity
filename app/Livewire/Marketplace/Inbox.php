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
#[Layout('components.layouts.marketplace', ['active' => 'marketplace'])]
#[Title('Inbox · GoMarket')]
class Inbox extends Component
{
    /** 'buying' | 'selling' (mirrors FB's targetTab=BUYER/SELLER). */
    #[Url(except: 'buying')]
    public string $tab = 'buying';

    /** Optional filter to only show conversations about a specific listing. */
    #[Url(as: 'l', except: null)]
    public ?int $filterListingId = null;

    /** Active conversation room id (reflected in the URL like FB). */
    #[Url(as: 'c', except: null)]
    public ?int $activeRoomId = null;

    public string $newMessage = '';

    /** Mobile only: whether the thread pane (vs the list) is showing. */
    public bool $threadOpen = false;

    /**
     * Whether both panes show (lg and up), as reported by the browser. The
     * server can't see the screen, and on a phone the thread is hidden until
     * opened, so it must not be marked read just because it resolved.
     */
    public bool $wide = false;

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
        $me = auth()->user();

        return $me ? app(MarketplaceChatService::class)->conversations($me) : collect();
    }

    #[Computed]
    public function conversations(): Collection
    {
        $filtered = $this->allConversations->where('role', $this->tab);
        if ($this->filterListingId) {
            $filtered = $filtered->filter(fn($c) => $c->listing && $c->listing->id === (int) $this->filterListingId);
        }
        return $filtered->values();
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
            // The newest 200, shown oldest first. orderBy('id')->limit(200) kept the
            // oldest, so past 200 new messages, your own included, never appeared.
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->values();
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
        if ($id && $this->threadVisible()) {
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

    /** Called once by the browser when both panes are on screen. */
    public function showingBothPanes(): void
    {
        $this->wide = true;
        $id = $this->resolvedActiveId();
        if ($id) {
            $this->markRead($id);
            unset($this->allConversations, $this->conversations, $this->counts, $this->active);
        }
    }

    private function threadVisible(): bool
    {
        return $this->wide || $this->threadOpen;
    }

    public function mount(): void
    {
        // A link to a conversation (?c=) opens it, on a phone too, and that one
        // is read. Otherwise nothing is marked until it is actually on screen:
        // see showingBothPanes() and selectRoom().
        if ($this->activeRoomId && $this->resolvedActiveId() === $this->activeRoomId) {
            $this->threadOpen = true;
            $this->markRead($this->activeRoomId);
        }
    }

    public function render()
    {
        return view('livewire.marketplace.inbox');
    }
}
