<?php

namespace App\Http\Controllers\Api\V1\Yard;

use App\Http\Controllers\Controller;
use App\Http\Presenters\YardPresenter;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Services\Yard\YardMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reading and writing messages from the app, through the same service as the
 * web's chat, so every rule (who sees what, who may post, the DM connection
 * gate, mentions, moderation, receipts) is the web's.
 */
class MessageController extends Controller
{
    public function __construct(
        private YardMessageService $messages,
        private YardPresenter $present,
    ) {}

    /** GET /yard/rooms/{room}/messages?before={id}&limit= — oldest first. */
    public function index(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate([
            'before' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $user = $request->user();
        if ($denied = $this->deny($request, $room)) {
            return $denied;
        }

        $limit = (int) ($data['limit'] ?? 30);
        $page = $this->messages->page($user, $room, $data['before'] ?? null, $limit + 1);
        // One extra row tells us whether there is more without a count query.
        $hasMore = $page->count() > $limit;
        if ($hasMore) {
            $page = $page->slice(1)->values();
        }

        return response()->json([
            'messages' => $this->present->messages($page, $user, $room),
            'has_more' => $hasMore,
        ]);
    }

    /** GET /yard/rooms/{room}/messages/{message}: one message, after a realtime event. */
    public function show(Request $request, YardRoom $room, YardMessage $message): JsonResponse
    {
        if ($denied = $this->deny($request, $room)) {
            return $denied;
        }

        $visible = $this->messages->visible($request->user(), $room)
            ->with(['user:id,name,username,avatar', 'parent:id,content,user_id,message_type,is_deleted', 'parent.user:id,name,username', 'poll.options'])
            ->whereKey($message->id)->first();
        if (! $visible) {
            return response()->json(['message' => __('Not found.')], 404);
        }

        return response()->json(['message' => $this->present->messages(collect([$visible]), $request->user(), $room)[0]]);
    }

    /** POST /yard/rooms/{room}/messages {content, reply_to?} */
    public function store(Request $request, YardRoom $room): JsonResponse
    {
        $data = $request->validate([
            'content' => 'required|string|max:4000',
            'reply_to' => 'nullable|integer',
        ]);
        if ($blocked = $this->blocked($request, $room)) {
            return $blocked;
        }

        $message = $this->messages->sendText($request->user(), $room, $data['content'], $data['reply_to'] ?? null);

        return $this->created($request, $room, $message);
    }

    /** POST /yard/rooms/{room}/media (multipart: file, type=image|document|audio, caption?, reply_to?, duration? in seconds) */
    public function media(Request $request, YardRoom $room): JsonResponse
    {
        $type = $request->validate(['type' => 'required|in:image,document,audio'])['type'];
        $data = $request->validate([
            'file' => YardMessageService::MEDIA_RULES[$type],
            'caption' => 'nullable|string|max:4000',
            'reply_to' => 'nullable|integer',
            'duration' => 'nullable|numeric|min:0|max:7200',
        ]);
        if ($blocked = $this->blocked($request, $room)) {
            return $blocked;
        }

        $message = $this->messages->sendMedia($request->user(), $room, $data['file'], $type, $data['caption'] ?? null, $data['reply_to'] ?? null, isset($data['duration']) ? (float) $data['duration'] : null);

        return $this->created($request, $room, $message);
    }

    /** PATCH /yard/messages/{message} {content}: your own text messages. */
    public function update(Request $request, YardMessage $message): JsonResponse
    {
        $data = $request->validate(['content' => 'required|string|max:4000']);

        if (! $this->messages->edit($request->user(), $message, $data['content'])) {
            return response()->json(['message' => __('You can only edit your own messages.')], 403);
        }

        return $this->created($request, $message->room, $message->fresh(), 200);
    }

    /** DELETE /yard/messages/{message}: for everyone; your own only. */
    public function destroy(Request $request, YardMessage $message): JsonResponse
    {
        if (! $this->messages->delete($request->user(), $message)) {
            return response()->json(['message' => __('You can only delete your own messages.')], 403);
        }

        return response()->json(['deleted' => true, 'id' => $message->id]);
    }

    /** POST /yard/messages/{message}/reactions {emoji}: toggles. */
    public function react(Request $request, YardMessage $message): JsonResponse
    {
        $emoji = $request->validate(['emoji' => 'required|string|max:16'])['emoji'];

        $counts = $this->messages->toggleReaction($request->user(), $message, $emoji);
        if ($counts === null) {
            return response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403);
        }

        return $this->created($request, $message->room, $message->fresh(), 200);
    }

    /** POST /yard/messages/{message}/delivered: it reached this phone. */
    public function delivered(Request $request, YardMessage $message): JsonResponse
    {
        $this->messages->markDelivered($request->user(), $message);

        return response()->json(['ok' => true]);
    }

    private function deny(Request $request, YardRoom $room): ?JsonResponse
    {
        return $this->messages->membership($request->user(), $room)
            ? null
            : response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403);
    }

    private function blocked(Request $request, YardRoom $room): ?JsonResponse
    {
        return match ($this->messages->cannotSend($request->user(), $room)) {
            null => null,
            'not_connected' => response()->json([
                'message' => __('You must connect with this user before you can send a message.'),
                'code' => 'not_connected',
            ], 403),
            default => response()->json(['message' => __('You are not a member of that chat.'), 'code' => 'not_member'], 403),
        };
    }

    private function created(Request $request, YardRoom $room, YardMessage $message, int $status = 201): JsonResponse
    {
        $message->load(['user:id,name,username,avatar', 'parent:id,content,user_id,message_type,is_deleted', 'parent.user:id,name,username', 'poll.options']);

        return response()->json(['message' => $this->present->messages(collect([$message]), $request->user(), $room)[0]], $status);
    }
}
