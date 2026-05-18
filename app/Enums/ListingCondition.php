<?php

namespace App\Enums;

enum ListingCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Fair = 'fair';
    case ForParts = 'for_parts';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LikeNew => 'Like New',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::ForParts => 'For Parts / Not Working',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::New => 'Neuf',
            self::LikeNew => 'Comme neuf',
            self::Good => 'Bon état',
            self::Fair => 'État correct',
            self::ForParts => 'Pour pièces',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::New => 'green',
            self::LikeNew => 'emerald',
            self::Good => 'cm-green',
            self::Fair => 'amber',
            self::ForParts => 'red',
        };
    }
}
