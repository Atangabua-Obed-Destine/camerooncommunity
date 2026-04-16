<?php

namespace App\Livewire\Yard;

use App\Enums\MessageType;
use App\Events\CallSignal;
use App\Events\CallStarted;
use App\Events\CallUpdated;
use App\Events\MessageSent;
use App\Models\YardCall;
use App\Models\YardCallParticipant;
use App\Models\YardMessage;
use App\Models\YardRoom;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class CallManager extends Component
{
    public ?int $activeCallId = null;
    public ?string $activeCallUuid = null;
    public ?string $callType = null;
    public ?int $callRoomId = null;
    public ?string $callRoomName = null;
    public ?string $callerName = null;
    public string $callStatus = 'idle'; // idle, ringing, outgoing, active, ended

    // Media toggle state
    public bool $isMuted = false;
    public bool $isVideoOff = false;

    // Participants for display
    public array $participants = [];

    protected $listeners = [
        'initiateCall',
        'answerCall',
        'declineCall',
        'endCall',
        'sendSignal',
        'toggleMute',
        'toggleVideo',
    ];

    public function initiateCall(int $roomId, string $type): void
    {
        $user = Auth::user();
        $room = YardRoom::findOrFail($roomId);

        // Check no active call in this room
        $existing = YardCall::where('room_id', $roomId)
            ->whereIn('status', ['ringing', 'active'])
            ->first();

        if ($existing) {
            $this->dispatch('call-error', message: 'A call is already in progress in this room.');
            return;
        }

        $call = YardCall::create([
            'room_id' => $roomId,
            'initiated_by' => $user->id,
            'call_type' => $type,
            'status' => 'ringing',
        ]);

        // Add initiator as participant
        YardCallParticipant::create([
            'call_id' => $call->id,
            'user_id' => $user->id,
            'status' => 'joined',
            'joined_at' => now(),
        ]);

        // Add other room members as ringing
        $memberIds = $room->members()
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id');

        foreach ($memberIds as $memberId) {
            YardCallParticipant::create([
                'call_id' => $call->id,
                'user_id' => $memberId,
                'status' => 'ringing',
            ]);
        }

        $this->activeCallId = $call->id;
        $this->activeCallUuid = $call->uuid;
        $this->callType = $type;
        $this->callRoomId = $roomId;
        $this->callRoomName = $room->name;
        $this->callerName = $user->username ?? $user->name;
        $this->callStatus = 'outgoing';
        $this->refreshParticipants();

        // Broadcast to room
        $this->broadcastToOthers(new CallStarted($call, $user->username ?? $user->name));

        // Get the other participant info for the call UI
        $otherUser = $room->members()
            ->where('user_id', '!=', $user->id)
            ->with('user:id,name,username,avatar,last_active_at')
            ->first()?->user;

        $this->dispatch('call-started', [
            'callUuid' => $call->uuid,
            'callId' => $call->id,
            'callType' => $type,
            'roomId' => $roomId,
            'calleeName' => $otherUser?->username ?? $otherUser?->name ?? $room->name,
            'calleeAvatar' => $otherUser?->avatar ?? null,
            'calleeOnline' => $otherUser?->last_active_at && $otherUser->last_active_at->gt(now()->subMinutes(5)),
            'isInitiator' => true,
        ]);

        // Auto-end if nobody answers in 45 seconds
        // (handled client-side with JS timeout)
    }

    public function answerCall(string $callUuid): void
    {
        $user = Auth::user();
        $call = YardCall::where('uuid', $callUuid)->firstOrFail();

        if (! $call->isActive()) {
            $this->dispatch('call-error', message: 'This call has already ended.');
            return;
        }

        $participant = YardCallParticipant::where('call_id', $call->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->update([
                'status' => 'joined',
                'joined_at' => now(),
            ]);
        } else {
            YardCallParticipant::create([
                'call_id' => $call->id,
                'user_id' => $user->id,
                'status' => 'joined',
                'joined_at' => now(),
            ]);
        }

        // Start the call if still ringing
        if ($call->status === 'ringing') {
            $call->update([
                'status' => 'active',
                'started_at' => now(),
            ]);
        }

        $this->activeCallId = $call->id;
        $this->activeCallUuid = $call->uuid;
        $this->callType = $call->call_type;
        $this->callRoomId = $call->room_id;
        $this->callRoomName = $call->room->name;
        $this->callerName = $call->initiator->username ?? $call->initiator->name;
        $this->callStatus = 'active';
        $this->refreshParticipants();

        $this->broadcastToOthers(new CallUpdated(
            $call->tenant_id,
            $call->room_id,
            $call->uuid,
            'active',
            $user->id,
            $user->username ?? $user->name,
            'joined',
        ));

        $this->dispatch('call-answered', [
            'callUuid' => $call->uuid,
            'callId' => $call->id,
            'callType' => $call->call_type,
            'roomId' => $call->room_id,
            'isInitiator' => false,
        ]);
    }

    public function declineCall(string $callUuid): void
    {
        $user = Auth::user();
        $call = YardCall::where('uuid', $callUuid)->first();

        if (! $call) {
            $this->resetCallState();
            return;
        }

        $participant = YardCallParticipant::where('call_id', $call->id)
            ->where('user_id', $user->id)
            ->first();

        if ($participant) {
            $participant->update(['status' => 'declined']);
        }

        // If all participants declined/missed, end the call
        $stillRinging = $call->participants()
            ->where('user_id', '!=', $call->initiated_by)
            ->whereIn('status', ['ringing', 'joined'])
            ->count();

        if ($stillRinging === 0) {
            $call->update([
                'status' => 'declined',
                'ended_at' => now(),
            ]);

            // Log declined call in chat
            $this->createCallLogMessage($call, 'declined');
        }

        $this->broadcastToOthers(new CallUpdated(
            $call->tenant_id,
            $call->room_id,
            $call->uuid,
            'declined',
            $user->id,
            $user->username ?? $user->name,
            'declined',
        ));

        $this->resetCallState();
    }

    public function endCall(?string $callUuid = null): void
    {
        $uuid = $callUuid ?? $this->activeCallUuid;
        if (! $uuid) {
            $this->resetCallState();
            return;
        }

        $call = YardCall::where('uuid', $uuid)->first();
        if ($call && $call->isActive()) {
            $wasAnswered = $call->status === 'active';
            $call->end();

            // Log call in chat
            if ($wasAnswered) {
                $this->createCallLogMessage($call, 'ended');
            } else {
                // Nobody answered — missed call
                $this->createCallLogMessage($call, 'missed');
            }

            $this->broadcastToOthers(new CallUpdated(
                $call->tenant_id,
                $call->room_id,
                $call->uuid,
                'ended',
                Auth::id(),
                Auth::user()->username ?? Auth::user()->name,
                'ended',
            ));
        }

        $this->resetCallState();
        $this->dispatch('call-ended');
    }

    public function sendSignal(string $callUuid, int $toUserId, string $signalType, array $signalData): void
    {
        $user = Auth::user();
        $call = YardCall::where('uuid', $callUuid)->first();

        if (! $call || ! $call->isActive()) {
            return;
        }

        $this->broadcastToOthers(new CallSignal(
            $call->tenant_id,
            $call->room_id,
            $callUuid,
            $user->id,
            $toUserId,
            $signalType,
            $signalData,
        ));
    }

    public function toggleMute(): void
    {
        $this->isMuted = ! $this->isMuted;

        if ($this->activeCallId) {
            YardCallParticipant::where('call_id', $this->activeCallId)
                ->where('user_id', Auth::id())
                ->update(['is_muted' => $this->isMuted]);
        }
    }

    public function toggleVideo(): void
    {
        $this->isVideoOff = ! $this->isVideoOff;

        if ($this->activeCallId) {
            YardCallParticipant::where('call_id', $this->activeCallId)
                ->where('user_id', Auth::id())
                ->update(['is_video_off' => $this->isVideoOff]);
        }
    }

    public function refreshParticipants(): void
    {
        if (! $this->activeCallId) {
            $this->participants = [];
            return;
        }

        $this->participants = YardCallParticipant::where('call_id', $this->activeCallId)
            ->with('user:id,name,username,avatar,last_active_at')
            ->get()
            ->map(fn ($p) => [
                'user_id' => $p->user_id,
                'name' => $p->user->username ?? $p->user->name ?? 'Unknown',
                'initial' => strtoupper(substr($p->user->username ?? $p->user->name ?? 'U', 0, 1)),
                'avatar' => $p->user->avatar ?? null,
                'is_online' => $p->user->last_active_at && $p->user->last_active_at->gt(now()->subMinutes(5)),
                'status' => $p->status,
                'is_muted' => $p->is_muted,
                'is_video_off' => $p->is_video_off,
            ])
            ->toArray();
    }

    /**
     * Broadcast an event, using toOthers() only when a valid socket ID is present.
     */
    protected function broadcastToOthers($event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            // If toOthers() fails (invalid socket ID), broadcast without exclusion
            try {
                broadcast($event);
            } catch (\Throwable $e2) {
                \Log::warning('Broadcast failed: ' . $e2->getMessage());
            }
        }
    }

    protected function resetCallState(): void
    {
        $this->activeCallId = null;
        $this->activeCallUuid = null;
        $this->callType = null;
        $this->callRoomId = null;
        $this->callRoomName = null;
        $this->callerName = null;
        $this->callStatus = 'idle';
        $this->isMuted = false;
        $this->isVideoOff = false;
        $this->participants = [];
    }

    /**
     * Insert a call log message into the chat room.
     */
    protected function createCallLogMessage(YardCall $call, string $outcome): void
    {
        $duration = $call->duration_seconds ?? 0;
        $callData = json_encode([
            'call_type' => $call->call_type,          // voice | video
            'outcome'   => $outcome,                   // ended | missed | declined
            'duration'  => $duration,
            'initiated_by' => $call->initiated_by,
            'call_uuid' => $call->uuid,
        ]);

        $msg = YardMessage::create([
            'tenant_id'    => $call->tenant_id,
            'uuid'         => Str::uuid()->toString(),
            'room_id'      => $call->room_id,
            'user_id'      => $call->initiated_by,
            'message_type'  => MessageType::CallLog,
            'content'      => $callData,
        ]);

        // Build a human-readable preview for the room list
        $icon = $call->call_type === 'video' ? '📹' : '📞';
        $preview = match ($outcome) {
            'ended'    => "{$icon} Call · " . $this->formatDuration($duration),
            'missed'   => "{$icon} Missed call",
            'declined' => "{$icon} Declined call",
            default    => "{$icon} Call",
        };

        $room = YardRoom::find($call->room_id);
        if ($room) {
            $room->update([
                'last_message_at'      => now(),
                'last_message_preview'  => $preview,
                'last_message_user_id'  => $call->initiated_by,
                'messages_count'        => $room->messages_count + 1,
            ]);
        }

        // Broadcast so the chat updates in real-time
        try {
            $this->broadcastToOthers(new MessageSent($msg));
        } catch (\Throwable $e) {
            // Non-critical — the message is already saved
        }
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return view('livewire.yard.call-manager');
    }
}
