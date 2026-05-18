<?php

namespace App\Enums;

enum ListingFulfillment: string
{
    case Pickup = 'pickup';
    case LocalDelivery = 'local_delivery';
    case DiasporaShippable = 'diaspora_shippable';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Pickup => 'Pickup only',
            self::LocalDelivery => 'Local delivery',
            self::DiasporaShippable => 'Ships to diaspora',
            self::Digital => 'Digital / Online',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Pickup => 'Retrait sur place',
            self::LocalDelivery => 'Livraison locale',
            self::DiasporaShippable => 'Expédié à la diaspora',
            self::Digital => 'Numérique / En ligne',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pickup => '📍',
            self::LocalDelivery => '🛵',
            self::DiasporaShippable => '✈️',
            self::Digital => '💻',
        };
    }
}
