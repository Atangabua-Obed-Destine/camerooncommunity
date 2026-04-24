<?php

namespace App\Services;

/**
 * Centralised naming for system rooms (National / Regional / City).
 *
 * Format:
 *   National  → "{ShortCountry} - Kamer"   e.g. "USA - Kamer", "UK - Kamer"
 *   Regional  → "{Region} - Kamer"          e.g. "Littoral - Kamer", "London - Kamer"
 *   City      → "{City} - Kamer"            e.g. "Manchester - Kamer"
 *
 * "Kamer" is the affectionate diaspora shorthand for Cameroonian.
 *
 * Country shortnames use the popular/colloquial form when one exists;
 * otherwise falls back to the full country name.
 */
class RoomNamingService
{
    /**
     * Map of full country names → popular short label used in room names.
     * Keep this list lean — only add entries where the short form is widely
     * recognised. Anything not listed falls back to the full name.
     */
    public const COUNTRY_SHORT = [
        'United States' => 'USA',
        'United States of America' => 'USA',
        'USA' => 'USA',
        'United Kingdom' => 'UK',
        'Great Britain' => 'UK',
        'United Arab Emirates' => 'UAE',
        'Saudi Arabia' => 'KSA',
        'South Africa' => 'SA',
        'Equatorial Guinea' => 'Eq. Guinea',
        "Côte d'Ivoire" => 'Ivory Coast',
        'Cote d\'Ivoire' => 'Ivory Coast',
        'Republic of Korea' => 'Korea',
        'South Korea' => 'Korea',
        'Democratic Republic of the Congo' => 'DR Congo',
        'Russian Federation' => 'Russia',
        'Netherlands' => 'NL',
        'Czech Republic' => 'Czechia',
        'Hong Kong' => 'HK',
        // Single-word countries already short — no entry needed.
    ];

    /** Build a National room name → "USA - Kamer". */
    public static function national(string $country): string
    {
        return self::shortCountry($country) . ' - Kamer';
    }

    /** Build a Regional room name → "Littoral - Kamer". */
    public static function regional(string $region): string
    {
        return trim($region) . ' - Kamer';
    }

    /** Build a City room name → "Manchester - Kamer". */
    public static function city(string $city): string
    {
        return trim($city) . ' - Kamer';
    }

    /** Description helpers — kept here so wording stays consistent. */
    public static function nationalDescription(string $country): string
    {
        return "The national room for all Cameroonians in {$country}.";
    }

    public static function regionalDescription(string $region, string $country): string
    {
        return "The regional room for Cameroonians in {$region}, {$country}.";
    }

    public static function cityDescription(string $city, string $country): string
    {
        return "The city room for Cameroonians in {$city}, {$country}.";
    }

    /** Resolve short label for a country, falling back to the full name. */
    public static function shortCountry(string $country): string
    {
        $country = trim($country);
        return self::COUNTRY_SHORT[$country] ?? $country;
    }
}
