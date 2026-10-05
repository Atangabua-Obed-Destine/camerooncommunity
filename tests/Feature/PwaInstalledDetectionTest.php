<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * Trying to install an app that is already installed should say so.
 *
 * A browser tab cannot tell on its own: display-mode reports whether the
 * current window is the installed app, so somebody browsing in Chrome with the
 * app on their home screen looks exactly like somebody who has never installed
 * it. getInstalledRelatedApps() can answer, but only when the manifest claims
 * the app as its own related application.
 */
class PwaInstalledDetectionTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_the_manifest_claims_this_app_as_a_related_application(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

        $this->assertArrayHasKey('related_applications', $manifest);
        $this->assertSame('webapp', $manifest['related_applications'][0]['platform']);
        $this->assertStringEndsWith(
            'manifest.webmanifest',
            $manifest['related_applications'][0]['url'],
            'getInstalledRelatedApps() matches on the manifest URL.'
        );
    }

    public function test_it_does_not_prefer_a_native_app(): void
    {
        // With this true, browsers suppress the install prompt entirely and
        // point people at a store listing that does not exist.
        $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

        $this->assertFalse($manifest['prefer_related_applications']);
    }

    public function test_the_landing_page_carries_the_already_installed_notice(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('alreadyInstalled', $html);
        $this->assertStringContainsString('getInstalledRelatedApps', $html);
    }

    public function test_the_install_listener_is_registered_before_the_installed_check(): void
    {
        // The listener used to sit after an early return, so on a device that
        // already had the app nothing was listening and the button did nothing.
        $html = $this->get('/')->assertOk()->getContent();

        $listener = strpos($html, "addEventListener('pwa-open-install'");
        $earlyReturn = strpos($html, 'if (this.installed) return;');

        $this->assertNotFalse($listener);
        $this->assertNotFalse($earlyReturn);
        $this->assertLessThan($earlyReturn, $listener);
    }
}
