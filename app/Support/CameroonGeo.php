<?php

namespace App\Support;

/**
 * Approximate centroid coordinates for the 10 administrative regions of
 * Cameroon, plus a fuzzy region matcher that maps free-text "region" strings
 * (typed by sellers in any language/spelling) to a canonical region key.
 *
 * Coordinates are good enough for a city-level map view — they are *not*
 * precise locations of listings (we deliberately don't store those for
 * privacy reasons). The map view jitters multiple listings around the
 * centroid so they don't visually stack.
 */
class CameroonGeo
{
    /** Canonical region key => approx lat/lng of the regional capital. */
    public static function centroids(): array
    {
        return [
            'adamaoua'  => ['lat' => 7.3267,  'lng' => 13.5847, 'capital' => 'Ngaoundéré'],
            'centre'    => ['lat' => 3.8480,  'lng' => 11.5021, 'capital' => 'Yaoundé'],
            'east'      => ['lat' => 4.5800,  'lng' => 13.6800, 'capital' => 'Bertoua'],
            'far_north' => ['lat' => 10.5950, 'lng' => 14.3247, 'capital' => 'Maroua'],
            'littoral'  => ['lat' => 4.0511,  'lng' => 9.7679,  'capital' => 'Douala'],
            'north'     => ['lat' => 9.3072,  'lng' => 13.3974, 'capital' => 'Garoua'],
            'northwest' => ['lat' => 5.9631,  'lng' => 10.1591, 'capital' => 'Bamenda'],
            'south'     => ['lat' => 2.9404,  'lng' => 11.1517, 'capital' => 'Ebolowa'],
            'southwest' => ['lat' => 4.1543,  'lng' => 9.2920,  'capital' => 'Buea'],
            'west'      => ['lat' => 5.4737,  'lng' => 10.4178, 'capital' => 'Bafoussam'],
        ];
    }

    /** Best-effort centre of Cameroon (used as the default map view). */
    public static function center(): array
    {
        return ['lat' => 6.5, 'lng' => 12.5, 'zoom' => 6];
    }

    /**
     * Region → free-text aliases (region names EN/FR + major cities + common
     * misspellings). Single source of truth for both fuzzy matching and the
     * radius filter (which LIKE-matches listing.region against these aliases).
     */
    public static function aliases(): array
    {
        return [
            'adamaoua'  => ['adamaoua', 'adamawa', 'ngaoundere', 'ngaoundéré', 'meiganga'],
            'centre'    => ['centre', 'center', 'yaounde', 'yaoundé', 'mbalmayo', 'obala', 'mfou', 'bafia'],
            'east'      => ['east', 'est', 'bertoua', 'batouri', 'abong-mbang', 'abong mbang', 'yokadouma'],
            'far_north' => ['far north', 'far-north', 'farnorth', 'extreme-nord', 'extreme nord', 'extrême-nord', 'extrême nord', 'extremenord', 'maroua', 'kousseri', 'mokolo', 'kaele'],
            'littoral'  => ['littoral', 'douala', 'edea', 'edéa', 'nkongsamba'],
            'north'     => ['north', 'nord', 'garoua', 'guider', 'poli'],
            'northwest' => ['northwest', 'north-west', 'north west', 'nord-ouest', 'nordouest', 'nord ouest', 'bamenda', 'kumbo', 'wum', 'mbouda', 'fundong'],
            'south'     => ['south', 'sud', 'ebolowa', 'kribi', 'sangmelima', 'ambam'],
            'southwest' => ['southwest', 'south-west', 'south west', 'sud-ouest', 'sudouest', 'sud ouest', 'buea', 'limbe', 'kumba', 'tiko', 'mamfe'],
            'west'      => ['west', 'ouest', 'bafoussam', 'dschang', 'foumban', 'bangangte', 'bangangté', 'bafang', 'mbouda'],
        ];
    }

    /**
     * Map a free-text region string to a canonical key. Returns '' if no match.
     * Handles French + English names, common cities, and obvious misspellings.
     */
    public static function matchRegion(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') { return ''; }
        // strip accents the simple way
        $s = strtr($s, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ô'=>'o','î'=>'i','ï'=>'i','ç'=>'c','ù'=>'u','û'=>'u']);

        foreach (self::aliases() as $key => $aliases) {
            foreach ($aliases as $a) {
                if (preg_match('/\b' . preg_quote($a, '/') . '\b/iu', $s)) { return $key; }
            }
        }
        return '';
    }

    /** Resolve a free-text region/city string to its region centroid, or null. */
    public static function centroidForRegion(string $raw): ?array
    {
        $key = self::matchRegion($raw);
        return $key === '' ? null : (self::centroids()[$key] ?? null);
    }

    /** Great-circle distance between two lat/lng points, in kilometres. */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Canonical region keys whose centroid lies within $radiusKm of the given
     * point. Used by the marketplace radius filter.
     *
     * @return string[]
     */
    public static function regionsWithin(float $lat, float $lng, float $radiusKm): array
    {
        $keys = [];
        foreach (self::centroids() as $key => $c) {
            if (self::haversineKm($lat, $lng, $c['lat'], $c['lng']) <= $radiusKm) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Flatten the free-text aliases for a set of canonical region keys.
     *
     * @param  string[]  $keys
     * @return string[]
     */
    public static function aliasesFor(array $keys): array
    {
        $all = self::aliases();
        $out = [];
        foreach ($keys as $k) {
            foreach ($all[$k] ?? [] as $a) {
                $out[] = $a;
            }
        }
        return array_values(array_unique($out));
    }
}
