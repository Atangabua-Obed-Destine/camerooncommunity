<?php

namespace App\Livewire\Onboarding;

use App\Enums\RoomType;
use App\Models\User;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use App\Services\AIService;
use App\Services\LocationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OnboardingFlow extends Component
{
    // Step tracking: 1=AI Chat, 2=Community Discovery, 3=Profile Polish, 4=Launch
    public int $step = 1;

    // AI Chat state
    public array $chatMessages = [];
    public string $chatInput = '';
    public bool $chatLoading = false;
    public ?string $userIntent = null;

    // Room discovery state
    public array $selectedRoomIds = [];
    public array $defaultRoomIds = [];

    // Profile polish
    public string $bio = '';

    public function mount(): void
    {
        $user = auth()->user();

        // Already onboarded? Go to yard
        if ($user->onboarded_at) {
            $this->redirect(route('yard'), navigate: true);
            return;
        }

        // Ensure rooms exist for user's location
        if ($user->current_country) {
            app(LocationService::class)->handleUserLocation(
                $user,
                $user->current_country,
                '',
                $user->current_region ?? ''
            );

            // Pre-select the National + Regional rooms for the user's country
            // so the "default room" is checked by default on the Discover step.
            // The user can still uncheck them, but most people just want to
            // tap "Join" and get straight into their country's main room.
            $this->selectedRoomIds = YardRoom::where('is_system_room', true)
                ->where('is_active', true)
                ->where('country', $user->current_country)
                ->whereIn('room_type', [RoomType::National, RoomType::Regional])
                ->when($user->current_region, function ($q) use ($user) {
                    // Only auto-select the regional room that matches the user's region
                    $q->where(function ($q2) use ($user) {
                        $q2->where('room_type', RoomType::National)
                           ->orWhere('region', $user->current_region);
                    });
                }, function ($q) {
                    // No region known — only auto-select the National room
                    $q->where('room_type', RoomType::National);
                })
                ->pluck('id')
                ->all();

            // Snapshot the auto-selected (default) rooms so the UI can prevent
            // the user from un-selecting them.
            $this->defaultRoomIds = $this->selectedRoomIds;
        }

        // Load Kamer AI welcome from session (set during registration), or create fresh
        $history = session('kamer_chat_history', []);
        if (! empty($history)) {
            $this->chatMessages = $history;
            session()->forget('kamer_chat_history');
        } else {
            $this->chatMessages = [[
                'role' => 'assistant',
                'content' => $this->getWelcomeMessage($user),
            ]];
        }
    }

    #[Computed]
    public function user(): User
    {
        return auth()->user();
    }

    #[Computed]
    public function discoverableRooms()
    {
        $user = auth()->user();

        if (! $user->current_country) {
            return collect();
        }

        $joinedRoomIds = YardRoomMember::where('user_id', $user->id)->pluck('room_id');

        return YardRoom::where('is_system_room', true)
            ->where('is_active', true)
            ->whereNotIn('id', $joinedRoomIds)
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('room_type', RoomType::National)
                       ->where('country', $user->current_country);
                });
                // Regional rooms: match user's region
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('room_type', RoomType::Regional)
                       ->where(function ($q3) use ($user) {
                           if ($user->current_region) {
                               $q3->where('region', $user->current_region);
                           }
                           if ($user->home_region) {
                               $regionName = config('cameroon.regions.' . $user->home_region, $user->home_region);
                               $q3->orWhere('region', $regionName);
                           }
                       });
                });
            })
            ->withCount('members')
            ->orderBy('room_type')
            ->get();
    }

    #[Computed]
    public function memberCount(): int
    {
        $user = auth()->user();

        return User::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('current_country', $user->current_country)
            ->count();
    }

    /**
     * Send a quick-reply chip or typed message to Kamer AI.
     */
    public function sendChat(?string $quickReply = null): void
    {
        $message = $quickReply ?: trim($this->chatInput);
        if ($message === '') {
            return;
        }

        $this->chatInput = '';
        $this->chatMessages[] = ['role' => 'user', 'content' => $message];
        $this->chatLoading = true;

        // Detect user intent from quick replies
        $this->detectIntent($message);

        // Get AI response
        $ai = app(AIService::class);
        $response = $ai->chat($this->chatMessages, auth()->user()->language_pref?->value ?? 'en');

        $this->chatLoading = false;

        if ($response) {
            $this->chatMessages[] = ['role' => 'assistant', 'content' => $response];
        } else {
            // Fallback if AI unavailable
            $this->chatMessages[] = [
                'role' => 'assistant',
                'content' => auth()->user()->language_pref?->value === 'fr'
                    ? "C'est super ! Passons à la découverte de vos communautés. 👇"
                    : "That's great! Let's discover your communities now. 👇",
            ];
        }
    }

    /**
     * Toggle a room selection for join.
     */
    public function toggleRoom(int $roomId): void
    {
        // Default (auto-selected) rooms are mandatory — ignore toggle attempts.
        if (in_array($roomId, $this->defaultRoomIds, true)) {
            return;
        }

        if (in_array($roomId, $this->selectedRoomIds)) {
            $this->selectedRoomIds = array_values(array_diff($this->selectedRoomIds, [$roomId]));
        } else {
            $this->selectedRoomIds[] = $roomId;
        }
    }

    /**
     * Join all selected rooms and move to next step.
     */
    public function joinSelectedRooms(): void
    {
        $user = auth()->user();
        $locationService = app(LocationService::class);

        foreach ($this->selectedRoomIds as $roomId) {
            $room = YardRoom::find($roomId);
            if ($room && $room->is_system_room && $room->is_active) {
                $locationService->joinRoom($user, $room);
            }
        }

        $this->step = 3;
    }

    /**
     * Skip room joining — proceed without joining.
     */
    public function skipRooms(): void
    {
        $this->step = 3;
    }

    /**
     * Save bio and proceed.
     */
    public function saveBio(): void
    {
        $this->validate(['bio' => 'nullable|string|max:500']);

        if ($this->bio) {
            auth()->user()->update(['bio' => $this->bio]);
        }

        $this->step = 4;
    }

    /**
     * Skip bio.
     */
    public function skipBio(): void
    {
        $this->step = 4;
    }

    /**
     * Complete onboarding and enter The Yard.
     */
    public function completeOnboarding(): void
    {
        auth()->user()->update(['onboarded_at' => now()]);

        $this->redirect(route('yard'), navigate: true);
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 4 && $step <= $this->step) {
            $this->step = $step;
        }
    }

    public function nextStep(): void
    {
        $this->step = min($this->step + 1, 4);
    }

    public function render()
    {
        return view('livewire.onboarding.onboarding-flow')
            ->layout('components.layouts.app');
    }

    /**
     * Generate a warm welcome message.
     */
    private function getWelcomeMessage(User $user): string
    {
        $name = $user->name;
        $country = $user->current_country ?? 'abroad';
        $lang = $user->language_pref?->value ?? 'en';

        if ($lang === 'fr') {
            return "Salut **{$name}** ! 🎉 Je suis **Kamer**, ton guide personnel sur Cameroon Community.\n\nJe suis vraiment content(e) de te voir ici ! Avant de plonger dans le Yard, dis-moi un peu — qu'est-ce qui t'amène sur la plateforme ?";
        }

        return "Hey **{$name}**! 🎉 I'm **Kamer**, your personal guide to Cameroon Community.\n\nSo glad you're here! Before we dive into The Yard, tell me — what brings you to the platform?";
    }

    /**
     * Detect user intent from messages to personalize experience.
     */
    private function detectIntent(string $message): void
    {
        $lower = mb_strtolower($message);
        if (str_contains($lower, 'connect') || str_contains($lower, 'chat') || str_contains($lower, 'meet') || str_contains($lower, 'friend') || str_contains($lower, 'retrouver') || str_contains($lower, 'discuter')) {
            $this->userIntent = 'connect';
        } elseif (str_contains($lower, 'solidarity') || str_contains($lower, 'support') || str_contains($lower, 'help') || str_contains($lower, 'solidarité') || str_contains($lower, 'aider')) {
            $this->userIntent = 'solidarity';
        } elseif (str_contains($lower, 'event') || str_contains($lower, 'service') || str_contains($lower, 'événement')) {
            $this->userIntent = 'services';
        } else {
            $this->userIntent = $this->userIntent ?? 'explore';
        }
    }
}
