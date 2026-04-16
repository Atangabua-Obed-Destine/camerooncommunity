<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected const KAMER_SYSTEM_PROMPT = 'You are Kamer, the intelligent assistant for Cameroon Community — the digital home for Cameroonians living abroad. You know the Cameroonian diaspora experience inside out. You help users navigate life in a foreign country, use the platform, find community resources, understand their rights, and feel less alone. You speak both English and French fluently. You understand Camfranglais and will never correct someone for using it. You are warm, practically helpful, culturally aware, and never condescending. You never discuss divisive politics. You prioritise user safety and wellbeing. When you don\'t know something, you say so honestly and suggest where to find out.';

    /**
     * Check if AI features are available.
     */
    public function isAvailable(): bool
    {
        return !empty(config('services.openai.key'))
            && PlatformSetting::getValue('openai_enabled', 'true') === 'true';
    }

    /**
     * Send a prompt to OpenAI and return the response text.
     */
    public function ask(string $systemPrompt, string $userMessage, ?string $model = null, int $maxTokens = 500): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $model = $model ?? PlatformSetting::getValue('openai_model', 'gpt-4o-mini');

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(15)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::warning('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('OpenAI API unavailable', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Multi-turn conversation with Kamer AI.
     */
    public function chat(array $messages, ?string $language = null): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $systemPrompt = PlatformSetting::getValue('ai_system_prompt', self::KAMER_SYSTEM_PROMPT);
        if ($language) {
            $systemPrompt .= "\n\nIMPORTANT: Respond in {$language}.";
        }

        $model = PlatformSetting::getValue('openai_model', 'gpt-4o-mini');
        $apiMessages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($messages as $msg) {
            $apiMessages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $apiMessages,
                    'max_tokens' => 800,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Kamer AI chat failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Moderate a message and return scoring.
     */
    public function moderateText(string $text): array
    {
        $result = $this->ask(
            'You are a content moderator for Cameroon Community, a diaspora platform. Analyse the following message and return ONLY valid JSON with no extra text: {"flagged": true/false, "score": 0-100, "categories": {"hate_speech": 0.0, "harassment": 0.0, "spam": 0.0, "scam": 0.0, "sexual": 0.0, "violence": 0.0}, "reason": "string or null"}. Flag content that is hateful, harassing, spam, scams, or explicit. Be culturally aware — Cameroonian pidgin, Camfranglais, French, and English are all normal and should NOT be flagged. Score 0 = completely safe, 100 = extremely harmful.',
            $text,
            null,
            300,
        );

        if (! $result) {
            return ['flagged' => false, 'score' => 0, 'categories' => [], 'reason' => null];
        }

        $cleaned = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $decoded = json_decode($cleaned, true);

        return is_array($decoded) ? $decoded : ['flagged' => false, 'score' => 0, 'categories' => [], 'reason' => null];
    }

    /**
     * Translate a message to the target language. Cached to avoid repeat API calls.
     */
    public function translate(string $text, string $targetLanguage): ?string
    {
        $cacheKey = 'ai_translate_' . md5($text . $targetLanguage);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $targetLanguage) {
            $langName = $targetLanguage === 'fr' ? 'French' : 'English';

            return $this->ask(
                "You are a translator for a Cameroonian diaspora platform. Translate the following message to {$langName}. If it contains Camfranglais or pidgin, translate the meaning naturally. Return ONLY the translation, no explanation.",
                $text,
                null,
                400,
            );
        });
    }

    /**
     * Assess a solidarity campaign for fraud risk.
     */
    public function assessCampaignRisk(string $title, string $description, string $category, ?string $beneficiary = null, ?float $goalAmount = null): array
    {
        $prompt = "Analyse this Solidarity campaign from Cameroon Community (diaspora fundraising platform) for fraud risk.\n\n"
            . "Title: {$title}\n"
            . "Category: {$category}\n"
            . ($beneficiary ? "Beneficiary: {$beneficiary}\n" : '')
            . ($goalAmount ? "Goal Amount: {$goalAmount}\n" : '')
            . "Description: {$description}\n\n"
            . "Return ONLY valid JSON: {\"risk_score\": \"low\"|\"medium\"|\"high\", \"risk_percentage\": 0-100, \"reason\": \"brief explanation\"}";

        $result = $this->ask(
            'You are a fraud detection analyst for Cameroon Community, a Cameroonian diaspora crowdfunding platform. You assess Solidarity campaigns for authenticity. Be fair — most campaigns are genuine. Flag vague descriptions, impossible claims, missing context, or known scam patterns. Cultural context matters: bereavement and repatriation campaigns are very common and usually genuine in Cameroonian communities.',
            $prompt,
            null,
            300,
        );

        if (! $result) {
            return ['risk_score' => 'low', 'risk_percentage' => 0, 'reason' => 'AI assessment unavailable'];
        }

        $cleaned = preg_replace('/```json\s*|\s*```/', '', trim($result));
        $decoded = json_decode($cleaned, true);

        return is_array($decoded) ? $decoded : ['risk_score' => 'low', 'risk_percentage' => 0, 'reason' => 'Could not parse AI response'];
    }

    /**
     * Help draft a solidarity campaign description.
     */
    public function draftCampaignDescription(string $situation, string $language = 'en'): ?string
    {
        $langName = $language === 'fr' ? 'French' : 'English';

        return $this->ask(
            "You are Kamer, helping a user write a Solidarity campaign description on Cameroon Community. Write a warm, respectful, clear campaign description in {$langName} based on the user's brief input. The description should explain the situation, why the community should help, and how funds will be used. Keep it under 200 words. Do not add fictional details — only expand on what the user provides.",
            $situation,
            null,
            400,
        );
    }

    /**
     * Suggest a condolence/support message for a contribution.
     */
    public function suggestContributionMessage(string $campaignTitle, string $category, string $language = 'en'): ?string
    {
        $langName = $language === 'fr' ? 'French' : 'English';

        return $this->ask(
            "You are Kamer. Generate a short, warm, culturally appropriate message in {$langName} for someone contributing to a Cameroonian community Solidarity campaign. Category: {$category}. Keep it to 1-2 sentences. Be heartfelt, not generic.",
            "Campaign: {$campaignTitle}",
            null,
            150,
        );
    }

    /**
     * Generate a dashboard insight for admins.
     */
    public function generateDashboardInsight(array $stats): ?string
    {
        $statsJson = json_encode($stats);

        return $this->ask(
            'You are Kamer, the AI assistant for Cameroon Community admins. Generate a brief, actionable daily insight (2-3 sentences max) based on the platform statistics provided. Focus on trends, notable changes, and actionable suggestions. Be specific with numbers.',
            "Platform stats: {$statsJson}",
            null,
            200,
        );
    }

    /**
     * Summarise a long thread of messages.
     */
    public function summariseThread(array $messages, string $language = 'en'): ?string
    {
        $langName = $language === 'fr' ? 'French' : 'English';
        $text = collect($messages)->map(fn ($m) => ($m['user'] ?? 'User') . ': ' . ($m['content'] ?? ''))->implode("\n");

        return $this->ask(
            "You are Kamer. Summarise this Cameroon Community chat thread in {$langName} in 3-4 sentences. Capture the main topics and conclusions. Be concise.",
            $text,
            null,
            300,
        );
    }

    /**
     * Kamer's personalised welcome for a new user.
     */
    public function welcomeMessage(string $name, string $country, string $language = 'en'): ?string
    {
        $langName = $language === 'fr' ? 'French' : 'English';

        return $this->ask(
            self::KAMER_SYSTEM_PROMPT . "\n\nRespond in {$langName}.",
            "A new user named {$name} just joined Cameroon Community from {$country}. Give them a warm, brief welcome (3-4 sentences). Mention what they can do on the platform: join The Yard to chat with fellow Cameroonians, support community members through Solidarity, and explore their city room. Be enthusiastic but not overwhelming.",
            null,
            200,
        );
    }

    /**
     * Generate a fun, culturally-aware greeting when a user is about to join a room.
     */
    public function roomJoinGreeting(string $userName, string $roomName, string $roomType, ?string $country, ?string $city, int $memberCount, string $language = 'en'): ?string
    {
        $langName = $language === 'fr' ? 'French' : 'English';
        $location = $city ? "{$city}, {$country}" : ($country ?? 'abroad');

        return $this->ask(
            self::KAMER_SYSTEM_PROMPT . "\n\nRespond in {$langName}. Be a hype-man/doorman welcoming someone into a room. Keep it to 2-3 sentences MAX. Use one emoji. Be warm and culturally Cameroonian.",
            "User '{$userName}' is about to join the '{$roomName}' room ({$roomType} room). There are {$memberCount} members already inside. The room is for Cameroonians in {$location}. Give them a short, exciting welcome that makes them want to click 'Enter'. Don't say 'welcome to Cameroon Community' — just hype up this specific room.",
            null,
            120,
        );
    }
}
