<?php

namespace Tests\Feature\Api;

use App\Enums\MessageType;
use App\Enums\RoomType;
use App\Models\Report;
use App\Models\User;
use App\Models\UserConnection;
use App\Models\UserContactName;
use App\Models\YardJoinRequest;
use App\Models\YardMessage;
use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * A chat's info panel and the selection bar's star, pin and forward, from
 * the app. The web's RoomInfo trusted a client-set room id; these check the
 * shared service does not: only members see a panel, only the admin acts as
 * one, and nothing can be forwarded out of a room the member isn't in.
 */
class YardRoomInfoApiTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        YardRoomMember::$suppressJoinAnnouncement = true;
    }

    protected function tearDown(): void
    {
        YardRoomMember::$suppressJoinAnnouncement = false;
        parent::tearDown();
    }

    private function group(User $admin, array $attrs = []): YardRoom
    {
        $room = YardRoom::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_type' => RoomType::PrivateGroup, 'is_system_room' => false,
            'name' => 'Bamenda Friends', 'created_by' => $admin->id,
        ], $attrs));
        $this->join($admin, $room, ['role' => 'admin']);

        return $room;
    }

    private function dm(User $a, User $b, array $attrs = []): YardRoom
    {
        $room = YardRoom::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_type' => RoomType::DirectMessage, 'is_system_room' => false, 'name' => 'dm',
        ], $attrs));
        $this->join($a, $room);
        $this->join($b, $room);

        return $room;
    }

    private function join(User $user, YardRoom $room, array $attrs = []): void
    {
        YardRoomMember::create(array_merge([
            'tenant_id' => $this->tenant->id, 'room_id' => $room->id, 'user_id' => $user->id,
            'role' => 'member', 'joined_at' => now()->subDay(),
        ], $attrs));
    }

    private function connect(User $a, User $b): void
    {
        UserConnection::create([
            'tenant_id' => $this->tenant->id, 'user_a_id' => min($a->id, $b->id), 'user_b_id' => max($a->id, $b->id),
            'requested_by' => $a->id, 'status' => UserConnection::STATUS_ACCEPTED,
        ]);
    }

    private function say(User $user, YardRoom $room, string $text, array $attrs = []): YardMessage
    {
        return YardMessage::create(array_merge([
            'tenant_id' => $this->tenant->id, 'uuid' => (string) Str::uuid(), 'room_id' => $room->id,
            'user_id' => $user->id, 'message_type' => MessageType::Text, 'content' => $text,
        ], $attrs));
    }

    public function test_a_member_sees_the_panel_and_a_stranger_does_not(): void
    {
        [$admin, $ama, $stranger] = [$this->createUser(), $this->createUser(['bio' => 'Ndolé lover']), $this->createUser()];
        $room = $this->group($admin, ['description' => 'Old school friends']);
        $this->join($ama, $room);
        $pinned = $this->say($admin, $room, 'Meeting on Saturday', ['is_pinned' => true, 'pinned_at' => now()]);
        $this->say($ama, $room, '', ['message_type' => MessageType::Image, 'content' => null, 'media_path' => 'yard/media/1/p.jpg']);

        $res = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertOk();

        $res->assertJsonPath('room.description', 'Old school friends')
            ->assertJsonPath('room.members_count', 2)
            ->assertJsonPath('me.is_admin', false)
            ->assertJsonPath('me.can_add_members', true)
            ->assertJsonPath('me.can_leave', true)
            ->assertJsonPath('me.can_edit_avatar', false)
            ->assertJsonPath('pinned.0.id', $pinned->id)
            ->assertJsonPath('media.0.type', 'image')
            ->assertJsonPath('partner', null)
            ->assertJsonPath('past_members', []);
        $this->assertStringStartsWith('http', $res->json('media.0.url'));
        $admins = collect($res->json('members'))->where('is_admin', true)->pluck('id')->all();
        $this->assertSame([$admin->id], $admins);
        $this->assertSame('Ndolé lover', collect($res->json('members'))->firstWhere('id', $ama->id)['meta']);

        $this->actingAs($stranger, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))
            ->assertForbidden()->assertJsonPath('code', 'not_member');
        $this->actingAs($stranger, 'sanctum')->getJson(route('api.v1.yard.info.members', $room))->assertForbidden();
    }

    public function test_pins_from_before_a_member_joined_stay_hidden_from_them(): void
    {
        [$admin, $late] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $old = $this->say($admin, $room, 'Before your time', ['is_pinned' => true, 'pinned_at' => now()]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $recent = $this->say($admin, $room, 'Welcome', ['is_pinned' => true, 'pinned_at' => now()]);
        $this->join($late, $room, ['joined_at' => now()->subHour()]);

        $res = $this->actingAs($late, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertOk();
        $this->assertSame([$recent->id], collect($res->json('pinned'))->pluck('id')->all());
    }

    public function test_a_dm_panel_shows_the_other_person_without_their_email(): void
    {
        [$ama, $ben] = [$this->createUser(), $this->createUser(['username' => 'benk', 'bio' => 'Douala', 'email' => 'ben@example.com'])];
        $this->connect($ama, $ben);
        $room = $this->dm($ama, $ben);
        UserContactName::create(['tenant_id' => $this->tenant->id, 'owner_user_id' => $ama->id, 'contact_user_id' => $ben->id, 'nickname' => 'Cousin Ben']);

        $res = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertOk();

        $res->assertJsonPath('partner.id', $ben->id)
            ->assertJsonPath('partner.name', 'Cousin Ben')
            ->assertJsonPath('partner.handle', 'benk')
            ->assertJsonPath('partner.bio', 'Douala')
            ->assertJsonPath('partner.is_blocked_by_me', false)
            ->assertJsonPath('members', [])
            ->assertJsonPath('me.can_leave', false)
            ->assertJsonPath('me.can_add_members', false);
        $this->assertStringNotContainsString('ben@example.com', $res->getContent());
    }

    public function test_the_member_search_filters_by_name(): void
    {
        [$admin, $ama] = [$this->createUser(['name' => 'Admin Person', 'username' => 'adminp']), $this->createUser(['name' => 'Ama Ndi', 'username' => 'amandi'])];
        $room = $this->group($admin);
        $this->join($ama, $room);

        $res = $this->actingAs($admin, 'sanctum')->getJson(route('api.v1.yard.info.members', $room).'?q=ama')->assertOk();
        $this->assertSame([$ama->id], collect($res->json('members'))->pluck('id')->all());
    }

    public function test_adding_members_needs_membership_and_skips_the_wrong_people(): void
    {
        [$admin, $ama, $ben, $blocker, $outsider] = [$this->createUser(), $this->createUser(['name' => 'Ama A']), $this->createUser(['name' => 'Ama B']), $this->createUser(['name' => 'Ama C']), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);
        // Ama is blocked by someone: they can't be added by her.
        UserConnection::create([
            'tenant_id' => $this->tenant->id, 'user_a_id' => min($ama->id, $blocker->id), 'user_b_id' => max($ama->id, $blocker->id),
            'requested_by' => $blocker->id, 'status' => UserConnection::STATUS_BLOCKED,
        ]);

        $search = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.candidates', $room).'?q=Ama')->assertOk();
        $found = collect($search->json('users'))->pluck('id');
        $this->assertContains($ben->id, $found);
        $this->assertNotContains($ama->id, $found, 'already a member');
        $this->assertNotContains($blocker->id, $found, 'blocked');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.members.add', $room), ['user_ids' => [$ben->id, $blocker->id, $admin->id]])
            ->assertOk()->assertJsonPath('added', 1);
        $this->assertTrue(YardRoomMember::where('room_id', $room->id)->where('user_id', $ben->id)->exists());
        $this->assertFalse(YardRoomMember::where('room_id', $room->id)->where('user_id', $blocker->id)->exists());
        $this->assertSame(1, $room->fresh()->members_count, 'the factory room starts at 0; one was added');

        // Someone outside can't add themselves (the web's crafted-call hole).
        $this->actingAs($outsider, 'sanctum')->postJson(route('api.v1.yard.info.members.add', $room), ['user_ids' => [$outsider->id]])
            ->assertForbidden();
        $this->assertFalse(YardRoomMember::where('room_id', $room->id)->where('user_id', $outsider->id)->exists());
    }

    public function test_nobody_can_be_added_to_a_dm(): void
    {
        [$ama, $ben, $cy] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $this->connect($ama, $ben);
        $room = $this->dm($ama, $ben);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.members.add', $room), ['user_ids' => [$cy->id]])->assertForbidden();
        $this->assertSame(2, YardRoomMember::where('room_id', $room->id)->count());
    }

    public function test_only_the_admin_removes_members_and_it_is_remembered(): void
    {
        [$admin, $ama, $ben] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $room = $this->group($admin, ['members_count' => 3]);
        $this->join($ama, $room);
        $this->join($ben, $room);

        $this->actingAs($ama, 'sanctum')->deleteJson(route('api.v1.yard.info.members.remove', [$room, $ben->id]))->assertForbidden();
        $this->actingAs($admin, 'sanctum')->deleteJson(route('api.v1.yard.info.members.remove', [$room, $admin->id]))->assertForbidden();

        $this->actingAs($admin, 'sanctum')->deleteJson(route('api.v1.yard.info.members.remove', [$room, $ben->id]))->assertOk();
        $this->assertFalse(YardRoomMember::where('room_id', $room->id)->where('user_id', $ben->id)->exists());

        $res = $this->actingAs($admin, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertOk();
        $res->assertJsonPath('past_members.0.user.id', $ben->id)->assertJsonPath('past_members.0.reason', 'removed');
        // Past members are the admin's to see.
        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertJsonPath('past_members', []);
    }

    public function test_leaving_a_group_but_not_a_community_room_or_your_own_group(): void
    {
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin, ['members_count' => 2]);
        $this->join($ama, $room);
        $city = YardRoom::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->join($ama, $city);

        $this->actingAs($admin, 'sanctum')->postJson(route('api.v1.yard.info.leave', $room))->assertStatus(422)->assertJsonPath('code', 'admin');
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.leave', $city))->assertStatus(422)->assertJsonPath('code', 'system_room');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.leave', $room))->assertOk();
        $this->assertFalse(YardRoomMember::where('room_id', $room->id)->where('user_id', $ama->id)->exists());
        $this->assertSame(1, $room->fresh()->members_count);
        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertForbidden();
    }

    public function test_the_admin_answers_join_requests_for_their_own_room_only(): void
    {
        [$admin, $ama, $otherAdmin] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $room = $this->group($admin, ['is_private' => true]);
        $other = $this->group($otherAdmin, ['is_private' => true]);
        $request = YardJoinRequest::create(['room_id' => $room->id, 'user_id' => $ama->id, 'status' => 'pending']);

        $this->actingAs($admin, 'sanctum')->getJson(route('api.v1.yard.info.show', $room))->assertJsonPath('requests.0.id', $request->id);
        // Another room's admin can't answer it, even through their own room's URL.
        $this->actingAs($otherAdmin, 'sanctum')->postJson(route('api.v1.yard.info.requests.approve', [$other, $request->id]))->assertForbidden();
        $this->actingAs($otherAdmin, 'sanctum')->postJson(route('api.v1.yard.info.requests.approve', [$room, $request->id]))->assertForbidden();

        $this->actingAs($admin, 'sanctum')->postJson(route('api.v1.yard.info.requests.approve', [$room, $request->id]))->assertOk();
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertTrue(YardRoomMember::where('room_id', $room->id)->where('user_id', $ama->id)->exists());
    }

    public function test_the_group_photo_is_the_admins(): void
    {
        Storage::fake('public');
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);
        $photo = UploadedFile::fake()->image('g.jpg', 200, 200);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.avatar', $room), ['avatar' => $photo])->assertForbidden();

        $res = $this->actingAs($admin, 'sanctum')->postJson(route('api.v1.yard.info.avatar', $room), ['avatar' => $photo])->assertOk();
        $this->assertNotNull($room->fresh()->avatar);
        Storage::disk('public')->assertExists($room->fresh()->avatar);
        $this->assertStringStartsWith('http', $res->json('avatar_url'));

        $this->actingAs($admin, 'sanctum')->deleteJson(route('api.v1.yard.info.avatar.remove', $room))->assertOk();
        $this->assertNull($room->fresh()->avatar);
    }

    public function test_block_unblock_and_nickname_are_for_dms(): void
    {
        [$ama, $ben, $admin] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $this->connect($ama, $ben);
        $dm = $this->dm($ama, $ben);
        $group = $this->group($admin);
        $this->join($ama, $group);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.block', $group))->assertForbidden();
        $this->actingAs($ama, 'sanctum')->putJson(route('api.v1.yard.info.nickname', $group), ['nickname' => 'x'])->assertForbidden();

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.block', $dm))->assertOk();
        $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $dm))->assertJsonPath('partner.is_blocked_by_me', true);
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.unblock', $dm))->assertOk()->assertJsonPath('unblocked', true);

        $this->actingAs($ama, 'sanctum')->putJson(route('api.v1.yard.info.nickname', $dm), ['nickname' => '  Benny  '])
            ->assertOk()->assertJsonPath('nickname', 'Benny');
        $this->assertSame('Benny', UserContactName::where('owner_user_id', $ama->id)->value('nickname'));
        $this->actingAs($ama, 'sanctum')->putJson(route('api.v1.yard.info.nickname', $dm), ['nickname' => ''])
            ->assertOk()->assertJsonPath('nickname', null);
        $this->assertSame(0, UserContactName::where('owner_user_id', $ama->id)->count());
    }

    public function test_a_room_is_reported_once_while_the_report_is_open(): void
    {
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.report', $room), ['reason' => 'scam', 'details' => 'Asks for money'])
            ->assertOk()->assertJsonPath('reported', true);
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.report', $room), ['reason' => 'spam'])
            ->assertOk()->assertJsonPath('reported', false);
        $this->assertSame(1, Report::where('reportable_id', $room->id)->count());
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.info.report', $room), ['reason' => 'nonsense'])->assertStatus(422);
    }

    public function test_starring_a_selection_and_starring_it_again_clears_it(): void
    {
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);
        $a = $this->say($admin, $room, 'one');
        $b = $this->say($admin, $room, 'two');
        DB::table('yard_message_stars')->insert(['user_id' => $ama->id, 'message_id' => $a->id, 'created_at' => now()]);

        // Mixed: both end up starred.
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.star'), ['ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('starred', true);
        $this->assertSame(2, DB::table('yard_message_stars')->where('user_id', $ama->id)->count());

        $msgs = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.messages.index', $room))->assertOk();
        $this->assertTrue(collect($msgs->json('messages'))->firstWhere('id', $b->id)['starred']);
        $info = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.info.show', $room));
        $this->assertCount(2, $info->json('starred'));

        // All starred: cleared.
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.star'), ['ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('starred', false);
        $this->assertSame(0, DB::table('yard_message_stars')->where('user_id', $ama->id)->count());
    }

    public function test_nobody_stars_or_pins_a_message_they_cannot_see(): void
    {
        [$admin, $stranger] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $secret = $this->say($admin, $room, 'members only');

        $this->actingAs($stranger, 'sanctum')->postJson(route('api.v1.yard.messages.star'), ['ids' => [$secret->id]])
            ->assertOk()->assertJsonPath('ids', []);
        $this->assertSame(0, DB::table('yard_message_stars')->count());
        $this->actingAs($stranger, 'sanctum')->postJson(route('api.v1.yard.messages.pin', $secret))->assertForbidden();
        $this->assertFalse((bool) $secret->fresh()->is_pinned);
    }

    public function test_a_member_pins_and_unpins_for_the_room(): void
    {
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);
        $m = $this->say($admin, $room, 'Rules');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.pin', $m))->assertOk()->assertJsonPath('is_pinned', true);
        $this->assertTrue((bool) $m->fresh()->is_pinned);
        $this->actingAs($admin, 'sanctum')->getJson(route('api.v1.yard.rooms.show', $room))->assertJsonPath('room.pinned.0.id', $m->id);
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.pin', $m))->assertOk()->assertJsonPath('is_pinned', false);
    }

    public function test_forwarding_copies_into_a_room_of_yours_and_nothing_out_of_a_room_you_are_not_in(): void
    {
        [$admin, $ama, $ben] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $from = $this->group($admin);
        $this->join($ama, $from);
        $to = $this->group($admin, ['name' => 'Other']);
        $this->join($ama, $to);
        $secretRoom = $this->group($ben, ['name' => 'Secret']);
        $secret = $this->say($ben, $secretRoom, 'not for Ama');
        $a = $this->say($admin, $from, 'Pass this on');
        $voice = $this->say($admin, $from, '', ['message_type' => MessageType::Audio, 'content' => null, 'media_path' => 'yard/media/1/v.m4a', 'media_metadata' => ['duration' => 4.2]]);
        $poll = $this->say($admin, $from, 'Poll?', ['message_type' => MessageType::Poll]);

        $targets = $this->actingAs($ama, 'sanctum')->getJson(route('api.v1.yard.messages.forward-targets').'?except='.$from->id)->assertOk();
        $this->assertSame([$to->id], collect($targets->json('rooms'))->pluck('id')->all());

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.forward'), ['ids' => [$voice->id, $a->id, $poll->id, $secret->id], 'room_id' => $to->id])
            ->assertOk()->assertJsonPath('forwarded', 2);

        $copies = YardMessage::where('room_id', $to->id)->orderBy('id')->get();
        $this->assertSame(['Pass this on', null], $copies->pluck('content')->all(), 'oldest first, poll and the foreign message left out');
        $this->assertTrue((bool) $copies[0]->is_forwarded);
        $this->assertSame(4.2, $copies[1]->media_metadata['duration']);
        $this->assertSame($ama->id, $to->fresh()->last_message_user_id);

        // Into a room Ama isn't in: refused.
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.forward'), ['ids' => [$a->id], 'room_id' => $secretRoom->id])
            ->assertForbidden()->assertJsonPath('code', 'not_member');
        // Only the foreign message: nothing to forward.
        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.forward'), ['ids' => [$secret->id], 'room_id' => $to->id])
            ->assertStatus(422)->assertJsonPath('code', 'nothing');
    }

    public function test_forwarding_into_an_unconnected_dm_is_refused(): void
    {
        [$admin, $ama, $ben] = [$this->createUser(), $this->createUser(), $this->createUser()];
        $from = $this->group($admin);
        $this->join($ama, $from);
        $dm = $this->dm($ama, $ben);
        $a = $this->say($admin, $from, 'hello');

        $this->actingAs($ama, 'sanctum')->postJson(route('api.v1.yard.messages.forward'), ['ids' => [$a->id], 'room_id' => $dm->id])
            ->assertForbidden()->assertJsonPath('code', 'not_connected');
        $this->assertSame(0, YardMessage::where('room_id', $dm->id)->count());
    }

    public function test_a_voice_note_keeps_the_length_the_recorder_measured(): void
    {
        Storage::fake('public');
        [$admin, $ama] = [$this->createUser(), $this->createUser()];
        $room = $this->group($admin);
        $this->join($ama, $room);
        $file = UploadedFile::fake()->create('voice.m4a', 40, 'audio/mp4');

        $res = $this->actingAs($ama, 'sanctum')->post(route('api.v1.yard.messages.media', $room), ['type' => 'audio', 'file' => $file, 'duration' => 7.36], ['Accept' => 'application/json'])
            ->assertCreated();
        $res->assertJsonPath('message.type', 'audio')->assertJsonPath('message.media.duration', 7.4);
    }
}
