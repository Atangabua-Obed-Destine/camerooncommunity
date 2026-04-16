<?php

namespace App\Enums\Solidarity;

enum RiskScore: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'yellow',
            self::High => 'red',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => '🟢',
            self::Medium => '🟡',
            self::High => '🔴',
        };
    }
}
