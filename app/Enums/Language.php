<?php

namespace App\Enums;

enum Language: string
{
    case English = 'en';
    case French = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'Français',
        };
    }
}
