<?php

namespace App\Services;

use Illuminate\Support\Facades\App;

class LanguageService
{
    /**
     * Get the current locale.
     */
    public function locale(): string
    {
        return App::getLocale();
    }

    /**
     * Get a bilingual value: returns the value for the current locale.
     */
    public function bilingual(?string $en, ?string $fr): ?string
    {
        return $this->locale() === 'fr' ? ($fr ?? $en) : ($en ?? $fr);
    }
}
