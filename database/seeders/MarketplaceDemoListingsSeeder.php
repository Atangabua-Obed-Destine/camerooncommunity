<?php

namespace Database\Seeders;

use App\Enums\ListingCondition;
use App\Enums\ListingFulfillment;
use App\Enums\ListingPriceType;
use App\Enums\ListingStatus;
use App\Enums\ListingVisibility;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceListing;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dev-only seeder to populate sample marketplace listings for QA.
 * Run via: php artisan db:seed --class=MarketplaceDemoListingsSeeder
 */
class MarketplaceDemoListingsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('domain', 'camerooncommunity.net')->first()
            ?? Tenant::query()->first();
        if (! $tenant) { return; }
        app()->instance('currentTenant', $tenant);

        $user = User::query()->orderBy('id')->first();
        if (! $user) {
            $this->command?->warn('No users — skipping demo listings.');
            return;
        }

        $samples = [
            ['cars',           'Toyota Corolla 2015 — Clean Title',  'Well maintained Corolla, second owner. Cold AC, new tyres. Serious buyers only.',  3500000, 'good',      'pickup',            'Douala',   'Littoral'],
            ['smartphones',    'iPhone 13 Pro 128GB — Sierra Blue',  'Excellent condition. Battery 91%. Comes with original box and cable.',           420000,  'like_new',  'local_delivery',    'Yaoundé',  'Centre'],
            ['laptops',        'MacBook Air M2 13" — 2023',          'Like new, 8GB/256GB. Includes laptop sleeve. Reason for sale: upgraded.',         650000,  'like_new',  'pickup',            'Buea',     'South-West'],
            ['apartments-rent','Modern 2-Bedroom Apartment in Bonapriso','Newly renovated, 24h water + electricity, secure compound.',                  280000,  'new',       'pickup',            'Douala',   'Littoral'],
            ['cm-specialty',   'Fresh Ndolé (1kg) — Home Cooked',    'Cooked with stockfish and shrimp. Vacuum packed and ready for shipping.',         5000,    'new',       'diaspora_shippable','Bafoussam','West'],
            ['traditional',    'Toghu Outfit — Custom Tailored',     'Handmade Toghu jacket with matching cap. Specify your measurements.',             45000,   'new',       'diaspora_shippable','Bamenda',  'North-West'],
            ['free-stuff',     'Free: 3 Boxes of Used Books',        'Mix of novels, school books, French + English. Pickup only.',                     0,       'good',      'pickup',            'Yaoundé',  'Centre'],
            ['tutoring',       'Math Tutor — Form 1 to Upper Sixth', 'Cambridge syllabus specialist. Online or in-person in Buea.',                     15000,   'new',       'digital',           'Buea',     'South-West'],
        ];

        foreach ($samples as $i => [$slug, $title, $desc, $price, $cond, $fulfill, $city, $region]) {
            $cat = MarketplaceCategory::query()->where('slug', $slug)->first();
            if (! $cat) { continue; }

            MarketplaceListing::firstOrCreate(
                ['user_id' => $user->id, 'title' => $title],
                [
                    'category_id' => $cat->id,
                    'description' => $desc,
                    'language' => 'en',
                    'price_type' => $price > 0 ? ListingPriceType::Negotiable->value : ListingPriceType::Free->value,
                    'price' => $price,
                    'currency' => 'XAF',
                    'condition' => $cond,
                    'quantity' => 1,
                    'fulfillment' => $fulfill,
                    'country' => 'CM',
                    'region' => $region,
                    'city' => $city,
                    'visibility' => ListingVisibility::Public->value,
                    'status' => ListingStatus::Active->value,
                    'published_at' => now()->subDays($i),
                    'expires_at' => now()->addDays(30),
                    'is_featured' => $i < 2,
                ],
            );
        }

        $this->command?->info('Demo marketplace listings seeded.');
    }
}
