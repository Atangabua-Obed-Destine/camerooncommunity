<?php

namespace App\Enums;

enum BadgeType: string
{
    case FoundingMember = 'founding_member';
    case VerifiedResident = 'verified_resident';
    case CommunityLeader = 'community_leader';
    case TopContributor = 'top_contributor';
    case SolidarityHero = 'solidarity_hero';
    case ParcelChampion = 'parcel_champion';
    case EarlyAdopter = 'early_adopter';

    public function label(): string
    {
        return match ($this) {
            self::FoundingMember => 'Founding Member',
            self::VerifiedResident => 'Verified Resident',
            self::CommunityLeader => 'Community Leader',
            self::TopContributor => 'Top Contributor',
            self::SolidarityHero => 'Solidarity Hero',
            self::ParcelChampion => 'Parcel Champion',
            self::EarlyAdopter => 'Early Adopter',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FoundingMember => '⭐',
            self::VerifiedResident => '🏠',
            self::CommunityLeader => '👑',
            self::TopContributor => '🏆',
            self::SolidarityHero => '❤️',
            self::ParcelChampion => '📦',
            self::EarlyAdopter => '🚀',
        };
    }
}
