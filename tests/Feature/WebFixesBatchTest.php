<?php

namespace Tests\Feature;

use App\Livewire\Auth\RegisterWizard;
use App\Models\PlatformSetting;
use App\Services\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SetupTenancy;

/**
 * The web fixes found while writing the app's UI-parity spec (passes 6 and 8):
 * sign-in hardening, apostrophes in names, the logo on white, Google photos,
 * and French for what the server says.
 */
class WebFixesBatchTest extends TestCase
{
    use RefreshDatabase, SetupTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    public function test_password_guessing_is_throttled_and_says_so_on_the_form(): void
    {
        $this->createUser(['email' => 'ama@example.com']);

        foreach (range(1, 10) as $attempt) {
            $this->from('/login')->post('/login', ['email' => 'ama@example.com', 'password' => 'wrong']);
        }

        $this->from('/login')->post('/login', ['email' => 'ama@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 1 minute(s).']);
    }

    public function test_reset_asks_for_the_same_password_as_registration(): void
    {
        $user = $this->createUser(['email' => 'ama@example.com']);
        $token = Password::createToken($user);

        // Reset used to take any eight characters.
        $this->from('/reset-password/' . $token)->post('/reset-password', [
            'token' => $token, 'email' => 'ama@example.com',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'ama@example.com',
            'password' => 'Kamer2026', 'password_confirmation' => 'Kamer2026',
        ])->assertRedirect(route('login'));
    }

    public function test_a_link_to_a_later_register_step_starts_where_the_fields_are_missing(): void
    {
        // Step 3 used to open with empty account fields and fail with no message.
        Livewire::withQueryParams(['step' => 3])->test(RegisterWizard::class)->assertSet('step', 1);
    }

    public function test_a_name_with_an_apostrophe_still_greets_the_member(): void
    {
        $member = $this->createUser(['name' => "N'Golo Kanté", 'onboarded_at' => now(), 'current_country' => 'Cameroon']);

        // '{{ }}' inside a JS string ended the string at the apostrophe: an empty heading.
        $this->actingAs($member)->get('/')
            ->assertOk()
            ->assertSee("\$store.lang.t('Welcome back, N\\u0027Golo!'", false);
    }

    public function test_the_logo_gets_a_navy_wordmark_copy_for_white_backgrounds(): void
    {
        Storage::fake('public');
        $logo = imagecreatetruecolor(4, 2);
        imagesavealpha($logo, true);
        imagealphablending($logo, false);
        imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagesetpixel($logo, 0, 0, imagecolorallocate($logo, 255, 255, 255)); // "Cameroon"
        imagesetpixel($logo, 1, 0, imagecolorallocate($logo, 0xF0, 0xC6, 0x10)); // "Network"
        ob_start();
        imagepng($logo);
        Storage::disk('public')->put('branding/logo.png', ob_get_clean());
        PlatformSetting::setValue('site_logo', 'branding/logo.png', 'branding');
        Cache::flush();

        $this->assertStringEndsWith('/storage/branding/logo-on-light.png', SiteSettings::logoOnLightUrl());

        $copy = imagecreatefromstring(Storage::disk('public')->get('branding/logo-on-light.png'));
        $rgb = fn ($x) => imagecolorat($copy, $x, 0) & 0xFFFFFF;
        $this->assertSame(0x243A5C, $rgb(0), 'white turns navy');
        $this->assertSame(0xF0C610, $rgb(1), 'yellow stays yellow');
    }

    public function test_an_older_google_photo_shows_instead_of_a_broken_image(): void
    {
        $viewer = $this->createUser(['onboarded_at' => now(), 'current_country' => 'Cameroon']);
        $this->createUser(['username' => 'googler', 'avatar' => 'https://lh3.googleusercontent.com/a/photo']);

        $this->actingAs($viewer)->get(route('people', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('src="https://lh3.googleusercontent.com/a/photo"', false)
            ->assertDontSee('storage/https://', false);
    }

    public function test_everything_the_server_says_has_a_french_version(): void
    {
        $french = json_decode(file_get_contents(lang_path('fr.json')), true, flags: JSON_THROW_ON_ERROR);

        $missing = [];
        $files = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())), '/\.php$/');
        $views = new \RegexIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))), '/\.php$/');
        foreach ([$files, $views] as $tree) {
            foreach ($tree as $file) {
                preg_match_all('/__\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $text) {
                    $text = stripslashes($text);
                    if (! preg_match('/^[a-z_]+(\.[a-z0-9_]+)+$/', $text) && ! isset($french[$text])) {
                        $missing[] = $text;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these to lang/fr.json');

        app()->setLocale('fr');
        $this->assertSame('Ces identifiants ne correspondent à aucun compte.', __('auth.failed'));
    }
}
