<?php

namespace App\Enums;

enum ListingVisibility: string
{
    case Public = 'public';
    case Connections = 'connections';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public (everyone)',
            self::Connections => 'Connections only',
            self::Group => 'Group / Community',
        };
    }

    public function labelFr(): string
    {
        return match ($this) {
            self::Public => 'Public (tout le monde)',
            self::Connections => 'Mes connexions',
            self::Group => 'Groupe / Communauté',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => '🌍',
            self::Connections => '👥',
            self::Group => '🏘️',
        };
    }
}
