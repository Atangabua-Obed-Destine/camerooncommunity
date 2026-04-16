<?php

namespace App\Enums\Solidarity;

enum CampaignCategory: string
{
    case Bereavement = 'bereavement';
    case Medical = 'medical';
    case Disaster = 'disaster';
    case Education = 'education';
    case Repatriation = 'repatriation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bereavement => 'Bereavement',
            self::Medical => 'Medical',
            self::Disaster => 'Disaster',
            self::Education => 'Education',
            self::Repatriation => 'Repatriation',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Bereavement => '🕊️',
            self::Medical => '🏥',
            self::Disaster => '🌊',
            self::Education => '🎓',
            self::Repatriation => '✈️',
            self::Other => '🤝',
        };
    }
}
