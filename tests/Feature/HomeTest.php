<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

class HomeTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_landing_page_loads(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_landing_page_contains_platform_name(): void
    {
        $response = $this->get('/');
        $response->assertSee('Cameroon Community');
    }

    public function test_coming_soon_modules_load(): void
    {
        $user = $this->createUser();

        $modules = ['marche', 'easygoparcell', 'roadfam', 'camevents', 'kamernest',
            'workconnect', 'kamereats', 'kamersos', 'camstories', 'kamerpulse', 'kamersend'];

        foreach ($modules as $module) {
            $response = $this->actingAs($user)->get("/{$module}");
            $response->assertStatus(200);
        }
    }
}
