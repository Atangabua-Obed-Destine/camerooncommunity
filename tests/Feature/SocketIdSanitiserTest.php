<?php

namespace Tests\Feature;

use App\Models\YardRoom;
use App\Models\YardRoomMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class SocketIdSanitiserTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    private function request(?string $socketId): \Illuminate\Http\Request
    {
        $request = \Illuminate\Http\Request::create('/yard', 'GET');

        if ($socketId !== null) {
            $request->headers->set('X-Socket-ID', $socketId);
        }

        app(\App\Http\Middleware\SanitizeSocketId::class)->handle($request, fn ($r) => response('ok'));

        return $request;
    }

    public function test_a_bogus_socket_id_is_removed(): void
    {
        // This is what the browser sends before the websocket has connected,
        // and it made every toOthers() call throw instead of publishing.
        foreach (['undefined', 'null', '', 'not-an-id', '12345'] as $bad) {
            $this->assertNull(
                $this->request($bad)->header('X-Socket-ID'),
                "'{$bad}' should not survive as a socket id"
            );
        }
    }

    public function test_a_real_socket_id_is_left_alone(): void
    {
        $this->assertSame('123456.7891011', $this->request('123456.7891011')->header('X-Socket-ID'));
    }

    public function test_broadcasting_still_excludes_the_sender_when_the_id_is_real(): void
    {
        $this->assertSame(
            '123456.7891011',
            Broadcast::socket($this->request('123456.7891011'))
        );

        // …and excludes nobody when it was junk, rather than failing.
        $this->assertNull(Broadcast::socket($this->request('undefined')));
    }
}
