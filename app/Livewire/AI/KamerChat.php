<?php

namespace App\Livewire\AI;

use App\Services\KamerConversation;
use Livewire\Attributes\On;
use Livewire\Component;

class KamerChat extends Component
{
    public array $messages = [];
    public string $input = '';
    public bool $isOpen = false;
    public bool $isLoading = false;

    /**
     * When true, suppresses the floating bubble (the slide-over panel still
     * works via dispatched events such as `open-kamer-ai` from the Yard
     * sidebar). Set by the parent layout in yardMode.
     */
    public bool $hideBubble = false;

    public function mount(bool $hideBubble = false)
    {
        $this->hideBubble = $hideBubble;

        // Restore session history
        $this->messages = session('kamer_chat_history', []);

        // If first open and empty, add Kamer welcome
        if (empty($this->messages)) {
            $this->messages[] = app(KamerConversation::class)->welcome(auth()->user()?->name, $this->lang());
        }
    }

    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
    }

    #[On('open-kamer-ai')]
    public function open()
    {
        $this->isOpen = true;
    }

    public function close()
    {
        $this->isOpen = false;
    }

    /**
     * Step one: show the member's message and the thinking dots straight away.
     * The browser then asks for the reply (step two). Doing both in one request
     * turned isLoading on and off before anything was drawn, so the dots never
     * showed and the member's own message only appeared with the answer.
     */
    public function send()
    {
        $input = trim($this->input);
        if ($input === '' || $this->isLoading) {
            return;
        }

        $this->input = '';
        $this->messages[] = ['role' => 'user', 'content' => $input];
        $this->isLoading = true;
        $this->dispatch('kamer-thinking');
    }

    /** Step two: called by the browser once the dots are on screen. */
    public function reply()
    {
        if (! $this->isLoading || (end($this->messages)['role'] ?? null) !== 'user') {
            $this->isLoading = false;
            return;
        }

        $kamer = app(KamerConversation::class);
        $this->messages[] = $kamer->reply($this->messages, $this->lang());

        $this->isLoading = false;

        // Persist conversation to session (keep last 50 messages)
        session(['kamer_chat_history' => $kamer->trim($this->messages)]);
    }

    /**
     * The language to talk in: the page's own (SetUserLanguage), so it follows
     * the member's choice. language_pref is an enum, and comparing it with 'fr'
     * was always false, so French members got English everywhere here.
     */
    public function lang(): string
    {
        return app()->getLocale() === 'fr' ? 'fr' : 'en';
    }

    /**
     * Quick-suggestion pill handler — pre-fills the input then routes through send().
     */
    public function sendSuggestion(string $text): void
    {
        $this->input = $text;
        $this->send();
    }

    public function clearHistory()
    {
        $this->messages = [];
        session()->forget('kamer_chat_history');

        // Re-add welcome
        $this->messages[] = app(KamerConversation::class)->cleared(auth()->user()?->name, $this->lang());
    }

    public function render()
    {
        return view('livewire.ai.kamer-chat');
    }
}
