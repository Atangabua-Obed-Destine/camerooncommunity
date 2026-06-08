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

    /**
     * Can a non-owner open this listing's detail page? Active listings and
     * Sold ones (so buyers can revisit / review, and shared links resolve to a
     * "Sold" state instead of a 404 — like Facebook Marketplace). Draft,
     * PendingReview, Paused, Expired and Removed stay owner-only.
     */
    public function isViewable(): bool
    {
        return in_array($this, [self::Active, self::Sold], true);
    }
}
