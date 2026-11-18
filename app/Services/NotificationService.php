<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user via database (and optionally push/email).
     */
    /**
     * $title and $body are either text, stored as given, or [string, replacements],
     * translated here into the recipient's language. Translating at the call site
     * used the sender's language, so a French seller wrote to an English buyer in
     * French, and the other way round.
     */
    public function send(User $user, string $type, string|array $title, string|array $body, array $data = []): void
    {
        $locale = in_array($lang = ($user->language_pref?->value ?? $user->language_pref), ['en', 'fr'], true) ? $lang : 'en';
        [$title, $body] = array_map(
            fn ($text) => is_array($text) ? __($text[0], $text[1] ?? [], $locale) : $text,
            [$title, $body],
        );

        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $user->tenant_id,
            // The notifications table carries an explicit user_id (NOT NULL) in
            // addition to the polymorphic notifiable_* columns.
            'user_id' => $user->id,
            'type' => $type,
            'data' => array_merge($data, [
                'title' => $title,
                'body' => $body,
            ]),
        ]);
    }

    /**
     * Send a notification to multiple users.
     */
    public function sendToMany(iterable $users, string $type, string $title, string $body, array $data = []): void
    {
        foreach ($users as $user) {
            $this->send($user, $type, $title, $body, $data);
        }
    }
}
