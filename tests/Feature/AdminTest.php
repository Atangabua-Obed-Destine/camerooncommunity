<?php

namespace Tests\Feature;

use App\Enums\Solidarity\CampaignStatus;
use App\Models\SolidarityCampaign;
use App\Models\YardRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class AdminTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    // ─── Access control ───

    public function test_guest_cannot_access_admin(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_regular_user_cannot_access_admin(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
    }

    // ─── Admin pages load ───

    public function test_admin_users_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/users');
        $response->assertStatus(200);
    }

    public function test_admin_yard_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/yard');
        $response->assertStatus(200);
    }

    public function test_admin_solidarity_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/solidarity');
        $response->assertStatus(200);
    }

    public function test_admin_moderation_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/moderation');
        $response->assertStatus(200);
    }

    public function test_admin_settings_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/settings');
        $response->assertStatus(200);
    }

    public function test_admin_audit_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/audit');
        $response->assertStatus(200);
    }

    public function test_admin_analytics_page_loads(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/analytics');
        $response->assertStatus(200);
    }

    // ─── Solidarity actions ───

    public function test_admin_can_approve_campaign(): void
    {
        $admin = $this->createAdmin();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        $campaign = SolidarityCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'status' => CampaignStatus::PendingApproval,
        ]);

        $response = $this->actingAs($admin)->post("/admin/solidarity/{$campaign->id}/approve");
        $response->assertRedirect();

        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Active, $campaign->status);
        $this->assertEquals($admin->id, $campaign->approved_by);
    }

    public function test_admin_can_reject_campaign(): void
    {
        $admin = $this->createAdmin();
        $room = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);

        $campaign = SolidarityCampaign::factory()->create([
            'tenant_id' => $this->tenant->id,
            'room_id' => $room->id,
            'status' => CampaignStatus::PendingApproval,
        ]);

        $response = $this->actingAs($admin)->post("/admin/solidarity/{$campaign->id}/reject", [
            'reason' => 'Insufficient documentation',
        ]);
        $response->assertRedirect();

        $campaign->refresh();
        $this->assertEquals(CampaignStatus::Rejected, $campaign->status);
        $this->assertEquals('Insufficient documentation', $campaign->rejection_reason);
    }
}
