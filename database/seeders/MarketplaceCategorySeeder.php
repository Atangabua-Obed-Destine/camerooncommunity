<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MarketplaceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('domain', 'camerooncommunity.net')->first();

        if (! $tenant) {
            return;
        }

        app()->instance('currentTenant', $tenant);

        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'icon' => 'laptop', 'sort_order' => 1],
            ['name' => 'Vehicles', 'slug' => 'vehicles', 'icon' => 'car', 'sort_order' => 2],
            ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => 'shirt', 'sort_order' => 3],
            ['name' => 'Home & Garden', 'slug' => 'home-garden', 'icon' => 'home', 'sort_order' => 4],
            ['name' => 'African Products', 'slug' => 'african-products', 'icon' => 'globe-2', 'sort_order' => 5],
            ['name' => 'Books & Stationery', 'slug' => 'books', 'icon' => 'book-open', 'sort_order' => 6],
            ['name' => 'Health & Beauty', 'slug' => 'health-beauty', 'icon' => 'heart', 'sort_order' => 7],
            ['name' => 'Sports & Leisure', 'slug' => 'sports', 'icon' => 'trophy', 'sort_order' => 8],
            ['name' => 'Services', 'slug' => 'services', 'icon' => 'briefcase', 'sort_order' => 9],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'grid', 'sort_order' => 10],
        ];

        foreach ($categories as $cat) {
            MarketplaceCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $cat['slug']],
                array_merge($cat, ['tenant_id' => $tenant->id]),
            );
        }
    }
}
