<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BroadcastAuthController;
use App\Http\Controllers\Api\V1\ConfigurationController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\MarketController;
use App\Http\Controllers\Api\V1\MarketBuyController;
use App\Http\Controllers\Api\V1\Yard\MessageController;
use App\Http\Controllers\Api\V1\Yard\PeopleController;
use App\Http\Controllers\Api\V1\Yard\CommunityController;
use App\Http\Controllers\Api\V1\Yard\RoomController;
use App\Http\Controllers\Api\V1\Yard\RoomInfoController;
use App\Http\Controllers\Api\V1\Yard\MessageActionsController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\KamerController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController as ProfileApiController;
use App\Http\Controllers\Api\V1\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API
|--------------------------------------------------------------------------
|
| The surface the Flutter app talks to. Served under /api by the router, so
| everything here is reached at /api/v1/....
|
| Versioned from the first endpoint on purpose: once a build is on somebody's
| phone we cannot change a payload underneath it, and an old version of the app
| may be in use for months. A breaking change goes in /v2 and /v1 keeps working.
|
| Every path added here must also be added as a constant in the Flutter client's
| app_constants.dart — no URL is ever written inline in a repository, which is
| the rule the E-ride app keeps.
|
| Nothing here has a session. Tenancy and locale still apply because
| InitializeTenancy and SetUserLanguage are global middleware (see
| bootstrap/app.php), so the tenant is resolved and X-Localization is honoured
| exactly as on the web.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
     * Public. Both of these must work with nobody signed in: the app reads its
     * configuration at splash, and needs to be told it is out of date or that
     * the platform is down before it shows a login form at all.
     */
    Route::get('configuration', ConfigurationController::class)->name('configuration');

    // Tight limit: this is the password-guessing endpoint.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    // Register, the web's three-step wizard in one request (step 1's location
    // and step 2's live email check have their own endpoints below).
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1')
        ->name('auth.register');
    Route::get('auth/email-available', [AuthController::class, 'emailAvailable'])
        ->middleware('throttle:40,1')
        ->name('auth.email-available');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1')
        ->name('auth.forgot-password');

    // Register step 1: coordinates in, the region registration will save out.
    Route::post('location/resolve', [LocationController::class, 'resolve'])
        ->middleware('throttle:20,1')
        ->name('location.resolve');
    Route::get('location/country-members', [LocationController::class, 'countryMembers'])
        ->middleware('throttle:40,1')
        ->name('location.country-members');

    /*
     * Authenticated.
     *
     * `active` sits after `auth:sanctum` deliberately. It reads
     * $request->user(), and that only resolves through the token guard once
     * auth:sanctum has made sanctum the active guard — put it first and it
     * would ask the session guard, find nobody, and wave suspended accounts
     * straight through.
     */
    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1')
            ->name('auth.email.resend');

        Route::patch('me', [MeController::class, 'update'])->name('me.update');

        // The profile's Settings dialog (ProfileSettings, shared with the web's
        // ProfileController): the form, the save, the two photos.
        Route::get('me/settings', [ProfileApiController::class, 'show'])->name('me.settings');
        Route::put('me/profile', [ProfileApiController::class, 'update'])->name('me.profile');
        Route::post('me/avatar', [ProfileApiController::class, 'avatar'])->middleware('throttle:20,1,me-photo')->name('me.avatar');
        Route::delete('me/avatar', [ProfileApiController::class, 'removeAvatar'])->name('me.avatar.remove');
        Route::post('me/cover', [ProfileApiController::class, 'cover'])->middleware('throttle:20,1,me-photo')->name('me.cover');
        Route::delete('me/cover', [ProfileApiController::class, 'removeCover'])->name('me.cover.remove');

        // Kamer AI (the web's slide-over panel). A reply costs an OpenAI call,
        // so sending has a limit of its own.
        Route::get('kamer', [KamerController::class, 'show'])->name('kamer.show');
        Route::post('kamer/messages', [KamerController::class, 'send'])->middleware('throttle:20,1,kamer')->name('kamer.send');
        Route::delete('kamer', [KamerController::class, 'clear'])->name('kamer.clear');

        // The header bell (Livewire NotificationBell, through NotificationFeed).
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

        // Onboarding (web: /welcome). The app keeps the step; these record what
        // each step changes.
        Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('onboarding/kamer', [OnboardingController::class, 'kamer'])
            ->middleware('throttle:20,1')
            ->name('onboarding.kamer');
        Route::post('onboarding/rooms', [OnboardingController::class, 'rooms'])->name('onboarding.rooms');
        Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

        // Account deletion, which App Store review requires inside the app.
        // Body: delete_confirm = the password, or DELETE for a Google account.
        Route::delete('me', [AuthController::class, 'destroy'])
            ->middleware('throttle:5,1')
            ->name('me.destroy');

        // Home (feed.blade.php) and its sponsored stories.
        Route::get('home', HomeController::class)->name('home');
        Route::post('ads/{ad}/impression', [HomeController::class, 'impression'])->name('ads.impression');
        Route::post('ads/{ad}/click', [HomeController::class, 'click'])->name('ads.click');

        // GoMarket.
        Route::get('market/filters', [MarketController::class, 'filters'])->name('market.filters');
        Route::get('market/listings', [MarketController::class, 'index'])->name('market.index');
        Route::get('market/listings/{slug}', [MarketController::class, 'show'])->name('market.show');
        Route::post('market/listings/{listing}/favorite', [MarketController::class, 'favorite'])->name('market.favorite');
        Route::post('market/listings/{listing}/inquiry', [MarketController::class, 'inquire'])->name('market.inquiry');
        Route::post('market/listings/{listing}/report', [MarketController::class, 'report'])->name('market.report');
        Route::post('market/listings/{listing}/block-seller', [MarketController::class, 'blockSeller'])->name('market.block-seller');
        Route::get('market/favorites', [MarketController::class, 'favorites'])->name('market.favorites');
        Route::post('market/sellers/{user}/follow', [MarketController::class, 'follow'])->name('market.follow');

        // GoMarket, selling (ListingComposer, MyListings, ListingInsights,
        // SellerProfile). Photos go one per request, under their own limit.
        Route::get('market/sell/options', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'options'])->name('market.sell.options');
        Route::get('market/sell/places', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'places'])->middleware('throttle:30,1')->name('market.sell.places');
        Route::get('market/sell/places/reverse', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'reverse'])->middleware('throttle:30,1')->name('market.sell.reverse');
        Route::post('market/sell/listings', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'store'])->name('market.sell.store');
        Route::get('market/sell/listings/{listing}', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'show'])->name('market.sell.show');
        Route::patch('market/sell/listings/{listing}', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'autosave'])->name('market.sell.autosave');
        Route::post('market/sell/listings/{listing}/photos', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'addPhoto'])->middleware('throttle:60,1')->name('market.sell.photos.add');
        Route::delete('market/sell/listings/{listing}/photos/{media}', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'removePhoto'])->whereNumber('media')->name('market.sell.photos.remove');
        Route::post('market/sell/listings/{listing}/photos/order', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'orderPhotos'])->name('market.sell.photos.order');
        Route::post('market/sell/listings/{listing}/publish', [\App\Http\Controllers\Api\V1\MarketSellController::class, 'publish'])->name('market.sell.publish');

        Route::get('market/mine', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'index'])->name('market.mine.index');
        Route::post('market/mine/bulk', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'bulk'])->name('market.mine.bulk');
        Route::post('market/mine/{id}/pause', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'pause'])->whereNumber('id')->name('market.mine.pause');
        Route::post('market/mine/{id}/reactivate', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'reactivate'])->whereNumber('id')->name('market.mine.reactivate');
        Route::post('market/mine/{id}/bump', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'bump'])->whereNumber('id')->name('market.mine.bump');
        Route::delete('market/mine/{id}', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'destroy'])->whereNumber('id')->name('market.mine.destroy');
        Route::get('market/mine/{id}/sale', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'sale'])->whereNumber('id')->name('market.mine.sale');
        Route::post('market/mine/{id}/sold', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'sold'])->whereNumber('id')->name('market.mine.sold');
        Route::get('market/mine/{id}/insights', [\App\Http\Controllers\Api\V1\MarketMineController::class, 'insights'])->whereNumber('id')->name('market.mine.insights');

        Route::get('market/sellers/{username}', [\App\Http\Controllers\Api\V1\MarketSellerController::class, 'show'])->name('market.seller.show');
        Route::post('market/sellers/{user}/connection', [\App\Http\Controllers\Api\V1\MarketSellerController::class, 'connection'])->name('market.seller.connection');
        Route::post('market/sellers/{user}/message', [\App\Http\Controllers\Api\V1\MarketSellerController::class, 'message'])->name('market.seller.message');
    });

    /*
     * GoMarket, buying: Buy now and the checkout, Orders, reviews, saved
     * searches, the inbox and the drawer's badges (MarketBuyController).
     */
    Route::middleware(['auth:sanctum', 'active', 'throttle:120,1'])->prefix('market')->name('market.')->group(function () {
        Route::get('listings/{slug}/checkout', [MarketBuyController::class, 'checkout'])->name('checkout');
        Route::post('listings/{listing}/checkout', [MarketBuyController::class, 'start'])->name('checkout.start');
        Route::get('listings/{slug}/review', [MarketBuyController::class, 'review'])->name('review');
        Route::post('listings/{listing}/review', [MarketBuyController::class, 'saveReview'])->name('review.save');
        Route::get('orders', [MarketBuyController::class, 'orders'])->name('orders');
        Route::post('orders/{order}/proof', [MarketBuyController::class, 'proof'])->name('orders.proof');
        Route::post('orders/{order}/cancel', [MarketBuyController::class, 'cancel'])->name('orders.cancel');
        Route::post('orders/{order}/paid', [MarketBuyController::class, 'paid'])->name('orders.paid');
        Route::post('orders/{order}/release', [MarketBuyController::class, 'release'])->name('orders.release');
        Route::post('orders/{order}/dispute', [MarketBuyController::class, 'dispute'])->name('orders.dispute');
        Route::get('saved-searches', [MarketBuyController::class, 'savedSearches'])->name('saved');
        Route::post('saved-searches', [MarketBuyController::class, 'saveSearch'])->name('saved.store')->middleware('throttle:20,1');
        Route::post('saved-searches/seen', [MarketBuyController::class, 'seenAll'])->name('saved.seen-all');
        Route::patch('saved-searches/{id}', [MarketBuyController::class, 'updateSearch'])->whereNumber('id')->name('saved.update');
        Route::delete('saved-searches/{id}', [MarketBuyController::class, 'deleteSearch'])->whereNumber('id')->name('saved.destroy');
        Route::post('saved-searches/{id}/seen', [MarketBuyController::class, 'seenSearch'])->whereNumber('id')->name('saved.seen');
        Route::get('inbox', [MarketBuyController::class, 'inbox'])->name('inbox');
        Route::get('badges', [MarketBuyController::class, 'badges'])->name('badges');
    });

    /*
     * GoConnect. Its own, higher limit: a busy room costs a fetch and a
     * delivered-receipt per incoming message, plus typing, and sharing the
     * 120/min above would start refusing a member mid-conversation. The
     * expensive writes keep their own server-side throttles (typing: 1 per
     * 2s per room; AI is queued).
     */
    Route::middleware(['auth:sanctum', 'active', 'throttle:600,1'])->prefix('yard')->name('yard.')->group(function () {
        Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
        Route::post('rooms/{room}/join', [RoomController::class, 'join'])->name('rooms.join');
        Route::post('rooms/{room}/favorite', [RoomController::class, 'favorite'])->name('rooms.favorite');
        Route::post('rooms/{room}/archive', [RoomController::class, 'archive'])->name('rooms.archive');
        Route::post('rooms/{room}/read', [RoomController::class, 'read'])->name('rooms.read');
        Route::post('rooms/{room}/typing', [RoomController::class, 'typing'])->name('rooms.typing');

        Route::get('rooms/{room}/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('rooms/{room}/messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('rooms/{room}/messages', [MessageController::class, 'store'])->name('messages.store');
        Route::post('rooms/{room}/media', [MessageController::class, 'media'])->name('messages.media');
        Route::patch('messages/{message}', [MessageController::class, 'update'])->name('messages.update');
        Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
        Route::post('messages/{message}/reactions', [MessageController::class, 'react'])->name('messages.react');
        Route::post('messages/{message}/delivered', [MessageController::class, 'delivered'])->name('messages.delivered');

        // People: Connections sheet, new-chat search, preview card, DMs.
        Route::get('connections', [PeopleController::class, 'index'])->name('connections.index');
        Route::post('connections/{user}/{action}', [PeopleController::class, 'act'])
            ->whereNumber('user')
            ->whereIn('action', ['request', 'accept', 'decline', 'cancel', 'disconnect', 'block', 'unblock'])
            ->middleware('throttle:60,1')
            ->name('connections.act');
        Route::get('people/search', [PeopleController::class, 'search'])->name('people.search');
        Route::get('people/{identifier}', [PeopleController::class, 'show'])->name('people.show');
        Route::post('people/{user}/nickname', [PeopleController::class, 'nickname'])->whereNumber('user')->name('people.nickname');
        Route::post('dm', [PeopleController::class, 'dm'])->name('dm.create');

        // Communities: the member's groups, discover, join / ask / leave, create.
        Route::get('communities', [CommunityController::class, 'index'])->name('communities.index');
        Route::post('communities', [CommunityController::class, 'store'])->middleware('throttle:10,1')->name('communities.store');
        Route::post('communities/{room}/join', [CommunityController::class, 'join'])->whereNumber('room')->name('communities.join');
        Route::delete('communities/{room}/request', [CommunityController::class, 'cancelRequest'])->whereNumber('room')->name('communities.cancel-request');
        Route::post('communities/{room}/leave', [CommunityController::class, 'leave'])->whereNumber('room')->name('communities.leave');
    });

    /*
     * A chat's info panel (members, media, admin actions) and the selection
     * bar's star, pin and forward. Same limits as GoConnect above.
     */
    Route::middleware(['auth:sanctum', 'active', 'throttle:600,1'])->prefix('yard')->name('yard.')->group(function () {
        Route::get('rooms/{room}/info', [RoomInfoController::class, 'show'])->name('info.show');
        Route::get('rooms/{room}/members', [RoomInfoController::class, 'members'])->name('info.members');
        Route::get('rooms/{room}/member-candidates', [RoomInfoController::class, 'candidates'])->name('info.candidates');
        Route::post('rooms/{room}/members', [RoomInfoController::class, 'addMembers'])->name('info.members.add');
        Route::delete('rooms/{room}/members/{userId}', [RoomInfoController::class, 'removeMember'])->whereNumber('userId')->name('info.members.remove');
        Route::post('rooms/{room}/avatar', [RoomInfoController::class, 'setAvatar'])->name('info.avatar');
        Route::delete('rooms/{room}/avatar', [RoomInfoController::class, 'removeAvatar'])->name('info.avatar.remove');
        Route::post('rooms/{room}/requests/{requestId}/approve', [RoomInfoController::class, 'approve'])->whereNumber('requestId')->name('info.requests.approve');
        Route::post('rooms/{room}/requests/{requestId}/reject', [RoomInfoController::class, 'reject'])->whereNumber('requestId')->name('info.requests.reject');
        Route::post('rooms/{room}/leave', [RoomInfoController::class, 'leave'])->name('info.leave');
        Route::post('rooms/{room}/report', [RoomInfoController::class, 'report'])->name('info.report');
        Route::post('rooms/{room}/block', [RoomInfoController::class, 'block'])->name('info.block');
        Route::post('rooms/{room}/unblock', [RoomInfoController::class, 'unblock'])->name('info.unblock');
        Route::put('rooms/{room}/nickname', [RoomInfoController::class, 'nickname'])->name('info.nickname');

        Route::post('messages/star', [MessageActionsController::class, 'star'])->name('messages.star');
        Route::post('messages/forward', [MessageActionsController::class, 'forward'])->name('messages.forward');
        Route::get('forward-targets', [MessageActionsController::class, 'targets'])->name('messages.forward-targets');
        Route::post('messages/{message}/pin', [MessageActionsController::class, 'pin'])->name('messages.pin');
    });

    // Private realtime channels for a token-carrying app (routes/channels.php rules).
    Route::post('broadcasting/auth', BroadcastAuthController::class)
        ->middleware(['auth:sanctum', 'active'])
        ->name('broadcasting.auth');
});
