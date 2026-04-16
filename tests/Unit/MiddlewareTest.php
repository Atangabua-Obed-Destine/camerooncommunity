<?php

namespace Tests\Unit;

use App\Http\Middleware\SetUserLanguage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_set_user_language_defaults_to_en(): void
    {
        $middleware = new SetUserLanguage();
        $request = Request::create('/');

        $middleware->handle($request, fn () => new Response());

        $this->assertEquals('en', App::getLocale());
    }

    public function test_set_user_language_respects_user_preference(): void
    {
        $user = $this->createUser(['language_pref' => 'fr']);
        $middleware = new SetUserLanguage();

        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);

        $middleware->handle($request, fn () => new Response());

        $this->assertEquals('fr', App::getLocale());
    }

    public function test_ensure_user_active_allows_active_user(): void
    {
        $user = $this->createUser(['is_active' => true]);

        $response = $this->actingAs($user)->get('/yard');
        $response->assertStatus(200);
    }

    public function test_ensure_user_active_blocks_inactive_user(): void
    {
        $user = $this->createUser(['is_active' => false]);

        $response = $this->actingAs($user)->get('/yard');
        $response->assertRedirect(route('login'));
    }

    public function test_initialize_tenancy_sets_tenant(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $this->assertNotNull(app('currentTenant'));
    }
}
