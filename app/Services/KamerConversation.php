<?php

namespace App\Services;

/**
 * A conversation with Kamer AI: what it says first, what it says when it
 * cannot answer, and how much of the conversation is kept. The web's panel
 * (Livewire AI\KamerChat, history in the session) and the app's
 * (Api\V1\KamerController, history in the cache per member) share this, so
 * Kamer reads the same in both.
 */
class KamerConversation
{
    /** Turns kept, the welcome included. */
    public const KEEP = 50;

    public function __construct(private AIService $ai) {}

    public function welcome(?string $name, string $lang): array
    {
        $name = $name ?: 'friend';

        return ['role' => 'assistant', 'content' => $lang === 'fr'
            ? "Salut {$name} ! 👋 Je suis Kamer, ton guide sur Cameroon Network. Comment puis-je t'aider aujourd'hui ?"
            : "Hi {$name}! 👋 I'm Kamer, your guide on Cameroon Network. What would you like to explore today?"];
    }

    /** What the conversation starts again with after "clear". */
    public function cleared(?string $name, string $lang): array
    {
        $name = $name ?: 'friend';

        return ['role' => 'assistant', 'content' => $lang === 'fr'
            ? "Conversation réinitialisée ! Comment puis-je t'aider, {$name} ?"
            : "Conversation cleared! How can I help you, {$name}?"];
    }

    /**
     * Kamer's answer to a conversation ending in the member's message. When
     * the AI cannot answer (no key, no credit, a timeout) the apology goes in
     * its place, as a message like any other.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{role: string, content: string}
     */
    public function reply(array $messages, string $lang): array
    {
        $fr = $lang === 'fr';
        $response = $this->ai->chat($messages, $fr ? 'French' : 'English');

        return ['role' => 'assistant', 'content' => $response ?: ($fr
            ? 'Désolé, je ne suis pas disponible pour le moment. Réessayez plus tard.'
            : "Sorry, I'm not available right now. Please try again later.")];
    }

    /** @param array<int, array{role: string, content: string}> $messages */
    public function trim(array $messages): array
    {
        return array_values(array_slice($messages, -self::KEEP));
    }

    /** The quick questions shown under a fresh conversation. Solidarity isn't live yet, so it isn't suggested. */
    public function suggestions(string $lang): array
    {
        return $lang === 'fr'
            ? ['Que puis-je faire ici ?', 'Comment vendre sur GoMarket ?', 'Aide-moi à trouver des Camerounais près de moi']
            : ['What can I do here?', 'How do I sell on GoMarket?', 'Help me find Cameroonians near me'];
    }
}
