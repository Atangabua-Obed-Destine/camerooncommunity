<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class AdminUserControlTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        foreach (['super_admin', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (['impersonate_users', 'view_system_health'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            Role::findByName('super_admin', 'web')->givePermissionTo($permission);
        }
    }

    private function admin(bool $super = true): User
    {
        $this->seq++;
        $user = $this->createUser(['username' => 'adm' . $this->seq, 'email_verified_at' => now()]);
        $user->assignRole($super ? 'super_admin' : 'admin');

        return $user;
    }

    public function test_suspending_an_account_locks_the_member_out(): void
    {
        $boss   = $this->admin();
        $member = $this->createUser(['username' => 'troublemaker', 'email_verified_at' => now()]);

        $this->actingAs($boss)
            ->post(route('admin.users.suspension', $member), ['reason' => 'spam'])
            ->assertRedirect();

        $this->assertFalse((bool) $member->fresh()->is_active);

        // EnsureUserActive turns them away on the next request.
        $this->actingAs($member->fresh())->get('/yard')->assertRedirect('/login');

        // And the reason is on the record.
        $entry = Activity::where('description', 'Suspended account')->latest('id')->first();
        $this->assertSame('spam', $entry->properties['reason'] ?? null);
    }

    public function test_an_admin_cannot_suspend_themselves(): void
    {
        $boss = $this->admin();

        $this->actingAs($boss)->post(route('admin.users.suspension', $boss));

        $this->assertTrue((bool) $boss->fresh()->is_active);
    }

    public function test_impersonation_needs_the_permission_and_returns_cleanly(): void
    {
        $plainAdmin = $this->admin(super: false);
        $member     = $this->createUser(['username' => 'subject', 'email_verified_at' => now()]);

        $this->actingAs($plainAdmin)
            ->post(route('admin.users.impersonate', $member))
            ->assertForbidden();

        $boss = $this->admin();

        $this->actingAs($boss)
            ->post(route('admin.users.impersonate', $member))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($member);
        $this->assertSame($boss->id, session('impersonator_id'));

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.users'));
        $this->assertAuthenticatedAs($boss);
        $this->assertNull(session('impersonator_id'));
    }

    public function test_one_admin_cannot_impersonate_another(): void
    {
        $boss  = $this->admin();
        $other = $this->admin(super: false);

        $this->actingAs($boss)->post(route('admin.users.impersonate', $other));

        $this->assertAuthenticatedAs($boss);
    }

    public function test_every_admin_write_lands_in_the_audit_log(): void
    {
        $boss   = $this->admin();
        $member = $this->createUser(['username' => 'logged', 'email_verified_at' => now()]);

        $this->actingAs($boss)->post(route('admin.users.force-logout', $member));

        // The middleware records the action even though forceLogout logs its own.
        $this->assertTrue(
            Activity::where('description', 'Admin action')
                ->where('causer_id', $boss->id)
                ->exists(),
            'the admin middleware should record non-GET requests'
        );
    }

    public function test_the_health_page_is_behind_its_permission(): void
    {
        $this->actingAs($this->admin(super: false))->get(route('admin.health'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.health'))->assertOk()->assertSee('System health');
    }
}
