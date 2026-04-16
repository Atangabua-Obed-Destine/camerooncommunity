<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a user via database (and optionally push/email).
     */
    public function send(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => $user->tenant_id,
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
