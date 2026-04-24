<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Yard\YardController;
use Illuminate\Support\Facades\Route;

// ─── Public Routes ───
Route::get('/', [HomeController::class, 'index'])->name('home');

// ─── Language Toggle API ───
Route::post('/api/language', [LanguageController::class, 'update'])->name('language.update');

// ─── Auth Routes (guests only) ───
Route::middleware('guest')->group(function () {
    Route::get('/register', \App\Livewire\Auth\RegisterWizard::class)->name('register');
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

// ─── Auth Routes (authenticated) ───
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Email verification
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
});

// ─── Authenticated App Routes ───
Route::middleware(['auth', 'verified', 'location'])->group(function () {
    // Onboarding (NOT behind 'onboarded' middleware — this IS the onboarding)
    Route::get('/welcome', \App\Livewire\Onboarding\OnboardingFlow::class)->name('onboarding');
    Route::post('/onboarding/complete', [\App\Http\Controllers\Auth\OnboardingController::class, 'complete'])->name('onboarding.complete');
});

Route::middleware(['auth', 'verified', 'location', 'onboarded'])->group(function () {

    // The Yard
    Route::get('/yard', [YardController::class, 'index'])->name('yard');
    Route::get('/yard/room/{room:slug}', [YardController::class, 'room'])->name('yard.room');
    Route::post('/yard/room/{room}/join', [YardController::class, 'joinRoom'])->name('yard.room.join');
    Route::post('/yard/dm', [YardController::class, 'createDm'])->name('yard.dm.create');
    Route::post('/yard/group', [YardController::class, 'createGroup'])->name('yard.group.create');
    Route::get('/yard/users/search', [YardController::class, 'searchUsers'])->name('yard.users.search');

    // TURN credentials (proxies Metered API — keeps secret server-side)
    Route::get('/api/turn-credentials', function () {
        $domain = config('services.metered.domain');
        $key = config('services.metered.secret_key');

        if (! $domain || ! $key) {
            return response()->json([
                ['urls' => 'stun:stun.l.google.com:19302'],
            ]);
        }

        $response = \Illuminate\Support\Facades\Http::get(
            "https://{$domain}/api/v1/turn/credentials",
            ['apiKey' => $key]
        );

        if ($response->successful()) {
            return $response->json();
        }

        return response()->json([
            ['urls' => 'stun:stun.l.google.com:19302'],
        ]);
    })->name('api.turn-credentials');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [\App\Http\Controllers\ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');

    // ── Sponsored Ads (user-facing) ──
    Route::get('/ads/yard', function () {
        $ads = \App\Models\SponsoredAd::active()
            ->forPlacement('yard_sidebar')
            ->orderByDesc('priority')
            ->limit(10)
            ->get()
            ->map(function ($ad) {
                $ad->recordImpression();
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'description' => $ad->description,
                    'image' => $ad->imageUrl(),
                    'video' => $ad->youtubeEmbedUrl(),
                    'advertiser' => $ad->advertiser_name,
                    'cta' => $ad->link_label,
                ];
            });
        return response()->json($ads);
    })->name('ads.yard');

    Route::get('/ad/{ad}/click', function (\App\Models\SponsoredAd $ad) {
        $ad->recordClick();
        if ($ad->link_url) {
            return redirect()->away($ad->link_url);
        }
        return redirect()->route('yard');
    })->name('ads.click');

    // Solidarity — handled via Livewire components within Yard

    // Coming Soon modules
    $comingSoonModules = [
        'marche' => 'Marché',
        'easygoparcell' => 'EasyGoParcel',
        'roadfam' => 'RoadFam',
        'camevents' => 'CamEvents',
        'kamernest' => 'KamerNest',
        'workconnect' => 'WorkConnect',
        'kamereats' => 'KamerEats',
        'kamersos' => 'KamerSOS',
        'camstories' => 'CamStories',
        'kamerpulse' => 'KamerPulse',
        'kamersend' => 'KamerSend',
    ];

    foreach ($comingSoonModules as $slug => $name) {
        Route::get("/{$slug}", fn () => view('modules.coming-soon', ['moduleName' => $name, 'moduleSlug' => $slug]))
            ->name("module.{$slug}");
    }
});

// ─── Admin Panel Routes ───
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'role:super_admin|admin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users'])->name('users');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\AdminController::class, 'showUser'])->name('users.show');
    Route::post('/users/{user}/toggle-admin', [\App\Http\Controllers\Admin\AdminController::class, 'toggleAdmin'])->name('users.toggle-admin');
    Route::get('/yard', [\App\Http\Controllers\Admin\AdminController::class, 'yard'])->name('yard');
    Route::get('/solidarity', [\App\Http\Controllers\Admin\AdminController::class, 'solidarity'])->name('solidarity');
    Route::post('/solidarity/{campaign}/approve', [\App\Http\Controllers\Admin\AdminController::class, 'approveCampaign'])->name('solidarity.approve');
    Route::post('/solidarity/{campaign}/reject', [\App\Http\Controllers\Admin\AdminController::class, 'rejectCampaign'])->name('solidarity.reject');
    Route::get('/moderation', [\App\Http\Controllers\Admin\AdminController::class, 'moderation'])->name('moderation');
    Route::get('/reports', [\App\Http\Controllers\Admin\AdminController::class, 'reports'])->name('reports');
    Route::get('/cms', [\App\Http\Controllers\Admin\AdminController::class, 'cms'])->name('cms');
    Route::get('/settings', [\App\Http\Controllers\Admin\AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [\App\Http\Controllers\Admin\AdminController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/branding', [\App\Http\Controllers\Admin\AdminController::class, 'updateBranding'])->name('settings.branding');
    Route::get('/tenants', [\App\Http\Controllers\Admin\AdminController::class, 'tenants'])->name('tenants');
    Route::get('/ai', [\App\Http\Controllers\Admin\AdminController::class, 'ai'])->name('ai');
    Route::post('/ai', [\App\Http\Controllers\Admin\AdminController::class, 'updateAiSettings'])->name('ai.update');
    Route::post('/moderation/{message}/dismiss', [\App\Http\Controllers\Admin\AdminController::class, 'dismissFlag'])->name('moderation.dismiss');
    Route::post('/moderation/{message}/delete', [\App\Http\Controllers\Admin\AdminController::class, 'deleteMessage'])->name('moderation.delete');
    Route::get('/audit', [\App\Http\Controllers\Admin\AdminController::class, 'audit'])->name('audit');
    Route::get('/analytics', [\App\Http\Controllers\Admin\AdminController::class, 'analytics'])->name('analytics');

    // Sponsored Ads
    Route::get('/sponsored-ads', [\App\Http\Controllers\Admin\AdminController::class, 'sponsoredAds'])->name('sponsored-ads');
    Route::get('/sponsored-ads/create', [\App\Http\Controllers\Admin\AdminController::class, 'createAd'])->name('sponsored-ads.create');
    Route::post('/sponsored-ads', [\App\Http\Controllers\Admin\AdminController::class, 'storeAd'])->name('sponsored-ads.store');
    Route::get('/sponsored-ads/{ad}/edit', [\App\Http\Controllers\Admin\AdminController::class, 'editAd'])->name('sponsored-ads.edit');
    Route::put('/sponsored-ads/{ad}', [\App\Http\Controllers\Admin\AdminController::class, 'updateAd'])->name('sponsored-ads.update');
    Route::post('/sponsored-ads/{ad}/toggle', [\App\Http\Controllers\Admin\AdminController::class, 'toggleAdStatus'])->name('sponsored-ads.toggle');
    Route::delete('/sponsored-ads/{ad}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteAd'])->name('sponsored-ads.delete');
});
