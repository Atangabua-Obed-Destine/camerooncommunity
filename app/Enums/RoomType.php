<?php

namespace App\Enums;

enum RoomType: string
{
    case National = 'national';
    case Regional = 'regional';
    case City = 'city';
    case PrivateGroup = 'private_group';
    case DirectMessage = 'direct_message';

    public function label(): string
    {
        return match ($this) {
            self::National => 'National Room',
            self::Regional => 'Regional Room',
            self::City => 'City Room',
            self::PrivateGroup => 'Private Group',
            self::DirectMessage => 'Direct Message',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::National => '🏳️',
            self::Regional => '🗺️',
            self::City => '📍',
            self::PrivateGroup => '🔒',
            self::DirectMessage => '👤',
        };
    }
}
