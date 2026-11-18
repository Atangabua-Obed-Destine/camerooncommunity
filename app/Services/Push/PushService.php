<?php

namespace App\Services\Push;

use App\Models\DeviceToken;

use function Illuminate\Support\defer;

/**
 * Sends a push to every phone a member has the app signed in on.
 *
 * Delivery happens after the response has gone (defer): nobody waits on
 * Google to send a message, and no queue worker is needed, which matters
 * because production runs none. A token Firebase reports dead is deleted.
 *
 * The data payload is the app's contract (all strings):
 *   kind             message | mention | connection | stored
 *   room_id          message, mention
 *   user_id          connection: the other member
 *   notification_id  stored: the bell notification to mark read
 *   link             always: the web URL the bell's row would open
 */
class PushService
{
    public function __construct(private FcmClient $fcm) {}

    /**
     * @param  array<string, scalar|null>  $data
     * @param  string  $channel  Android channel: 'messages' or 'activity'
     * @param  string|null  $tag  replaces an earlier push with the same tag (one per chat)
     */
    public function toUser(int $userId, string $title, string $body, array $data, string $channel = 'activity', ?string $tag = null): void
    {
        if (! $this->fcm->enabled()) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->all();
        if ($tokens === []) {
            return;
        }

        $message = $this->message($title, $body, $data, $channel, $tag);

        defer(function () use ($tokens, $message) {
            foreach ($tokens as $token) {
                if ($this->fcm->send($token, $message) === 'unregistered') {
                    DeviceToken::where('token', $token)->delete();
                }
            }
        });
    }

    /** @return array<string, mixed> */
    private function message(string $title, string $body, array $data, string $channel, ?string $tag): array
    {
        $notification = ['channel_id' => $channel, 'sound' => 'default'];
        if ($tag !== null) {
            $notification['tag'] = $tag;
        }

        $aps = ['sound' => 'default'];
        if ($tag !== null) {
            $aps['thread-id'] = $tag;
        }

        return [
            'notification' => ['title' => $title, 'body' => $body],
            // FCM data values must be strings.
            'data' => array_map(fn ($v) => (string) $v, array_filter($data, fn ($v) => $v !== null && $v !== '')),
            'android' => ['priority' => 'high', 'notification' => $notification],
            'apns' => ['payload' => ['aps' => $aps]],
        ];
    }
}
