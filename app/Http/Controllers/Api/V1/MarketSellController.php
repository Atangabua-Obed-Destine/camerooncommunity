<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\MarketSellPresenter;
use App\Models\MarketplaceListing;
use App\Services\Market\ListingComposerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * The sell composer for the app (the web's ListingComposer): a draft is made
 * as soon as there is a photo or something worth keeping, photos are added
 * one per request, and publishing checks everything the web checks, through
 * the same ListingComposerService.
 */
class MarketSellController extends Controller
{
    public function __construct(
        private ListingComposerService $composer,
        private MarketSellPresenter $present,
    ) {}

    /** GET /market/sell/options: the composer's choices and the member's starting place. */
    public function options(Request $request): JsonResponse
    {
        return response()->json($this->present->options() + ['blank' => $this->present->blank($request->user())]);
    }

    /** POST /market/sell/listings: start a draft with what has been typed. */
    public function store(Request $request): JsonResponse
    {
        $fields = $this->draftFields($request);
        $listing = $this->composer->createDraft($request->user(), $fields);
        if (isset($fields['attributes'])) {
            $this->composer->autosave($listing, ['attributes' => $fields['attributes'], 'category_id' => $fields['category_id'] ?? null]);
        }

        return response()->json(['listing' => $this->present->editable($listing->fresh())], 201);
    }

    /** GET /market/sell/listings/{listing}: one of the member's, for editing. */
    public function show(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }

        return response()->json(['listing' => $this->present->editable($listing)]);
    }

    /** PATCH /market/sell/listings/{listing}: keep a draft's progress (drafts only). */
    public function autosave(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }
        $saved = $this->composer->autosave($listing, $this->draftFields($request));

        return response()->json(['saved' => $saved, 'saved_at' => $saved ? now()->toIso8601String() : null]);
    }

    /** POST /market/sell/listings/{listing}/photos {photo}: one photo, optimised. */
    public function addPhoto(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }
        $request->validate(['photo' => 'required|'.ListingComposerService::PHOTO_RULE], [
            'photo.max' => __('This photo is over the 8MB limit. Pick a smaller one or compress it.'),
            'photo.mimes' => __('That file is not an image. Choose a JPG, PNG or WEBP.'),
        ]);

        if ($this->composer->addPhotos($listing, [$request->file('photo')]) === 0) {
            return response()->json([
                'message' => __('You can add up to :n photos.', ['n' => $this->composer->maxImages()]),
                'media' => $this->composer->mediaItems($listing),
            ], 422);
        }

        return response()->json(['media' => $this->composer->mediaItems($listing)], 201);
    }

    /** DELETE /market/sell/listings/{listing}/photos/{media} */
    public function removePhoto(Request $request, MarketplaceListing $listing, int $media): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }
        $this->composer->removePhoto($listing, $media);

        return response()->json(['media' => $this->composer->mediaItems($listing)]);
    }

    /** POST /market/sell/listings/{listing}/photos/order {ids}: the first is the cover. */
    public function orderPhotos(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }
        $ids = $request->validate(['ids' => 'required|array|max:50', 'ids.*' => 'integer'])['ids'];
        $this->composer->reorder($listing, $ids);

        return response()->json(['media' => $this->composer->mediaItems($listing)]);
    }

    /** POST /market/sell/listings/{listing}/publish: publish a draft or save an edit. */
    public function publish(Request $request, MarketplaceListing $listing): JsonResponse
    {
        if (! $this->owns($request, $listing)) {
            return $this->notFound();
        }

        $categoryId = $request->integer('category_id') ?: null;
        $data = $request->validate($this->composer->rules($categoryId) + [
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:20',
            'attributes' => 'nullable|array',
        ], [], [
            'category_id' => __('category'),
            'price_type' => __('price type'),
            'location_label' => __('location'),
            'fulfillment' => __('delivery method'),
        ]);

        // Facebook requires at least one photo to publish.
        if (! $listing->media()->exists()) {
            return response()->json([
                'message' => __('Add at least one photo before publishing.'),
                'errors' => ['photos' => [__('Add at least one photo before publishing.')]],
            ], 422);
        }

        $wasEdit = $listing->status !== ListingStatus::Draft;
        $status = $this->composer->publish($listing, $data);

        return response()->json([
            'slug' => $listing->slug,
            'status' => $status->value,
            'message' => $this->composer->publishedMessage($status, $wasEdit),
        ]);
    }

    /**
     * GET /market/sell/places?q=: the location search (the web asks Nominatim
     * from the browser; here the server asks, as one identified client).
     */
    public function places(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => 'required|string|min:3|max:120'])['q']);

        $rows = $this->nominatim('search', ['q' => $q, 'limit' => 5]);

        return response()->json(['places' => collect(is_array($rows) ? $rows : [])
            ->map(fn ($r) => $this->place($r))->filter()->values()]);
    }

    /** GET /market/sell/places/reverse?lat&lng: "Current location" and the map pin. */
    public function reverse(Request $request): JsonResponse
    {
        $d = $request->validate(['lat' => 'required|numeric|between:-90,90', 'lng' => 'required|numeric|between:-180,180']);
        $row = $this->nominatim('reverse', ['lat' => $d['lat'], 'lon' => $d['lng']]);
        $place = is_array($row) ? $this->place($row + ['lat' => $d['lat'], 'lon' => $d['lng']]) : null;

        return response()->json(['place' => $place ?? [
            'lat' => (float) $d['lat'], 'lng' => (float) $d['lng'],
            'label' => app()->getLocale() === 'fr' ? 'Position actuelle' : 'Current Location',
            'country' => null, 'region' => '', 'city' => '', 'neighborhood' => '',
        ]]);
    }

    private function nominatim(string $endpoint, array $params): mixed
    {
        try {
            $r = Http::timeout(8)
                ->withUserAgent('CameroonNetwork/1.0 (+https://cameroonnetwork.org)')
                ->get('https://nominatim.openstreetmap.org/'.$endpoint, $params + [
                    'format' => 'json',
                    'addressdetails' => 1,
                    'accept-language' => app()->getLocale(),
                ]);

            return $r->successful() ? $r->json() : null;
        } catch (\Throwable $e) {
            logger()->warning('Nominatim '.$endpoint.' failed: '.$e->getMessage());

            return null;
        }
    }

    /** A Nominatim row as the composer's location (setLocationData on the web). */
    private function place(array $r): ?array
    {
        if (! isset($r['lat'], $r['lon'])) {
            return null;
        }
        $a = (array) ($r['address'] ?? []);
        // Some OpenStreetMap names were typed with HTML entities in them
        // ("d&apos;AKWA"): shown as the name was meant.
        $text = fn ($v) => html_entity_decode((string) ($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lon'],
            'label' => $text($r['display_name'] ?? ''),
            'country' => isset($a['country_code']) ? strtoupper((string) $a['country_code']) : null,
            'region' => $text($a['state'] ?? $a['region'] ?? ''),
            'city' => $text($a['city'] ?? $a['town'] ?? $a['village'] ?? ''),
            'neighborhood' => $text($a['suburb'] ?? $a['neighbourhood'] ?? ''),
        ];
    }

    /** What a draft may hold, loosely checked: publishing checks it all. */
    private function draftFields(Request $request): array
    {
        return array_filter($request->validate([
            'category_id' => 'nullable|integer|exists:marketplace_categories,id',
            'title' => 'nullable|string|max:180',
            'description' => 'nullable|string|max:5000',
            'price_type' => 'nullable|in:fixed,negotiable,free,contact',
            'price' => 'nullable|numeric|min:0|max:1000000000',
            'currency' => 'nullable|string|size:3',
            'condition' => 'nullable|in:new,like_new,good,fair,for_parts',
            'quantity' => 'nullable|integer|min:1|max:9999',
            'fulfillment' => 'nullable|array',
            'fulfillment.*' => 'in:pickup,local_delivery,diaspora_shippable,digital',
            'country' => 'nullable|string|size:2',
            'region' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:120',
            'neighborhood' => 'nullable|string|max:160',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'visibility' => 'nullable|in:public,connections,group',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:20',
            'attributes' => 'nullable|array',
        ]), fn ($v) => $v !== null);
    }

    private function owns(Request $request, MarketplaceListing $listing): bool
    {
        return $listing->isOwnedBy($request->user()->id);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('Not found.')], 404);
    }
}
