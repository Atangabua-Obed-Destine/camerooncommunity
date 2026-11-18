<?php

namespace App\Livewire\Onboarding;

use App\Models\User;
use App\Services\Onboarding;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * /welcome. The server side of each step lives in App\Services\Onboarding,
 * which the app's /api/v1/onboarding endpoints call too.
 */
class OnboardingFlow extends Component
{
    // Step tracking: 1=AI Chat, 2=Community Discovery, 3=Profile Polish, 4=Launch
    public int $step = 1;
    // Furthest step the visitor has unlocked — bumped whenever the flow
    // advances. Used so the visitor can swipe / click back to any visited
    // step without being able to skip ahead past unfinished work.
    public int $maxStep = 1;

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

    public function mount(Onboarding $onboarding): void
    {
        $user = auth()->user();

        // Already onboarded? Go to yard
        if ($user->onboarded_at) {
            $this->redirect(route('yard'), navigate: true);
            return;
        }

        // Make sure the national and regional rooms exist, then pre-select
        // them. They are mandatory: the member can't untick them.
        $onboarding->prepare($user);
        $this->selectedRoomIds = $onboarding->communityRooms($user)->pluck('id')->all();
        $this->defaultRoomIds = $this->selectedRoomIds;

        // Load Kamer AI welcome from session (set during registration), or create fresh
        $history = session('kamer_chat_history', []);
        if (! empty($history)) {
            $this->chatMessages = $history;
            session()->forget('kamer_chat_history');
        } else {
            $this->chatMessages = [[
                'role' => 'assistant',
                'content' => $onboarding->welcomeMessage($user),
            ]];
        }
    }

    #[Computed]
    public function user(): User
    {
        return auth()->user();
    }

    /** The national and regional rooms, joined automatically. */
    #[Computed]
    public function discoverableRooms()
    {
        return app(Onboarding::class)->communityRooms(auth()->user());
    }

    #[Computed]
    public function memberCount(): int
    {
        return app(Onboarding::class)->memberCount(auth()->user());
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

        // Detect user intent from quick replies
        $this->detectIntent($message);

        $this->chatMessages[] = [
            'role' => 'assistant',
            'content' => app(Onboarding::class)->kamerReply(auth()->user(), $this->chatMessages),
        ];
        $this->chatLoading = false;
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
        app(Onboarding::class)->joinRooms(auth()->user(), $this->selectedRoomIds);

        $this->step = 3;
        $this->maxStep = max($this->maxStep, 3);
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
        $this->maxStep = 4;
    }

    /**
     * Skip bio.
     */
    public function skipBio(): void
    {
        $this->step = 4;
        $this->maxStep = 4;
    }

    /**
     * Complete onboarding and enter The Yard.
     */
    public function completeOnboarding(): void
    {
        app(Onboarding::class)->complete(auth()->user());

        $this->redirect(route('yard'), navigate: true);
    }

    public function goToStep(int $step): void
    {
        // Allow jumping to any step the visitor has already unlocked, both
        // backwards (review previous step) and forwards (return to where they
        // left off after a back-swipe). Steps that haven't been reached yet
        // are still gated by the Continue button on the current step.
        if ($step >= 1 && $step <= 4 && $step <= $this->maxStep) {
            $this->step = $step;
        }
    }

    public function nextStep(): void
    {
        $this->step = min($this->step + 1, 4);
        $this->maxStep = max($this->maxStep, $this->step);
    }

    public function render()
    {
        return view('livewire.onboarding.onboarding-flow')
            ->layout('components.layouts.app');
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
