<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RemoteAvatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class RemoteAvatarTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        Storage::fake('public');
    }

    public function test_a_remote_picture_is_copied_onto_our_own_disk(): void
    {
        Http::fake(['lh3.googleusercontent.com/*' => Http::response('binary-jpeg', 200, ['Content-Type' => 'image/jpeg'])]);

        $path = RemoteAvatar::fetch('https://lh3.googleusercontent.com/a/ABC123');

        // Views build asset('storage/' . $avatar), so this must be a path.
        $this->assertNotNull($path);
        $this->assertStringStartsWith('avatars/google-', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_non_images_and_failures_produce_no_avatar(): void
    {
        Http::fake([
            'example.com/html' => Http::response('<html>', 200, ['Content-Type' => 'text/html']),
            'example.com/gone' => Http::response('', 404),
        ]);

        $this->assertNull(RemoteAvatar::fetch('https://example.com/html'));
        $this->assertNull(RemoteAvatar::fetch('https://example.com/gone'));
        // A path that is already local must not be re-fetched.
        $this->assertNull(RemoteAvatar::fetch('avatars/existing.jpg'));
    }

    public function test_the_repair_command_rewrites_existing_urls(): void
    {
        Http::fake([
            'lh3.googleusercontent.com/*' => Http::response('binary-png', 200, ['Content-Type' => 'image/png']),
            'dead.example.com/*'          => Http::response('', 500),
        ]);

        $good = $this->createUser(['username' => 'googler', 'avatar' => 'https://lh3.googleusercontent.com/a/XYZ']);
        $dead = $this->createUser(['username' => 'deadpic', 'avatar' => 'https://dead.example.com/x.jpg']);
        $kept = $this->createUser(['username' => 'uploader', 'avatar' => 'avatars/mine.jpg']);

        $this->artisan('users:localise-avatars')->assertSuccessful();

        $this->assertStringStartsWith('avatars/google-', $good->fresh()->avatar);
        // Unreachable beats broken: fall back to the coloured initial.
        $this->assertNull($dead->fresh()->avatar);
        $this->assertSame('avatars/mine.jpg', $kept->fresh()->avatar);
    }
}
