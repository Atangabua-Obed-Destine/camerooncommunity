<?php

namespace App\Services\Market;

use App\Enums\ListingStatus;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceListingMedia;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\CategoryAttributeSchema;
use App\Support\MarketplaceImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Creating and editing a GoMarket listing: the draft, its photos and the
 * publish. Shared by the web's ListingComposer and the app's sell API, so a
 * listing made on either is the same listing.
 *
 * Field names are the database's (category_id, price_type, ...). The
 * Livewire component maps its camelCase properties onto them.
 */
class ListingComposerService
{
    /** jpg/png/webp up to 8 MB, as the composer tells the member. */
    public const PHOTO_RULE = 'file|mimes:jpg,jpeg,png,webp|max:8192';

    /** The currency picker, XAF and the diaspora's first. */
    public const CURRENCIES = [
        ['code' => 'XAF', 'name' => 'CFA Franc BEAC'],
        ['code' => 'USD', 'name' => 'United States Dollar'],
        ['code' => 'EUR', 'name' => 'Euro'],
        ['code' => 'GBP', 'name' => 'British Pound'],
        ['code' => 'CAD', 'name' => 'Canadian Dollar'],
        ['code' => 'AED', 'name' => 'United Arab Emirates Dirham'],
        ['code' => 'AFN', 'name' => 'Afghan Afghani'],
        ['code' => 'ALL', 'name' => 'Albanian Lek'],
        ['code' => 'AMD', 'name' => 'Armenian Dram'],
        ['code' => 'ANG', 'name' => 'Netherlands Antillean Guilder'],
        ['code' => 'AOA', 'name' => 'Angolan Kwanza'],
        ['code' => 'ARS', 'name' => 'Argentine Peso'],
        ['code' => 'AUD', 'name' => 'Australian Dollar'],
        ['code' => 'AWG', 'name' => 'Aruban Florin'],
        ['code' => 'AZN', 'name' => 'Azerbaijani Manat'],
        ['code' => 'BAM', 'name' => 'Bosnia-Herzegovina Convertible Mark'],
        ['code' => 'BBD', 'name' => 'Barbadian Dollar'],
        ['code' => 'BDT', 'name' => 'Bangladeshi Taka'],
        ['code' => 'BGN', 'name' => 'Bulgarian Lev'],
        ['code' => 'BHD', 'name' => 'Bahraini Dinar'],
        ['code' => 'BIF', 'name' => 'Burundian Franc'],
        ['code' => 'BMD', 'name' => 'Bermudan Dollar'],
        ['code' => 'BND', 'name' => 'Brunei Dollar'],
        ['code' => 'BOB', 'name' => 'Bolivian Boliviano'],
        ['code' => 'BRL', 'name' => 'Brazilian Real'],
        ['code' => 'BSD', 'name' => 'Bahamian Dollar'],
        ['code' => 'BTN', 'name' => 'Bhutanese Ngultrum'],
        ['code' => 'BWP', 'name' => 'Botswanan Pula'],
        ['code' => 'BYN', 'name' => 'Belarusian Ruble'],
        ['code' => 'BZD', 'name' => 'Belize Dollar'],
        ['code' => 'CDF', 'name' => 'Congolese Franc'],
        ['code' => 'CHF', 'name' => 'Swiss Franc'],
        ['code' => 'CLP', 'name' => 'Chilean Peso'],
        ['code' => 'CNY', 'name' => 'Chinese Yuan'],
        ['code' => 'COP', 'name' => 'Colombian Peso'],
        ['code' => 'CRC', 'name' => 'Costa Rican Colón'],
        ['code' => 'CUP', 'name' => 'Cuban Peso'],
        ['code' => 'CVE', 'name' => 'Cape Verdean Escudo'],
        ['code' => 'CZK', 'name' => 'Czech Republic Koruna'],
        ['code' => 'DJF', 'name' => 'Djiboutian Franc'],
        ['code' => 'DKK', 'name' => 'Danish Krone'],
        ['code' => 'DOP', 'name' => 'Dominican Peso'],
        ['code' => 'DZD', 'name' => 'Algerian Dinar'],
        ['code' => 'EGP', 'name' => 'Egyptian Pound'],
        ['code' => 'ERN', 'name' => 'Eritrean Nakfa'],
        ['code' => 'ETB', 'name' => 'Ethiopian Birr'],
        ['code' => 'FJD', 'name' => 'Fijian Dollar'],
        ['code' => 'FKP', 'name' => 'Falkland Islands Pound'],
        ['code' => 'GEL', 'name' => 'Georgian Lari'],
        ['code' => 'GHS', 'name' => 'Ghanaian Cedi'],
        ['code' => 'GIP', 'name' => 'Gibraltar Pound'],
        ['code' => 'GMD', 'name' => 'Gambian Dalasi'],
        ['code' => 'GNF', 'name' => 'Guinean Franc'],
        ['code' => 'GTQ', 'name' => 'Guatemalan Quetzal'],
        ['code' => 'GYD', 'name' => 'Guyanaese Dollar'],
        ['code' => 'HKD', 'name' => 'Hong Kong Dollar'],
        ['code' => 'HNL', 'name' => 'Honduran Lempira'],
        ['code' => 'HRK', 'name' => 'Croatian Kuna'],
        ['code' => 'HTG', 'name' => 'Haitian Gourde'],
        ['code' => 'HUF', 'name' => 'Hungarian Forint'],
        ['code' => 'IDR', 'name' => 'Indonesian Rupiah'],
        ['code' => 'ILS', 'name' => 'Israeli New Sheqel'],
        ['code' => 'INR', 'name' => 'Indian Rupee'],
        ['code' => 'IQD', 'name' => 'Iraqi Dinar'],
        ['code' => 'IRR', 'name' => 'Iranian Rial'],
        ['code' => 'ISK', 'name' => 'Icelandic Króna'],
        ['code' => 'JMD', 'name' => 'Jamaican Dollar'],
        ['code' => 'JOD', 'name' => 'Jordanian Dinar'],
        ['code' => 'JPY', 'name' => 'Japanese Yen'],
        ['code' => 'KES', 'name' => 'Kenyan Shilling'],
        ['code' => 'KGS', 'name' => 'Kyrgystani Som'],
        ['code' => 'KHR', 'name' => 'Cambodian Riel'],
        ['code' => 'KMF', 'name' => 'Comorian Franc'],
        ['code' => 'KPW', 'name' => 'North Korean Won'],
        ['code' => 'KRW', 'name' => 'South Korean Won'],
        ['code' => 'KWD', 'name' => 'Kuwaiti Dinar'],
        ['code' => 'KYD', 'name' => 'Cayman Islands Dollar'],
        ['code' => 'KZT', 'name' => 'Kazakhstani Tenge'],
        ['code' => 'LAK', 'name' => 'Laotian Kip'],
        ['code' => 'LBP', 'name' => 'Lebanese Pound'],
        ['code' => 'LKR', 'name' => 'Sri Lankan Rupee'],
        ['code' => 'LRD', 'name' => 'Liberian Dollar'],
        ['code' => 'LSL', 'name' => 'Lesotho Loti'],
        ['code' => 'LYD', 'name' => 'Libyan Dinar'],
        ['code' => 'MAD', 'name' => 'Moroccan Dirham'],
        ['code' => 'MDL', 'name' => 'Moldovan Leu'],
        ['code' => 'MGA', 'name' => 'Malagasy Ariary'],
        ['code' => 'MKD', 'name' => 'Macedonian Denar'],
        ['code' => 'MMK', 'name' => 'Myanma Kyat'],
        ['code' => 'MNT', 'name' => 'Mongolian Tugrik'],
        ['code' => 'MOP', 'name' => 'Macanese Pataca'],
        ['code' => 'MRU', 'name' => 'Mauritanian Ouguiya'],
        ['code' => 'MUR', 'name' => 'Mauritian Rupee'],
        ['code' => 'MVR', 'name' => 'Maldivian Rufiyaa'],
        ['code' => 'MWK', 'name' => 'Malawian Kwacha'],
        ['code' => 'MXN', 'name' => 'Mexican Peso'],
        ['code' => 'MYR', 'name' => 'Malaysian Ringgit'],
        ['code' => 'MZN', 'name' => 'Mozambican Metical'],
        ['code' => 'NAD', 'name' => 'Namibian Dollar'],
        ['code' => 'NGN', 'name' => 'Nigerian Naira'],
        ['code' => 'NIO', 'name' => 'Nicaraguan Córdoba'],
        ['code' => 'NOK', 'name' => 'Norwegian Krone'],
        ['code' => 'NPR', 'name' => 'Nepalese Rupee'],
        ['code' => 'NZD', 'name' => 'New Zealand Dollar'],
        ['code' => 'OMR', 'name' => 'Omani Rial'],
        ['code' => 'PAB', 'name' => 'Panamanian Balboa'],
        ['code' => 'PEN', 'name' => 'Peruvian Nuevo Sol'],
        ['code' => 'PGK', 'name' => 'Papua New Guinean Kina'],
        ['code' => 'PHP', 'name' => 'Philippine Peso'],
        ['code' => 'PKR', 'name' => 'Pakistani Rupee'],
        ['code' => 'PLN', 'name' => 'Polish Zloty'],
        ['code' => 'PYG', 'name' => 'Paraguayan Guarani'],
        ['code' => 'QAR', 'name' => 'Qatari Rial'],
        ['code' => 'RON', 'name' => 'Romanian Leu'],
        ['code' => 'RSD', 'name' => 'Serbian Dinar'],
        ['code' => 'RUB', 'name' => 'Russian Ruble'],
        ['code' => 'RWF', 'name' => 'Rwandan Franc'],
        ['code' => 'SAR', 'name' => 'Saudi Riyal'],
        ['code' => 'SBD', 'name' => 'Solomon Islands Dollar'],
        ['code' => 'SCR', 'name' => 'Seychellois Rupee'],
        ['code' => 'SDG', 'name' => 'Sudanese Pound'],
        ['code' => 'SEK', 'name' => 'Swedish Krona'],
        ['code' => 'SGD', 'name' => 'Singapore Dollar'],
        ['code' => 'SHP', 'name' => 'Saint Helena Pound'],
        ['code' => 'SLL', 'name' => 'Sierra Leonean Leone'],
        ['code' => 'SOS', 'name' => 'Somali Shilling'],
        ['code' => 'SRD', 'name' => 'Surinamese Dollar'],
        ['code' => 'SSP', 'name' => 'South Sudanese Pound'],
        ['code' => 'STN', 'name' => 'São Tomé and Príncipe Dobra'],
        ['code' => 'SYP', 'name' => 'Syrian Pound'],
        ['code' => 'SZL', 'name' => 'Swazi Lilangeni'],
        ['code' => 'THB', 'name' => 'Thai Baht'],
        ['code' => 'TJS', 'name' => 'Tajikistani Somoni'],
        ['code' => 'TMT', 'name' => 'Turkmenistani Manat'],
        ['code' => 'TND', 'name' => 'Tunisian Dinar'],
        ['code' => 'TOP', 'name' => 'Tongan Paʻanga'],
        ['code' => 'TRY', 'name' => 'Turkish Lira'],
        ['code' => 'TTD', 'name' => 'Trinidad and Tobago Dollar'],
        ['code' => 'TWD', 'name' => 'New Taiwan Dollar'],
        ['code' => 'TZS', 'name' => 'Tanzanian Shilling'],
        ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia'],
        ['code' => 'UGX', 'name' => 'Ugandan Shilling'],
        ['code' => 'UYU', 'name' => 'Uruguayan Peso'],
        ['code' => 'UZS', 'name' => 'Uzbekistan Som'],
        ['code' => 'VES', 'name' => 'Venezuelan Bolívar'],
        ['code' => 'VND', 'name' => 'Vietnamese Dong'],
        ['code' => 'VUV', 'name' => 'Vanuatu Vatu'],
        ['code' => 'WST', 'name' => 'Samoan Tala'],
        ['code' => 'XCD', 'name' => 'East Caribbean Dollar'],
        ['code' => 'XOF', 'name' => 'CFA Franc BCEAO'],
        ['code' => 'XPF', 'name' => 'CFP Franc'],
        ['code' => 'YER', 'name' => 'Yemeni Rial'],
        ['code' => 'ZAR', 'name' => 'South African Rand'],
        ['code' => 'ZMW', 'name' => 'Zambian Kwacha'],
        ['code' => 'ZWL', 'name' => 'Zimbabwean Dollar']
    ];

    public function maxImages(): int
    {
        return (int) PlatformSetting::getValue('marketplace_max_images', 10);
    }

    public function minTitleLength(): int
    {
        return (int) PlatformSetting::getValue('marketplace_min_title_length', 6);
    }

    public function defaultCurrency(): string
    {
        return (string) PlatformSetting::getValue('marketplace_default_currency', 'XAF');
    }

    /**
     * Everything publishing checks, keyed by field name. $rename maps a field
     * to another key (the Livewire component's property names).
     *
     * @param  array<string, string>  $rename
     */
    public function rules(?int $categoryId, array $rename = [], string $attrsPrefix = 'attributes'): array
    {
        $rules = [
            'category_id' => 'required|integer|exists:marketplace_categories,id',
            'title' => 'required|string|min:'.$this->minTitleLength().'|max:180',
            // Optional, as on Facebook Marketplace.
            'description' => 'nullable|string|max:5000',
            'price_type' => 'required|in:fixed,negotiable,free,contact',
            'price' => 'nullable|numeric|min:0|max:1000000000|required_if:'.($rename['price_type'] ?? 'price_type').',fixed,negotiable',
            'currency' => 'required|string|size:3',
            'condition' => 'required|in:new,like_new,good,fair,for_parts',
            'quantity' => 'nullable|integer|min:1|max:9999',
            'fulfillment' => 'required|array|min:1',
            'fulfillment.*' => 'in:pickup,local_delivery,diaspora_shippable,digital',
            'country' => 'nullable|string|size:2',
            'region' => 'nullable|string|max:80',
            'city' => 'nullable|string|max:120',
            'neighborhood' => 'nullable|string|max:160',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_label' => 'required|string|max:200',
            'visibility' => 'required|in:public,connections,group',
        ];

        $out = [];
        foreach ($rules as $key => $rule) {
            $out[$rename[$key] ?? $key] = $rule;
        }

        return $out + CategoryAttributeSchema::validationRules(CategoryAttributeSchema::forCategory($categoryId), $attrsPrefix);
    }

    /** A draft for the member: photos need a listing to belong to. */
    public function createDraft(User $user, array $fields = []): MarketplaceListing
    {
        $priceType = $fields['price_type'] ?? 'fixed';

        return MarketplaceListing::create([
            'user_id' => $user->id,
            'category_id' => $fields['category_id'] ?? null,
            'title' => trim((string) ($fields['title'] ?? '')) ?: 'Untitled draft',
            'description' => $fields['description'] ?? null,
            'price_type' => $priceType,
            'price' => $priceType === 'free' ? 0 : ($this->amount($fields['price'] ?? null)),
            'currency' => $fields['currency'] ?? $this->defaultCurrency(),
            'condition' => $fields['condition'] ?? 'good',
            'quantity' => (int) ($fields['quantity'] ?? 1) ?: 1,
            'fulfillment' => $fields['fulfillment'] ?? ['pickup'],
            'country' => $fields['country'] ?? $user->current_country_code ?? 'CM',
            'region' => $fields['region'] ?? $user->current_region,
            'city' => $fields['city'] ?? $user->current_city,
            'neighborhood' => $fields['neighborhood'] ?? null,
            'latitude' => $fields['latitude'] ?? null,
            'longitude' => $fields['longitude'] ?? null,
            'visibility' => $fields['visibility'] ?? 'public',
            'tags' => array_values($fields['tags'] ?? []),
            'status' => ListingStatus::Draft->value,
        ]);
    }

    /** A draft has something worth keeping (the web's autosave threshold). */
    public function worthSaving(array $fields, ?MarketplaceListing $listing): bool
    {
        return mb_strlen(trim((string) ($fields['title'] ?? ''))) >= 3
            || mb_strlen(trim((string) ($fields['description'] ?? ''))) >= 10
            || ($listing && $listing->media()->exists());
    }

    /**
     * Quietly keep what has been typed. Drafts only: a published listing
     * changes when the member saves it, never behind their back.
     */
    public function autosave(MarketplaceListing $listing, array $fields): bool
    {
        if ($listing->status !== ListingStatus::Draft) {
            return false;
        }

        $priceType = $fields['price_type'] ?? $listing->price_type?->value;
        $listing->forceFill(array_filter([
            'category_id' => $fields['category_id'] ?? null,
            'title' => trim((string) ($fields['title'] ?? '')) ?: $listing->title,
            'description' => trim((string) ($fields['description'] ?? '')) ?: $listing->description,
            'price_type' => $priceType,
            'price' => $priceType === 'free' ? 0 : $this->amount($fields['price'] ?? null),
            'currency' => $fields['currency'] ?? null,
            'condition' => $fields['condition'] ?? null,
            'quantity' => $fields['quantity'] ?? null,
            'fulfillment' => $fields['fulfillment'] ?? null,
            'country' => $fields['country'] ?? null,
            'region' => $fields['region'] ?? null,
            'city' => $fields['city'] ?? null,
            'neighborhood' => $fields['neighborhood'] ?? null,
            'latitude' => $fields['latitude'] ?? null,
            'longitude' => $fields['longitude'] ?? null,
            'visibility' => $fields['visibility'] ?? null,
            'tags' => isset($fields['tags']) ? array_values($fields['tags']) : null,
            'attributes' => isset($fields['attributes'])
                ? CategoryAttributeSchema::sanitize((array) $fields['attributes'], $fields['category_id'] ?? $listing->category_id)
                : null,
        ], fn ($v) => $v !== null && $v !== ''))->save();

        return true;
    }

    /**
     * Store uploaded photos (optimised, with a thumbnail) up to the limit.
     * The first photo of a listing is its cover.
     *
     * @param  array<int, UploadedFile>  $files
     * @return int how many were added
     */
    public function addPhotos(MarketplaceListing $listing, array $files): int
    {
        $current = $listing->media()->count();
        $remaining = max(0, $this->maxImages() - $current);
        $added = 0;

        foreach (array_slice($files, 0, $remaining) as $photo) {
            $rawPath = $photo->store('marketplace/'.$listing->id, 'public');
            $opt = MarketplaceImageProcessor::process($rawPath);

            MarketplaceListingMedia::create([
                'listing_id' => $listing->id,
                'type' => 'image',
                'path' => $opt['path'],
                'thumbnail_path' => $opt['thumb'],
                'original_name' => $photo->getClientOriginalName(),
                'size_bytes' => Storage::disk('public')->size($opt['path']),
                'width' => $opt['w'] ?: null,
                'height' => $opt['h'] ?: null,
                'mime_type' => 'image/jpeg',
                'position' => $current + $added,
                'is_cover' => ($current + $added) === 0,
            ]);
            $added++;
        }

        return $added;
    }

    public function removePhoto(MarketplaceListing $listing, int $mediaId): bool
    {
        $media = MarketplaceListingMedia::where('listing_id', $listing->id)->find($mediaId);
        if (! $media) {
            return false;
        }

        Storage::disk('public')->delete($media->path);
        if ($media->thumbnail_path && $media->thumbnail_path !== $media->path) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }
        $media->delete();

        // Close the gap and keep "first is the cover" true.
        $this->reorder($listing, $listing->media()->orderBy('position')->orderBy('id')->pluck('id')->all());

        return true;
    }

    /**
     * Make a photo the cover by moving it to the front. (The web only moved
     * the star, so the cover and the first photo could disagree, and a later
     * drag silently took the star back.)
     */
    public function makeCover(MarketplaceListing $listing, int $mediaId): bool
    {
        $ids = $listing->media()->orderBy('position')->orderBy('id')->pluck('id')->all();
        if (! in_array($mediaId, $ids, true)) {
            return false;
        }

        $this->reorder($listing, array_merge([$mediaId], array_values(array_diff($ids, [$mediaId]))));

        return true;
    }

    /**
     * A new photo order; the first is the cover. Ids that are not this
     * listing's are ignored, and photos left out keep their place after the
     * ones given.
     *
     * @param  array<int|string>  $ids
     */
    public function reorder(MarketplaceListing $listing, array $ids): void
    {
        $owned = $listing->media()->orderBy('position')->orderBy('id')->pluck('id')->all();
        $ordered = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => in_array($id, $owned, true))));
        if ($ordered === [] && $owned === []) {
            return;
        }
        $ordered = array_merge($ordered, array_values(array_diff($owned, $ordered)));

        foreach ($ordered as $pos => $id) {
            MarketplaceListingMedia::where('id', $id)->where('listing_id', $listing->id)
                ->update(['position' => $pos, 'is_cover' => $pos === 0]);
        }
    }

    /** @return array<int, array{id:int, url:string, thumb_url:string, is_cover:bool}> */
    public function mediaItems(MarketplaceListing $listing): array
    {
        return $listing->media()->orderBy('position')->orderBy('id')->get()
            ->map(fn (MarketplaceListingMedia $m) => [
                'id' => $m->id,
                'url' => $m->url(),
                'thumb_url' => $m->thumbnailUrl(),
                'is_cover' => (bool) $m->is_cover,
            ])->all();
    }

    /**
     * Publish a draft, or save an edit. $data is validated against rules().
     *
     * A draft goes live (or to review when auto-approve is off) with a fresh
     * expiry. An edit keeps the listing where it was: the web re-published
     * every edit, which brought a sold or paused listing back on sale and
     * reset its date (a free bump).
     */
    public function publish(MarketplaceListing $listing, array $data): ListingStatus
    {
        $isDraft = $listing->status === ListingStatus::Draft;
        $priceType = $data['price_type'];

        $listing->fill([
            'category_id' => $data['category_id'],
            'title' => trim($data['title']),
            'description' => trim((string) ($data['description'] ?? '')),
            'price_type' => $priceType,
            'price' => $priceType === 'free' ? 0 : $this->amount($data['price'] ?? null),
            'currency' => $data['currency'],
            'condition' => $data['condition'],
            'quantity' => (int) ($data['quantity'] ?? 1) ?: 1,
            'fulfillment' => array_values($data['fulfillment']),
            'country' => $data['country'] ?? $listing->country,
            'region' => $data['region'] ?? '',
            'city' => $data['city'] ?? '',
            'neighborhood' => $data['neighborhood'] ?? '',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'visibility' => $data['visibility'],
            'tags' => array_values($data['tags'] ?? []),
            'attributes' => CategoryAttributeSchema::sanitize((array) ($data['attributes'] ?? []), $data['category_id']),
        ]);

        if ($isDraft) {
            $autoApprove = filter_var(PlatformSetting::getValue('marketplace_auto_approve', 'true'), FILTER_VALIDATE_BOOL);
            $status = $autoApprove ? ListingStatus::Active : ListingStatus::PendingReview;
            $expiresDays = (int) PlatformSetting::getValue('marketplace_listing_expiry_days', 30);
            $listing->status = $status->value;
            $listing->published_at = $status === ListingStatus::Active ? now() : null;
            $listing->expires_at = $status === ListingStatus::Active ? now()->addDays($expiresDays) : null;

            // A draft started from a photo was named "Untitled draft", and
            // the slug is made once: give the live listing a real address.
            if (Str::startsWith((string) $listing->slug, 'untitled-draft-')) {
                $listing->slug = (Str::slug($listing->title) ?: 'listing').'-'.Str::lower(Str::random(6));
            }
        }

        $listing->save();
        $listing->recomputeRankScore();

        return $listing->status;
    }

    /** The member's words for the outcome of publish(). */
    public function publishedMessage(ListingStatus $status, bool $wasEdit, ?string $lang = null): string
    {
        $fr = ($lang ?? app()->getLocale()) === 'fr';
        if ($wasEdit) {
            return $fr ? 'Modifications enregistrées ✓' : 'Changes saved ✓';
        }

        return $status === ListingStatus::Active
            ? ($fr ? 'Votre annonce est en ligne ✓' : 'Your listing is live ✓')
            : ($fr ? 'Envoyée pour vérification, vous serez notifié sous peu.' : 'Submitted for review, you’ll be notified shortly.');
    }

    private function amount(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float) $v;
    }
}
