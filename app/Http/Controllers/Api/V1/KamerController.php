<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AIService;
use App\Services\KamerConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Kamer AI in the app: the web's slide-over panel (Livewire AI\KamerChat).
 *
 * The web keeps the conversation in the session; a token has none, so the
 * app's is kept here, per member, for 30 days (the last 50 turns, as on the
 * web). It is kept on the server rather than sent up by the phone, so what
 * Kamer is told it said earlier is what it really said.
 *
 * Replies speak the app's language (X-Localization, through SetUserLanguage).
 * When the AI cannot answer, the apology is the reply, exactly as on the web.
 */
class KamerController extends Controller
{
    private const TTL_DAYS = 30;

    public function __construct(private KamerConversation $kamer) {}

    public function show(Request $request): JsonResponse
    {
        return $this->answer($this->history($request->user()));
    }

    public function send(Request $request, AIService $ai): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);
        $text = trim($data['message']);
        if ($text === '') {
            return response()->json(['message' => __('validation.required', ['attribute' => 'message'])], 422);
        }

        $user = $request->user();
        $messages = $this->history($user);
        $messages[] = ['role' => 'user', 'content' => $text];
        $reply = $this->kamer->reply($messages, $this->lang());
        $messages[] = $reply;

        Cache::put($this->key($user), $this->kamer->trim($messages), now()->addDays(self::TTL_DAYS));

        return response()->json(['reply' => $reply, 'available' => $ai->isAvailable()]);
    }

    /** "Clear chat": the conversation starts again from Kamer's line. */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();
        Cache::forget($this->key($user));

        return $this->answer([$this->kamer->cleared($user->name, $this->lang())]);
    }

    /** The stored conversation, or Kamer's welcome when there is none yet. */
    private function history(User $user): array
    {
        $stored = Cache::get($this->key($user));

        return is_array($stored) && $stored !== []
            ? $stored
            : [$this->kamer->welcome($user->name, $this->lang())];
    }

    private function answer(array $messages): JsonResponse
    {
        return response()->json([
            'messages' => array_values($messages),
            'suggestions' => $this->kamer->suggestions($this->lang()),
        ]);
    }

    private function key(User $user): string
    {
        return "kamer:history:{$user->id}";
    }

    private function lang(): string
    {
        return app()->getLocale() === 'fr' ? 'fr' : 'en';
    }
}
