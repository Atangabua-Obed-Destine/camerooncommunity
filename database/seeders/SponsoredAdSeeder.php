<?php

namespace Database\Seeders;

use App\Models\SponsoredAd;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SponsoredAdSeeder extends Seeder
{
    public function run(): void
    {
        // Bind tenant early so BelongsToTenant global scope resolves
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->warn('No tenant found — skipping ad seeder.');
            return;
        }

        app()->instance('currentTenant', $tenant);

        $adminId = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super_admin')
            ->value('users.id');

        $ads = [
            [
                'title' => 'MTN MoMo — Send Money Instantly',
                'description' => 'Transfer money to family back home in Cameroon. Fast, secure & low fees with MTN Mobile Money.',
                'image_url' => 'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?w=600&h=400&fit=crop',
                'link_url' => 'https://mtn.cm',
                'link_label' => 'Send Now',
                'advertiser_name' => 'MTN Cameroon',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 90,
                'budget' => 5000.00,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->addMonths(3),
            ],
            [
                'title' => 'Jumia Cameroon — Mega Sale!',
                'description' => 'Up to 70% off electronics, fashion & home. Free delivery in Douala & Yaoundé.',
                'image_url' => 'https://images.unsplash.com/photo-1607082349566-187342175e2f?w=600&h=400&fit=crop',
                'link_url' => 'https://jumia.cm',
                'link_label' => 'Shop Now',
                'advertiser_name' => 'Jumia Cameroon',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 85,
                'budget' => 3000.00,
                'starts_at' => now()->subDays(2),
                'expires_at' => now()->addMonths(2),
            ],
            [
                'title' => 'Learn French Online — CamerLearn',
                'description' => 'Master French or English with certified Cameroonian tutors. First lesson free!',
                'image_url' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=600&h=400&fit=crop',
                'link_url' => 'https://example.com/camerlearn',
                'link_label' => 'Start Free',
                'advertiser_name' => 'CamerLearn',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 70,
                'budget' => 1500.00,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addMonths(6),
            ],
            [
                'title' => 'Cameroon Airlines — Fly Home',
                'description' => 'Direct flights Douala ↔ Paris, Brussels, London. Book early & save up to 30%.',
                'image_url' => 'https://images.unsplash.com/photo-1436491865332-7a61a109db05?w=600&h=400&fit=crop',
                'link_url' => 'https://example.com/camair',
                'link_label' => 'Book Flight',
                'advertiser_name' => 'Camair-Co',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 80,
                'budget' => 8000.00,
                'starts_at' => now(),
                'expires_at' => now()->addMonths(4),
            ],
            [
                'title' => 'Invest in Cameroon Real Estate',
                'description' => 'Build your dream home in Douala or Yaoundé. Secure plots starting from 5M FCFA.',
                'image_url' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&h=400&fit=crop',
                'link_url' => 'https://example.com/realestate',
                'link_label' => 'View Plots',
                'advertiser_name' => 'CamProperty',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 60,
                'budget' => 2000.00,
                'starts_at' => now()->subDays(3),
                'expires_at' => now()->addMonths(5),
            ],
            [
                'title' => 'Diaspora Banking — UBA Cameroon',
                'description' => 'Open a diaspora account online. Send, save & invest from anywhere in the world.',
                'image_url' => 'https://images.unsplash.com/photo-1601597111158-2fceff292cdc?w=600&h=400&fit=crop',
                'link_url' => 'https://example.com/uba',
                'link_label' => 'Open Account',
                'advertiser_name' => 'UBA Cameroon',
                'placement' => 'yard_sidebar',
                'status' => 'active',
                'priority' => 75,
                'budget' => 4000.00,
                'starts_at' => now()->subDays(1),
                'expires_at' => now()->addMonths(3),
            ],
        ];

        foreach ($ads as $ad) {
            SponsoredAd::create(array_merge($ad, [
                'tenant_id' => $tenant->id,
                'created_by' => $adminId,
            ]));
        }

        $this->command->info('✅ Seeded ' . count($ads) . ' sponsored ads.');
    }
}
