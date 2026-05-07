<?php

namespace App\Livewire\Yard;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\YardRoomMember;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class StarredMessages extends Component
{
    public bool $show = false;

    #[On('open-starred')]
    public function open(): void
    {
        $this->show = true;
        unset($this->messages);
    }

    #[On('starred-changed')]
    public function refresh(): void
    {
        unset($this->messages);
    }

    public function close(): void
    {
        $this->show = false;
    }

    /**
     * All starred messages for the current user, across every room they are
     * still a member of, joined with room + sender info. DM rooms get the
     * partner's display name resolved on the PHP side.
     */
    #[Computed]
    public function messages()
    {
        $userId = auth()->id();

        $rows = DB::table('yard_message_stars as s')
            ->join('yard_messages as m', 'm.id', '=', 's.message_id')
            ->join('yard_rooms as r', 'r.id', '=', 'm.room_id')
            ->join('yard_room_members as me', function ($j) use ($userId) {
                $j->on('me.room_id', '=', 'r.id')->where('me.user_id', '=', $userId);
            })
            ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
            ->where('s.user_id', $userId)
            ->where('m.is_deleted', false)
            ->orderByDesc('s.created_at')
            ->limit(200)
            ->get([
                's.message_id',
                's.created_at as starred_at',
                'm.content',
                'm.message_type',
                'm.media_path',
                'm.media_original_name',
                'm.created_at as msg_created_at',
                'r.id as room_id',
                'r.name as room_name',
                'r.avatar as room_avatar',
                'r.room_type as room_type',
                'u.id as sender_id',
                'u.name as sender_name',
                'u.avatar as sender_avatar',
            ]);

        // Resolve DM partner names for any DM rooms we have stars in.
        $dmRoomIds = $rows->where('room_type', RoomType::DirectMessage->value)->pluck('room_id')->unique();
        $partners = collect();
        if ($dmRoomIds->isNotEmpty()) {
            $partners = DB::table('yard_room_members as m')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->whereIn('m.room_id', $dmRoomIds)
                ->where('m.user_id', '!=', $userId)
                ->get(['m.room_id', 'u.name', 'u.avatar'])
                ->keyBy('room_id');
        }

        return $rows->map(function ($row) use ($partners) {
            $isDm = $row->room_type === RoomType::DirectMessage->value;
            $partner = $partners->get($row->room_id);

            $row->display_room_name = $isDm && $partner ? $partner->name : ($row->room_name ?: '—');
            $row->display_room_avatar = $isDm && $partner ? $partner->avatar : $row->room_avatar;
            $row->is_dm = $isDm;

            // Build a short preview line based on message type
            $type = $row->message_type;
            if ($type === MessageType::Image->value) {
                $row->preview = '📷 ' . __('Photo') . ($row->content ? ' · ' . $row->content : '');
            } elseif ($type === MessageType::Video->value) {
                $row->preview = '🎬 ' . __('Video') . ($row->content ? ' · ' . $row->content : '');
            } elseif ($type === MessageType::Audio->value) {
                $row->preview = '🎤 ' . __('Voice message');
            } elseif ($type === MessageType::Document->value) {
                $row->preview = '📄 ' . ($row->media_original_name ?: __('Document'));
            } elseif ($type === MessageType::Poll->value) {
                $row->preview = '📊 ' . __('Poll');
            } else {
                $row->preview = $row->content ?: '';
            }

            return $row;
        });
    }

    /**
     * Open the host room and ask the chat surface to scroll to the message.
     */
    public function jump(int $roomId, int $messageId): void
    {
        // Ignore stars from rooms the user can no longer access.
        $isMember = YardRoomMember::where('room_id', $roomId)
            ->where('user_id', auth()->id())
            ->exists();
        if (!$isMember) {
            $this->dispatch('toast', type: 'error', message: __('You are no longer a member of this chat.'));
            return;
        }

        $this->show = false;
        $this->dispatch('room-selected', roomId: $roomId);
        $this->dispatch('scroll-to-message', messageId: $messageId);
    }

    /**
     * Remove a star from this list (also unstars the underlying message).
     */
    public function unstar(int $messageId): void
    {
        DB::table('yard_message_stars')
            ->where('user_id', auth()->id())
            ->where('message_id', $messageId)
            ->delete();
        unset($this->messages);
    }

    public function render()
    {
        return view('livewire.yard.starred-messages');
    }
}
