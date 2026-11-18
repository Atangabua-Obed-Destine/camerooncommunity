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
 *
 * Visitors get Kamer too, from the landing page's bubble, as the web's guest
 * layout gives it to them. With no account and no session, a visitor is the
 * random id their phone made up (X-Guest-Id), and their conversation lasts as
 * long as a web session would.
 */
class KamerController extends Controller
{
    private const TTL_DAYS = 30;

    public function __construct(private KamerConversation $kamer) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->answer($this->history($this->key($user), $user->name));
    }

    public function send(Request $request, AIService $ai): JsonResponse
    {
        $user = $request->user();

        return $this->reply($request, $ai, $this->key($user), $user->name, now()->addDays(self::TTL_DAYS));
    }

    /** "Clear chat": the conversation starts again from Kamer's line. */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->forget($this->key($user), $user->name);
    }

    // ── Visitors (the landing page's bubble) ─────────────────────────────

    public function guestShow(Request $request): JsonResponse
    {
        return $this->answer($this->history($this->guestKey($request), null));
    }

    public function guestSend(Request $request, AIService $ai): JsonResponse
    {
        $key = $this->guestKey($request);

        return $this->reply($request, $ai, $key, null, now()->addMinutes((int) config('session.lifetime', 120)));
    }

    public function guestClear(Request $request): JsonResponse
    {
        return $this->forget($this->guestKey($request), null);
    }

    // ── Shared ───────────────────────────────────────────────────────────

    private function reply(Request $request, AIService $ai, string $key, ?string $name, \DateTimeInterface $until): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);
        $text = trim($data['message']);
        if ($text === '') {
            return response()->json(['message' => __('validation.required', ['attribute' => 'message'])], 422);
        }

        $messages = $this->history($key, $name);
        $messages[] = ['role' => 'user', 'content' => $text];
        $reply = $this->kamer->reply($messages, $this->lang());
        $messages[] = $reply;

        Cache::put($key, $this->kamer->trim($messages), $until);

        return response()->json(['reply' => $reply, 'available' => $ai->isAvailable()]);
    }

    private function forget(string $key, ?string $name): JsonResponse
    {
        Cache::forget($key);

        return $this->answer([$this->kamer->cleared($name, $this->lang())]);
    }

    /** The stored conversation, or Kamer's welcome when there is none yet. */
    private function history(string $key, ?string $name): array
    {
        $stored = Cache::get($key);

        return is_array($stored) && $stored !== []
            ? $stored
            : [$this->kamer->welcome($name, $this->lang())];
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

    /**
     * A visitor's conversation, by the id their phone made: 32 hex characters,
     * so it cannot be guessed, nor be anything but a cache key.
     */
    private function guestKey(Request $request): string
    {
        $id = (string) $request->header('X-Guest-Id', '');
        // Only a broken client gets this, so it is not translated.
        abort_unless(preg_match('/^[a-f0-9]{32}$/', $id) === 1, 422, 'Missing or invalid guest id.');

        return "kamer:guest:{$id}";
    }

    private function lang(): string
    {
        return app()->getLocale() === 'fr' ? 'fr' : 'en';
    }
}
