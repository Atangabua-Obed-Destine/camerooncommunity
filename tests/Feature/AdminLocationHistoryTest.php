<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLocationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class AdminLocationHistoryTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        foreach (['super_admin', 'admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Permission::findOrCreate('view_user_location', 'web');
        Role::findByName('super_admin', 'web')->givePermissionTo('view_user_location');
    }

    private int $adminSeq = 0;

    private function admin(bool $super = true): User
    {
        $this->adminSeq++;
        $user = $this->createUser([
            'username'          => ($super ? 'boss' : 'mod') . $this->adminSeq,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($super ? 'super_admin' : 'admin');

        return $user;
    }

    private function report(User $user, float $lat, float $lng): void
    {
        $this->actingAs($user)->postJson('/api/location/update', [
            'lat'     => $lat,
            'lng'     => $lng,
            'country' => 'Cameroon',
            'region'  => 'Northwest',
        ])->assertOk();
    }

    public function test_a_move_is_recorded_and_a_repeat_of_the_same_spot_is_not(): void
    {
        $user = $this->createUser(['username' => 'traveller', 'email_verified_at' => now()]);

        $this->report($user, 5.9597, 10.1459);                 // Bamenda
        $this->assertSame(1, UserLocationHistory::where('user_id', $user->id)->count());

        // Same place again a moment later: nothing new to say.
        $this->report($user, 5.9598, 10.1460);
        $this->assertSame(1, UserLocationHistory::where('user_id', $user->id)->count());

        // A real move is a new point.
        $this->report($user, 4.0511, 9.7679);                  // Douala
        $this->assertSame(2, UserLocationHistory::where('user_id', $user->id)->count());
    }

    public function test_a_quiet_phone_is_recorded_again_after_the_gap(): void
    {
        $user = $this->createUser(['username' => 'stayer', 'email_verified_at' => now()]);

        $this->report($user, 5.9597, 10.1459);

        // Age the only point past the quiet window.
        UserLocationHistory::where('user_id', $user->id)
            ->update(['created_at' => now()->subMinutes(30)]);

        $this->report($user, 5.9597, 10.1459);

        $this->assertSame(2, UserLocationHistory::where('user_id', $user->id)->count());
    }

    public function test_pruning_keeps_the_retention_window(): void
    {
        $user = $this->createUser(['username' => 'old', 'email_verified_at' => now()]);

        $keep = UserLocationHistory::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
            'lat' => 1, 'lng' => 1, 'source' => 'gps',
        ]);
        $drop = UserLocationHistory::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $user->id,
            'lat' => 2, 'lng' => 2, 'source' => 'gps',
        ]);
        UserLocationHistory::whereKey($drop->id)->update(['created_at' => now()->subDays(100)]);

        $this->artisan('locations:prune', ['--days' => 90])->assertSuccessful();

        $this->assertModelExists($keep->fresh());
        $this->assertNull(UserLocationHistory::find($drop->id));
    }

    public function test_the_trail_needs_the_permission(): void
    {
        $target = $this->createUser(['username' => 'subject', 'email_verified_at' => now()]);

        // An admin without the permission cannot open it, and sees no coordinates.
        $this->actingAs($this->admin(super: false))
            ->get(route('admin.users.locations', $target))
            ->assertForbidden();

        $this->actingAs($this->admin(super: false))
            ->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertDontSee('Exact position');

        // A super admin can.
        $this->actingAs($this->admin())
            ->get(route('admin.users.locations', $target))
            ->assertOk()
            ->assertSee('Location history');
    }

    public function test_opening_a_trail_is_itself_written_to_the_audit_log(): void
    {
        $boss   = $this->admin();
        $target = $this->createUser(['username' => 'watched', 'email_verified_at' => now()]);

        $this->actingAs($boss)->get(route('admin.users.locations', $target))->assertOk();

        $entry = Activity::where('description', 'Viewed location history')->latest('id')->first();

        $this->assertNotNull($entry, 'viewing a trail must be logged');
        $this->assertSame($boss->id, $entry->causer_id);
        $this->assertSame($target->id, $entry->subject_id);
    }

    public function test_the_live_map_is_behind_the_same_permission(): void
    {
        $this->actingAs($this->admin(super: false))->get(route('admin.map'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.map'))->assertOk();
    }
}
