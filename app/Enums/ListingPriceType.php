<?php

namespace App\Enums;

enum ListingPriceType: string
{
    case Fixed = 'fixed';
    case Negotiable = 'negotiable';
    case Free = 'free';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price',
            self::Negotiable => 'Negotiable',
            self::Free => 'Free',
            self::Contact => 'Contact for price',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Fixed => 'Prix fixe',
            self::Negotiable => 'Négociable',
            self::Free => 'Gratuit',
            self::Contact => 'Prix sur demande',
        };
    }
}
