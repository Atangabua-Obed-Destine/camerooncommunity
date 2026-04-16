<?php

namespace Database\Factories;

use App\Enums\Solidarity\CampaignCategory;
use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use App\Models\Tenant;
use App\Models\User;
use App\Models\YardRoom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SolidarityCampaign> */
class SolidarityCampaignFactory extends Factory
{
    protected $model = SolidarityCampaign::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::first()?->id ?? Tenant::factory(),
            'uuid' => Str::uuid()->toString(),
            'room_id' => YardRoom::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'beneficiary_name' => fake()->name(),
            'beneficiary_relationship' => 'family',
            'category' => CampaignCategory::Medical,
            'target_amount' => 5000.00,
            'current_amount' => 0.00,
            'platform_cut_percent' => 5.00,
            'currency' => 'GBP',
            'status' => CampaignStatus::PendingApproval,
            'deadline' => now()->addDays(30),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Active,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CampaignStatus::Completed,
        ]);
    }
}
