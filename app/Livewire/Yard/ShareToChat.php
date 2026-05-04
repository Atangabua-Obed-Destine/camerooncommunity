<?php

namespace App\Livewire\Yard;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Events\MessageSent;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\ShareableResolver;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Universal "Share to chat" modal.
 *
 * Opened from anywhere in the app via:
 *   $this->dispatch('open-share-to-chat', type: 'solidarity', id: 42);
 * or in a Blade:
 *   <button @click="$dispatch('open-share-to-chat',{type:'event',id:9})">
 *
 * Lets the current user pick one of their rooms and posts a
 * `share_card` message that points back to the resource.
 */
class ShareToChat extends Component
{
    public bool $visible = false;
    public ?string $shareType = null;
    public ?int $shareId = null;
    public string $search = '';
    public ?string $note = null;

    protected $listeners = [
        'open-share-to-chat' => 'open',
    ];

    public function open(string $type, int $id, ?string $note = null): void
    {
        if (!array_key_exists($type, ShareableResolver::TYPES)) {
            $this->dispatch('toast', type: 'error', message: __('This item cannot be shared.'));
            return;
        }

        $this->shareType = $type;
        $this->shareId   = $id;
        $this->note      = $note;
        $this->search    = '';
        $this->visible   = true;
    }

    public function close(): void
    {
        $this->visible = false;
        $this->shareType = null;
        $this->shareId = null;
        $this->note = null;
        $this->search = '';
    }

    #[Computed]
    public function preview(): ?array
    {
        if (!$this->shareType || !$this->shareId) {
            return null;
        }
        $resolver = app(ShareableResolver::class);
        return $resolver->preview($resolver->resolve($this->shareType, $this->shareId));
    }

    #[Computed]
    public function rooms()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $query = YardRoom::query()
            ->whereIn('id', YardRoomMember::where('user_id', $user->id)->pluck('room_id'))
            ->orderByDesc('last_message_at')
            ->limit(40);

        if ($this->search !== '') {
            $like = '%' . $this->search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like);
            });
        }

        return $query->get();
    }

    public function shareTo(int $roomId): void
    {
        $user = auth()->user();
        if (!$user || !$this->shareType || !$this->shareId) {
            return;
        }

        $member = YardRoomMember::where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->first();
        if (!$member) {
            return;
        }

        $room = YardRoom::find($roomId);
        if (!$room) {
            return;
        }

        // Block share-into-DM if not connected.
        if ($room->room_type === RoomType::DirectMessage) {
            $partnerId = $room->members()->where('user_id', '!=', $user->id)->value('user_id');
            if ($partnerId && !$user->isConnectedWith((int) $partnerId)) {
                $this->dispatch('toast', type: 'warning', message: __('You must connect with this user before sharing.'));
                return;
            }
        }

        $resolver = app(ShareableResolver::class);
        $model = $resolver->resolve($this->shareType, $this->shareId);
        if (!$model) {
            $this->dispatch('toast', type: 'error', message: __('Item no longer available.'));
            return;
        }

        $preview = $resolver->preview($model);

        $message = YardMessage::create([
            'tenant_id'      => $user->tenant_id,
            'uuid'           => Str::uuid()->toString(),
            'room_id'        => $roomId,
            'user_id'        => $user->id,
            'message_type'   => MessageType::ShareCard,
            'content'        => $this->note ?: ($preview['title'] ?? null),
            'shareable_type' => get_class($model),
            'shareable_id'   => $model->getKey(),
        ]);

        // Update room meta.
        $room->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit('🔗 ' . ($preview['title'] ?? __('Shared item')), 100),
            'last_message_user_id' => $user->id,
            'messages_count'       => $room->messages_count + 1,
        ]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Log::warning('Share broadcast failed: ' . $e->getMessage());
        }

        $this->dispatch('toast', type: 'success', message: __('Shared to :room', ['room' => $room->name ?? __('chat')]));
        $this->close();
    }

    public function render()
    {
        return view('livewire.yard.share-to-chat');
    }
}
