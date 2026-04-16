<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cameroon Community — Platform Configuration
    |--------------------------------------------------------------------------
    */

    'company' => [
        'name' => 'I-NNOVA CM',
        'tagline' => 'Transforming Communities. Empowering Innovators.',
        'address' => 'Belgocam Building, City-Chemist, Bamenda, Cameroon',
    ],

    'platform' => [
        'name' => 'Cameroon Community',
        'tagline' => 'Connecting Cameroonians. Wherever They Are.',
        'domain' => 'camerooncommunity.net',
        'pilot_market' => 'United Kingdom',
    ],

    'colors' => [
        'green' => '#006B3F',
        'red' => '#CE1126',
        'yellow' => '#FCD116',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cameroon Regions (all 10)
    |--------------------------------------------------------------------------
    */
    'regions' => [
        'adamawa' => 'Adamawa',
        'centre' => 'Centre',
        'east' => 'East',
        'far_north' => 'Far North',
        'littoral' => 'Littoral',
        'north' => 'North',
        'northwest' => 'Northwest',
        'south' => 'South',
        'southwest' => 'Southwest',
        'west' => 'West',
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries with seeded National Rooms at launch
    | Key = ISO alpha-2 code, value = display name
    |--------------------------------------------------------------------------
    */
    'seeded_countries' => [
        'CM' => 'Cameroon',
        'GB' => 'United Kingdom',
        'FR' => 'France',
        'DE' => 'Germany',
        'BE' => 'Belgium',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'US' => 'United States',
        'CA' => 'Canada',
        'ZA' => 'South Africa',
        'NG' => 'Nigeria',
        'GA' => 'Gabon',
        'GQ' => 'Equatorial Guinea',
        'CI' => "Côte d'Ivoire",
        'SN' => 'Senegal',
        'GH' => 'Ghana',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'CN' => 'China',
        'TR' => 'Turkey',
        'RU' => 'Russia',
        'IN' => 'India',
        'BR' => 'Brazil',
        'AU' => 'Australia',
    ],

    /*
    |--------------------------------------------------------------------------
    | Regions pre-seeded per country
    | These get Regional Rooms at seeder time for regional community grouping
    |--------------------------------------------------------------------------
    */
    'seeded_regions' => [
        'CM' => ['Adamawa', 'Centre', 'East', 'Far North', 'Littoral', 'North', 'Northwest', 'South', 'Southwest', 'West'],
        'GB' => ['England', 'Scotland', 'Wales', 'Northern Ireland'],
        'FR' => ['Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur', 'Occitanie', 'Nouvelle-Aquitaine', 'Grand Est'],
        'DE' => ['Bavaria', 'Berlin', 'North Rhine-Westphalia', 'Baden-Württemberg', 'Hesse', 'Lower Saxony'],
        'US' => ['Northeast', 'Southeast', 'Midwest', 'Southwest', 'West Coast'],
        'CA' => ['Ontario', 'Quebec', 'British Columbia', 'Alberta'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Major cities pre-seeded per pilot country
    | These get City Rooms at seeder time so early users land in populated rooms
    |--------------------------------------------------------------------------
    */
    'seeded_cities' => [
        'GB' => ['London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Bristol', 'Edinburgh', 'Sheffield', 'Nottingham'],
        'FR' => ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nice', 'Bordeaux', 'Lille', 'Strasbourg'],
        'DE' => ['Berlin', 'Munich', 'Hamburg', 'Frankfurt', 'Cologne', 'Düsseldorf', 'Stuttgart'],
        'US' => ['New York', 'Houston', 'Washington DC', 'Dallas', 'Atlanta', 'Los Angeles', 'Chicago', 'Philadelphia', 'Baltimore', 'Miami'],
        'CA' => ['Toronto', 'Montreal', 'Ottawa', 'Calgary', 'Vancouver', 'Edmonton'],
        'BE' => ['Brussels', 'Antwerp', 'Liège', 'Ghent'],
        'IT' => ['Rome', 'Milan', 'Naples', 'Turin'],
        'ES' => ['Madrid', 'Barcelona', 'Valencia', 'Seville'],
        'CM' => ['Douala', 'Yaoundé', 'Bamenda', 'Bafoussam', 'Garoua', 'Maroua', 'Buea', 'Limbe', 'Kumba', 'Bertoua'],
        'ZA' => ['Johannesburg', 'Cape Town', 'Pretoria', 'Durban'],
        'NG' => ['Lagos', 'Abuja', 'Port Harcourt'],
        'GA' => ['Libreville'],
        'GQ' => ['Malabo'],
        'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah'],
        'SA' => ['Riyadh', 'Jeddah'],
        'AU' => ['Sydney', 'Melbourne', 'Brisbane', 'Perth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default platform settings (seeded into platform_settings table)
    |--------------------------------------------------------------------------
    */
    'default_settings' => [
        'solidarity_platform_cut_percent' => '5.00',
        'solidarity_max_campaign_days' => '90',
        'solidarity_min_target_amount' => '50.00',
        'default_currency' => 'GBP',
        'openai_model' => 'gpt-4o-mini',
        'openai_enabled' => 'true',
        'max_private_group_members' => '200',
        'story_expiry_hours' => '24',
        'marketplace_listing_expiry_days' => '30',
        'sos_radius_km' => '50',
        'minimum_app_version' => '1.0.0',
        'maintenance_mode' => 'false',
        'location_detection_mode' => 'gps',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'moderator' => 'Moderator',
        'support' => 'Support',
    ],

    /*
    |--------------------------------------------------------------------------
    | Community Points
    |--------------------------------------------------------------------------
    */
    'points' => [
        'daily_login' => 5,
        'send_message' => 1,
        'create_solidarity_campaign' => 10,
        'contribute_to_solidarity' => 15,
        'create_listing' => 5,
        'create_event' => 10,
        'invite_user' => 20,
        'profile_completed' => 25,
        'first_message' => 10,
    ],
];
