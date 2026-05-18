<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case Countered = 'countered';
    case Withdrawn = 'withdrawn';
    case Expired   = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Accepted  => 'Accepted',
            self::Rejected  => 'Rejected',
            self::Countered => 'Countered',
            self::Withdrawn => 'Withdrawn',
            self::Expired   => 'Expired',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Pending   => 'En attente',
            self::Accepted  => 'Acceptée',
            self::Rejected  => 'Refusée',
            self::Countered => 'Contre-offre',
            self::Withdrawn => 'Retirée',
            self::Expired   => 'Expirée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'amber',
            self::Accepted  => 'emerald',
            self::Rejected  => 'rose',
            self::Countered => 'blue',
            self::Withdrawn => 'slate',
            self::Expired   => 'slate',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Countered], true);
    }
}
