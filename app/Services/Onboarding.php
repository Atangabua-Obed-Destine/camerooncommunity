<?php

namespace App\Services;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use Illuminate\Support\Collection;

/**
 * The four onboarding steps' server side, shared by the web's /welcome
 * (OnboardingFlow) and the app's /api/v1/onboarding endpoints.
 */
class Onboarding
{
    public function __construct(private LocationService $locations) {}

    /**
     * Make sure the member's national and regional rooms exist. The web does
     * this when /welcome mounts; the app does it when it asks for the rooms.
     */
    public function prepare(User $user): void
    {
        if ($user->current_country) {
            $this->locations->handleUserLocation($user, $user->current_country, '', $user->current_region ?? '');
        }
    }

    /**
     * The rooms step 2 shows: the member's national room and their region's,
     * national first. Both are joined automatically and can't be unticked.
     *
     * @return Collection<int, YardRoom>
     */
    public function communityRooms(User $user): Collection
    {
        if (! $user->current_country) {
            return collect();
        }

        return YardRoom::where('is_system_room', true)
            ->where('is_active', true)
            ->where('country', $user->current_country)
            ->where(function ($q) use ($user) {
                $q->where('room_type', RoomType::National);
                if ($user->current_region) {
                    $q->orWhere(fn ($q2) => $q2->where('room_type', RoomType::Regional)->where('region', $user->current_region));
                }
            })
            ->withCount('members')
            // National first. CASE rather than MySQL's FIELD() so it runs anywhere.
            ->orderByRaw('CASE WHEN room_type = ? THEN 0 ELSE 1 END', [RoomType::National->value])
            ->get();
    }

    /** "N Cameroonians" in the member's country, as step 2's chip says. */
    public function memberCount(User $user): int
    {
        return User::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('current_country', $user->current_country)
            ->whereNull('deleted_at')
            ->whereNull('anonymised_at')
            ->where('is_active', true)
            ->count();
    }

    /** Join the chosen rooms. Only active system rooms can be joined this way. */
    public function joinRooms(User $user, array $roomIds): void
    {
        foreach (YardRoom::whereIn('id', $roomIds)->where('is_system_room', true)->where('is_active', true)->get() as $room) {
            $this->locations->joinRoom($user, $room);
        }
    }

    /** Kamer's opening line when no AI welcome was seeded at registration. */
    public function welcomeMessage(User $user): string
    {
        $name = $user->name;

        if (($user->language_pref?->value ?? 'en') === 'fr') {
            return "Salut **{$name}** ! 🎉 Je suis **Kamer**, ton guide personnel sur Cameroon Network.\n\nJe suis vraiment content(e) de te voir ici ! Avant de plonger dans le Yard, dis-moi un peu, qu'est-ce qui t'amène sur la plateforme ?";
        }

        return "Hey **{$name}**! 🎉 I'm **Kamer**, your personal guide to Cameroon Network.\n\nSo glad you're here! Before we dive into The Yard, tell me, what brings you to the platform?";
    }

    /**
     * Kamer's reply to the onboarding chat, or the fallback line that moves the
     * member on when the AI is unavailable.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function kamerReply(User $user, array $messages): string
    {
        $lang = $user->language_pref?->value ?? 'en';

        try {
            $reply = app(AIService::class)->chat($messages, $lang === 'fr' ? 'French' : 'English');
        } catch (\Throwable $e) {
            logger()->warning('Onboarding Kamer reply failed: '.$e->getMessage());
            $reply = null;
        }

        return $reply ?: ($lang === 'fr'
            ? "C'est super ! Passons à la découverte de vos communautés. 👇"
            : "That's great! Let's discover your communities now. 👇");
    }

    public function complete(User $user): void
    {
        if (! $user->onboarded_at) {
            $user->update(['onboarded_at' => now()]);
        }
    }
}
