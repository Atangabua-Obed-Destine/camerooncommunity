<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case Sold = 'sold';
    case Expired = 'expired';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Sold => 'Sold',
            self::Expired => 'Expired',
            self::Removed => 'Removed',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingReview => 'En revue',
            self::Active => 'Actif',
            self::Paused => 'En pause',
            self::Sold => 'Vendu',
            self::Expired => 'Expiré',
            self::Removed => 'Retiré',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Sold => 'blue',
            self::PendingReview => 'amber',
            self::Paused => 'slate',
            self::Expired => 'orange',
            self::Removed, self::Draft => 'gray',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Active;
    }
}
