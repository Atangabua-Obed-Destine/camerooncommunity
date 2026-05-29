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
     * Map a free-text region string to a canonical key. Returns '' if no match.
     * Handles French + English names, common cities, and obvious misspellings.
     */
    public static function matchRegion(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') { return ''; }
        // strip accents the simple way
        $s = strtr($s, ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ô'=>'o','î'=>'i','ï'=>'i','ç'=>'c','ù'=>'u','û'=>'u']);

        $map = [
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

        foreach ($map as $key => $aliases) {
            foreach ($aliases as $a) {
                if (str_contains($s, $a)) { return $key; }
            }
        }
        return '';
    }
}
