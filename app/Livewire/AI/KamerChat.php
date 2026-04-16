<?php

namespace App\Livewire\AI;

use App\Services\AIService;
use Livewire\Component;

class KamerChat extends Component
{
    public array $messages = [];
    public string $input = '';
    public bool $isOpen = false;
    public bool $isLoading = false;

    public function mount()
    {
        // Restore session history
        $this->messages = session('kamer_chat_history', []);

        // If first open and empty, add Kamer welcome
        if (empty($this->messages)) {
            $user = auth()->user();
            $name = $user?->name ?? 'friend';
            $lang = $user?->language_pref ?? app()->getLocale();
            $langLabel = $lang === 'fr' ? 'French' : 'English';

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $lang === 'fr'
                    ? "Salut {$name} ! 👋 Je suis Kamer, ton guide sur Cameroon Community. Comment puis-je t'aider aujourd'hui ?"
                    : "Hi {$name}! 👋 I'm Kamer, your guide on Cameroon Community. What would you like to explore today?",
            ];
        }
    }

    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
    }

    public function open()
    {
        $this->isOpen = true;
    }

    public function close()
    {
        $this->isOpen = false;
    }

    public function send()
    {
        $input = trim($this->input);
        if ($input === '' || $this->isLoading) {
            return;
        }

        $this->input = '';
        $this->messages[] = ['role' => 'user', 'content' => $input];
        $this->isLoading = true;

        $aiService = app(AIService::class);
        $user = auth()->user();
        $language = $user?->language_pref === 'fr' ? 'French' : 'English';

        $response = $aiService->chat($this->messages, $language);

        if ($response) {
            $this->messages[] = ['role' => 'assistant', 'content' => $response];
        } else {
            $failMsg = ($user?->language_pref === 'fr')
                ? 'Désolé, je ne suis pas disponible pour le moment. Réessayez plus tard.'
                : 'Sorry, I\'m not available right now. Please try again later.';
            $this->messages[] = ['role' => 'assistant', 'content' => $failMsg];
        }

        $this->isLoading = false;

        // Persist conversation to session (keep last 50 messages)
        session(['kamer_chat_history' => array_slice($this->messages, -50)]);
    }

    public function clearHistory()
    {
        $this->messages = [];
        session()->forget('kamer_chat_history');

        // Re-add welcome
        $user = auth()->user();
        $lang = $user?->language_pref ?? 'en';
        $name = $user?->name ?? 'friend';
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $lang === 'fr'
                ? "Conversation réinitialisée ! Comment puis-je t'aider, {$name} ?"
                : "Conversation cleared! How can I help you, {$name}?",
        ];
    }

    public function render()
    {
        return view('livewire.ai.kamer-chat');
    }
}
