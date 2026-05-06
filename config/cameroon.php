<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cameroon Network — Platform Configuration
    |--------------------------------------------------------------------------
    */

    'company' => [
        'name' => 'I-NNOVA CM',
        'tagline' => 'Transforming Communities. Empowering Innovators.',
        'address' => 'Belgocam Building, City-Chemist, Bamenda, Cameroon',
    ],

    'platform' => [
        'name' => 'Cameroon Network',
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
    | UK ITL Regions (Integrated Transport Levels)
    | For United Kingdom, use ITL regions instead of cities
    | These are the official statistical regions
    |--------------------------------------------------------------------------
    */
    'uk_itl_regions' => [
        'UKC' => 'North East',
        'UKD' => 'North West',
        'UKE' => 'Yorkshire and The Humber',
        'UKF' => 'East Midlands',
        'UKG' => 'West Midlands',
        'UKH' => 'East',
        'UKI' => 'London',
        'UKJ' => 'South East',
        'UKK' => 'South West',
        'UKL' => 'Wales',
        'UKM' => 'Scotland',
        'UKN' => 'Northern Ireland',
    ],

    /*
    |--------------------------------------------------------------------------
    | UK City to ITL Region Mapping
    | Maps major UK cities to their corresponding ITL1 region
    |--------------------------------------------------------------------------
    */
    'gb_city_to_region' => [
        'london' => 'London',
        'manchester' => 'North West',
        'birmingham' => 'West Midlands',
        'leeds' => 'Yorkshire and The Humber',
        'glasgow' => 'Scotland',
        'sheffield' => 'Yorkshire and The Humber',
        'edinburgh' => 'Scotland',
        'liverpool' => 'North West',
        'bristol' => 'South West',
        'newcastle' => 'North East',
        'belfast' => 'Northern Ireland',
        'cardiff' => 'Wales',
        'coventry' => 'West Midlands',
        'northampton' => 'East Midlands',
        'nottingham' => 'East Midlands',
        'leicester' => 'East Midlands',
        'derby' => 'East Midlands',
        'cambridge' => 'East of England',
        'norwich' => 'East of England',
        'peterborough' => 'East of England',
        'brighton' => 'South East',
        'southampton' => 'South East',
        'oxford' => 'South East',
        'reading' => 'South East',
        'exeter' => 'South West',
        'plymouth' => 'South West',
        'bath' => 'South West',
        'swansea' => 'Wales',
        'aberdeen' => 'Scotland',
        'dundee' => 'Scotland',
        'perth' => 'Scotland',
        'inverness' => 'Scotland',
        'stirling' => 'Scotland',
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
        // Official ITL1 regions of the United Kingdom: 9 English regions
        // + Scotland + Wales + Northern Ireland. We treat the UK as a single
        // unified country (no per-nation split) — diaspora users are routed
        // to the correct region using the city→region map below.
        'GB' => [
            'London',
            'South East',
            'South West',
            'East of England',
            'East Midlands',
            'West Midlands',
            'Yorkshire and the Humber',
            'North West',
            'North East',
            'Scotland',
            'Wales',
            'Northern Ireland',
        ],
        'FR' => ['Île-de-France', 'Auvergne-Rhône-Alpes', 'Provence-Alpes-Côte d\'Azur', 'Occitanie', 'Nouvelle-Aquitaine', 'Grand Est'],
        'DE' => ['Bavaria', 'Berlin', 'North Rhine-Westphalia', 'Baden-Württemberg', 'Hesse', 'Lower Saxony'],
        'US' => ['Northeast', 'Southeast', 'Midwest', 'Southwest', 'West Coast'],
        'CA' => ['Ontario', 'Quebec', 'British Columbia', 'Alberta'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UK city → ITL1 region map
    | Used to normalize UK locations: a user detected in "Birmingham" is
    | routed to "West Midlands"; "Cardiff" → "Wales"; "Glasgow" → "Scotland".
    | Keys are lowercased city names. Anything not listed defaults via the
    | `gb_region_aliases` map below (when ipapi returns a country/county).
    |--------------------------------------------------------------------------
    */
    'gb_city_to_region' => [
        // London
        'london' => 'London', 'city of london' => 'London', 'westminster' => 'London',
        'croydon' => 'London', 'bromley' => 'London', 'ealing' => 'London',
        'enfield' => 'London', 'barnet' => 'London', 'wandsworth' => 'London',
        'lambeth' => 'London', 'southwark' => 'London', 'lewisham' => 'London',
        'greenwich' => 'London', 'hackney' => 'London', 'islington' => 'London',
        'camden' => 'London', 'haringey' => 'London', 'newham' => 'London',
        'tower hamlets' => 'London', 'redbridge' => 'London', 'waltham forest' => 'London',
        'hounslow' => 'London', 'brent' => 'London', 'harrow' => 'London',
        'hillingdon' => 'London', 'kingston' => 'London', 'kingston upon thames' => 'London',
        'merton' => 'London', 'sutton' => 'London',
        // South East
        'brighton' => 'South East', 'reading' => 'South East', 'oxford' => 'South East',
        'milton keynes' => 'South East', 'southampton' => 'South East',
        'portsmouth' => 'South East', 'slough' => 'South East', 'crawley' => 'South East',
        'hastings' => 'South East', 'eastbourne' => 'South East', 'guildford' => 'South East',
        'maidstone' => 'South East', 'canterbury' => 'South East', 'medway' => 'South East',
        'high wycombe' => 'South East', 'basingstoke' => 'South East', 'aylesbury' => 'South East',
        'worthing' => 'South East', 'chichester' => 'South East', 'dover' => 'South East',
        // South West
        'bristol' => 'South West', 'plymouth' => 'South West', 'exeter' => 'South West',
        'bournemouth' => 'South West', 'poole' => 'South West', 'gloucester' => 'South West',
        'cheltenham' => 'South West', 'bath' => 'South West', 'swindon' => 'South West',
        'torquay' => 'South West', 'taunton' => 'South West', 'truro' => 'South West',
        'salisbury' => 'South West', 'weston-super-mare' => 'South West',
        // East of England
        'cambridge' => 'East of England', 'norwich' => 'East of England',
        'ipswich' => 'East of England', 'luton' => 'East of England',
        'peterborough' => 'East of England', 'chelmsford' => 'East of England',
        'colchester' => 'East of England', 'southend-on-sea' => 'East of England',
        'southend' => 'East of England', 'basildon' => 'East of England',
        'st albans' => 'East of England', 'watford' => 'East of England',
        'bedford' => 'East of England', 'stevenage' => 'East of England',
        'harlow' => 'East of England',
        // East Midlands
        'nottingham' => 'East Midlands', 'leicester' => 'East Midlands',
        'derby' => 'East Midlands', 'lincoln' => 'East Midlands',
        'northampton' => 'East Midlands', 'mansfield' => 'East Midlands',
        'chesterfield' => 'East Midlands', 'kettering' => 'East Midlands',
        'corby' => 'East Midlands', 'loughborough' => 'East Midlands',
        // West Midlands
        'birmingham' => 'West Midlands', 'coventry' => 'West Midlands',
        'wolverhampton' => 'West Midlands', 'stoke-on-trent' => 'West Midlands',
        'stoke' => 'West Midlands', 'walsall' => 'West Midlands',
        'dudley' => 'West Midlands', 'sandwell' => 'West Midlands',
        'solihull' => 'West Midlands', 'worcester' => 'West Midlands',
        'shrewsbury' => 'West Midlands', 'telford' => 'West Midlands',
        'hereford' => 'West Midlands', 'stafford' => 'West Midlands',
        // Yorkshire and the Humber
        'leeds' => 'Yorkshire and the Humber', 'sheffield' => 'Yorkshire and the Humber',
        'bradford' => 'Yorkshire and the Humber', 'hull' => 'Yorkshire and the Humber',
        'kingston upon hull' => 'Yorkshire and the Humber',
        'york' => 'Yorkshire and the Humber', 'wakefield' => 'Yorkshire and the Humber',
        'huddersfield' => 'Yorkshire and the Humber', 'doncaster' => 'Yorkshire and the Humber',
        'rotherham' => 'Yorkshire and the Humber', 'barnsley' => 'Yorkshire and the Humber',
        'halifax' => 'Yorkshire and the Humber', 'grimsby' => 'Yorkshire and the Humber',
        // North West
        'manchester' => 'North West', 'liverpool' => 'North West',
        'preston' => 'North West', 'blackpool' => 'North West',
        'blackburn' => 'North West', 'bolton' => 'North West',
        'oldham' => 'North West', 'rochdale' => 'North West',
        'salford' => 'North West', 'stockport' => 'North West',
        'warrington' => 'North West', 'wigan' => 'North West',
        'lancaster' => 'North West', 'chester' => 'North West',
        'crewe' => 'North West', 'birkenhead' => 'North West',
        'st helens' => 'North West', 'bury' => 'North West',
        // North East
        'newcastle' => 'North East', 'newcastle upon tyne' => 'North East',
        'sunderland' => 'North East', 'middlesbrough' => 'North East',
        'gateshead' => 'North East', 'durham' => 'North East',
        'darlington' => 'North East', 'hartlepool' => 'North East',
        'stockton-on-tees' => 'North East', 'stockton' => 'North East',
        'south shields' => 'North East', 'north shields' => 'North East',
        // Scotland
        'glasgow' => 'Scotland', 'edinburgh' => 'Scotland', 'aberdeen' => 'Scotland',
        'dundee' => 'Scotland', 'inverness' => 'Scotland', 'stirling' => 'Scotland',
        'perth' => 'Scotland', 'paisley' => 'Scotland', 'livingston' => 'Scotland',
        'east kilbride' => 'Scotland', 'cumbernauld' => 'Scotland', 'dunfermline' => 'Scotland',
        'kirkcaldy' => 'Scotland', 'kilmarnock' => 'Scotland',
        'greenock' => 'Scotland', 'ayr' => 'Scotland',
        // Wales
        'cardiff' => 'Wales', 'swansea' => 'Wales', 'newport' => 'Wales',
        'wrexham' => 'Wales', 'barry' => 'Wales', 'caerphilly' => 'Wales',
        'merthyr tydfil' => 'Wales', 'aberystwyth' => 'Wales',
        'bangor' => 'Wales', 'llandudno' => 'Wales', 'rhyl' => 'Wales',
        // Northern Ireland
        'belfast' => 'Northern Ireland', 'derry' => 'Northern Ireland',
        'londonderry' => 'Northern Ireland', 'lisburn' => 'Northern Ireland',
        'newry' => 'Northern Ireland', 'armagh' => 'Northern Ireland',
        'bangor (ni)' => 'Northern Ireland', 'craigavon' => 'Northern Ireland',
        'antrim' => 'Northern Ireland', 'ballymena' => 'Northern Ireland',
    ],

    /*
    |--------------------------------------------------------------------------
    | UK region aliases — when the geo provider returns a country/county
    | name instead of an ITL1 region, we map it to the right one.
    |--------------------------------------------------------------------------
    */
    'gb_region_aliases' => [
        // ipapi sometimes returns "England" for any English address — this
        // is a last-resort fallback when no city match is found.
        'england' => null, // handled by city map; leave untouched if no city
        'scotland' => 'Scotland',
        'wales' => 'Wales',
        'cymru' => 'Wales',
        'northern ireland' => 'Northern Ireland',
        // Common county / metropolitan returns
        'greater london' => 'London',
        'greater manchester' => 'North West',
        'merseyside' => 'North West',
        'lancashire' => 'North West',
        'cheshire' => 'North West',
        'cumbria' => 'North West',
        'tyne and wear' => 'North East',
        'county durham' => 'North East',
        'northumberland' => 'North East',
        'south yorkshire' => 'Yorkshire and the Humber',
        'west yorkshire' => 'Yorkshire and the Humber',
        'north yorkshire' => 'Yorkshire and the Humber',
        'east riding of yorkshire' => 'Yorkshire and the Humber',
        'derbyshire' => 'East Midlands',
        'nottinghamshire' => 'East Midlands',
        'leicestershire' => 'East Midlands',
        'lincolnshire' => 'East Midlands',
        'northamptonshire' => 'East Midlands',
        'rutland' => 'East Midlands',
        'west midlands (county)' => 'West Midlands',
        'staffordshire' => 'West Midlands',
        'warwickshire' => 'West Midlands',
        'worcestershire' => 'West Midlands',
        'herefordshire' => 'West Midlands',
        'shropshire' => 'West Midlands',
        'norfolk' => 'East of England',
        'suffolk' => 'East of England',
        'essex' => 'East of England',
        'cambridgeshire' => 'East of England',
        'bedfordshire' => 'East of England',
        'hertfordshire' => 'East of England',
        'kent' => 'South East',
        'surrey' => 'South East',
        'east sussex' => 'South East',
        'west sussex' => 'South East',
        'hampshire' => 'South East',
        'isle of wight' => 'South East',
        'berkshire' => 'South East',
        'oxfordshire' => 'South East',
        'buckinghamshire' => 'South East',
        'gloucestershire' => 'South West',
        'wiltshire' => 'South West',
        'somerset' => 'South West',
        'dorset' => 'South West',
        'devon' => 'South West',
        'cornwall' => 'South West',
        'bristol, city of' => 'South West',
    ],


    /*
    |--------------------------------------------------------------------------
    | Major cities pre-seeded per pilot country
    | These get City Rooms at seeder time so early users land in populated rooms
    |--------------------------------------------------------------------------
    */
    'seeded_cities' => [
        // NOTE: UK is intentionally NOT seeded by city. UK uses the 12 ITL1
        // regions (see `seeded_regions.GB`) as the sub-national grouping.
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
