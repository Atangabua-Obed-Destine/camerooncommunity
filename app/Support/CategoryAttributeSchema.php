<?php

namespace App\Support;

use App\Models\MarketplaceCategory;

/**
 * Per-category attribute schemas (Phase 4 — category-specific fields).
 *
 * Listings store the answers in `marketplace_listings.attributes` (JSON).
 * The schema below describes which fields to render in the composer and
 * how to label / display them in detail pages and filters.
 *
 * Field shape:
 *   key       string  Storage key inside the JSON column.
 *   label     string  English label.
 *   labelFr   string  French label.
 *   type      string  text | number | select | toggle
 *   options?  array   For `select`: list of ['value','label','labelFr','icon?'].
 *   required? bool    Enforced at publish time.
 *   suffix?   string  Visual unit suffix (e.g. "km", "m²", "kWh").
 *   help?     string  English placeholder/help.
 *   helpFr?   string  French placeholder/help.
 *   filter?   bool    Surfaces as a sidebar quick-filter when category is active.
 *   icon?     string  Emoji shown next to the value pill on detail pages.
 *
 * Category matching is done by slug; we walk up the parent chain so a leaf
 * "Cars" under root "Vehicles" inherits the vehicles schema unless overridden.
 */
class CategoryAttributeSchema
{
    /** root slug => field[] */
    public static function map(): array
    {
        return [
            'vehicles' => [
                ['key'=>'make',         'label'=>'Make / Brand',     'labelFr'=>'Marque',           'type'=>'text',   'required'=>true,  'help'=>'Toyota, Honda, …',     'icon'=>'🏷️', 'filter'=>true],
                ['key'=>'model',        'label'=>'Model',            'labelFr'=>'Modèle',           'type'=>'text',   'required'=>true,  'help'=>'Corolla, Civic, …',    'icon'=>'🚗'],
                ['key'=>'year',         'label'=>'Year',             'labelFr'=>'Année',            'type'=>'number', 'required'=>true,  'help'=>'2018',                 'icon'=>'📅', 'filter'=>true],
                ['key'=>'mileage_km',   'label'=>'Mileage',          'labelFr'=>'Kilométrage',      'type'=>'number', 'suffix'=>'km',    'help'=>'85000',                'icon'=>'🛣️'],
                ['key'=>'transmission', 'label'=>'Transmission',     'labelFr'=>'Transmission',     'type'=>'select', 'icon'=>'⚙️',     'filter'=>true,
                    'options'=>[
                        ['value'=>'manual',    'label'=>'Manual',    'labelFr'=>'Manuelle'],
                        ['value'=>'automatic', 'label'=>'Automatic', 'labelFr'=>'Automatique'],
                        ['value'=>'cvt',       'label'=>'CVT',       'labelFr'=>'CVT'],
                    ]],
                ['key'=>'fuel',         'label'=>'Fuel',             'labelFr'=>'Carburant',        'type'=>'select', 'icon'=>'⛽', 'filter'=>true,
                    'options'=>[
                        ['value'=>'petrol', 'label'=>'Petrol', 'labelFr'=>'Essence'],
                        ['value'=>'diesel', 'label'=>'Diesel', 'labelFr'=>'Diesel'],
                        ['value'=>'hybrid', 'label'=>'Hybrid', 'labelFr'=>'Hybride'],
                        ['value'=>'electric','label'=>'Electric','labelFr'=>'Électrique'],
                        ['value'=>'lpg',    'label'=>'LPG',    'labelFr'=>'GPL'],
                    ]],
                ['key'=>'body_type',    'label'=>'Body type',        'labelFr'=>'Carrosserie',      'type'=>'select', 'icon'=>'🚙',
                    'options'=>[
                        ['value'=>'sedan',   'label'=>'Sedan',    'labelFr'=>'Berline'],
                        ['value'=>'suv',     'label'=>'SUV',      'labelFr'=>'SUV'],
                        ['value'=>'hatch',   'label'=>'Hatchback','labelFr'=>'Compacte'],
                        ['value'=>'pickup',  'label'=>'Pickup',   'labelFr'=>'Pick-up'],
                        ['value'=>'van',     'label'=>'Van',      'labelFr'=>'Van / Utilitaire'],
                        ['value'=>'coupe',   'label'=>'Coupe',    'labelFr'=>'Coupé'],
                        ['value'=>'wagon',   'label'=>'Wagon',    'labelFr'=>'Break'],
                        ['value'=>'moto',    'label'=>'Motorbike','labelFr'=>'Moto'],
                    ]],
                ['key'=>'color',        'label'=>'Color',            'labelFr'=>'Couleur',          'type'=>'text',  'help'=>'Black, Silver, …', 'icon'=>'🎨'],
                ['key'=>'doc_status',   'label'=>'Documents',        'labelFr'=>'Documents',        'type'=>'select', 'icon'=>'📄',
                    'options'=>[
                        ['value'=>'clean',    'label'=>'Clean title',         'labelFr'=>'Carte grise OK'],
                        ['value'=>'pending',  'label'=>'Paperwork pending',   'labelFr'=>'Papiers en cours'],
                        ['value'=>'damaged',  'label'=>'Salvage / damaged',   'labelFr'=>'Endommagé'],
                    ]],
            ],

            'real-estate' => [
                ['key'=>'transaction', 'label'=>'Listing type', 'labelFr'=>'Type d\'annonce', 'type'=>'select', 'required'=>true, 'icon'=>'🏷️','filter'=>true,
                    'options'=>[
                        ['value'=>'rent', 'label'=>'For rent', 'labelFr'=>'À louer'],
                        ['value'=>'sale', 'label'=>'For sale', 'labelFr'=>'À vendre'],
                        ['value'=>'short_stay', 'label'=>'Short stay', 'labelFr'=>'Courte durée'],
                    ]],
                ['key'=>'property_type', 'label'=>'Property type', 'labelFr'=>'Type de bien', 'type'=>'select', 'required'=>true, 'icon'=>'🏠', 'filter'=>true,
                    'options'=>[
                        ['value'=>'apartment', 'label'=>'Apartment', 'labelFr'=>'Appartement'],
                        ['value'=>'house',     'label'=>'House',     'labelFr'=>'Maison'],
                        ['value'=>'villa',     'label'=>'Villa',     'labelFr'=>'Villa'],
                        ['value'=>'studio',    'label'=>'Studio',    'labelFr'=>'Studio'],
                        ['value'=>'room',      'label'=>'Room',      'labelFr'=>'Chambre'],
                        ['value'=>'land',      'label'=>'Land',      'labelFr'=>'Terrain'],
                        ['value'=>'commercial','label'=>'Commercial','labelFr'=>'Commercial'],
                    ]],
                ['key'=>'bedrooms',  'label'=>'Bedrooms',  'labelFr'=>'Chambres',     'type'=>'number', 'icon'=>'🛏️', 'filter'=>true, 'help'=>'2'],
                ['key'=>'bathrooms', 'label'=>'Bathrooms', 'labelFr'=>'Salles de bain','type'=>'number', 'icon'=>'🛁', 'help'=>'1'],
                ['key'=>'area_m2',   'label'=>'Surface',   'labelFr'=>'Superficie',    'type'=>'number', 'suffix'=>'m²', 'icon'=>'📐', 'help'=>'85'],
                ['key'=>'furnished', 'label'=>'Furnished', 'labelFr'=>'Meublé',        'type'=>'toggle', 'icon'=>'🛋️'],
                ['key'=>'parking',   'label'=>'Parking',   'labelFr'=>'Parking',       'type'=>'toggle', 'icon'=>'🅿️'],
                ['key'=>'water',     'label'=>'Running water','labelFr'=>'Eau courante','type'=>'toggle', 'icon'=>'🚿'],
                ['key'=>'electricity','label'=>'Electricity', 'labelFr'=>'Électricité','type'=>'toggle', 'icon'=>'💡'],
            ],

            'jobs' => [
                ['key'=>'employment',  'label'=>'Employment',    'labelFr'=>'Contrat',     'type'=>'select', 'required'=>true, 'icon'=>'📝', 'filter'=>true,
                    'options'=>[
                        ['value'=>'full_time', 'label'=>'Full-time', 'labelFr'=>'Temps plein'],
                        ['value'=>'part_time', 'label'=>'Part-time', 'labelFr'=>'Temps partiel'],
                        ['value'=>'contract',  'label'=>'Contract',  'labelFr'=>'Contrat'],
                        ['value'=>'internship','label'=>'Internship','labelFr'=>'Stage'],
                        ['value'=>'gig',       'label'=>'Gig',       'labelFr'=>'Mission'],
                    ]],
                ['key'=>'remote',    'label'=>'Remote OK',      'labelFr'=>'Télétravail OK','type'=>'toggle', 'icon'=>'🏡', 'filter'=>true],
                ['key'=>'experience','label'=>'Experience',     'labelFr'=>'Expérience',    'type'=>'select', 'icon'=>'⭐',
                    'options'=>[
                        ['value'=>'entry',  'label'=>'Entry-level','labelFr'=>'Débutant'],
                        ['value'=>'mid',    'label'=>'Mid-level',  'labelFr'=>'Intermédiaire'],
                        ['value'=>'senior', 'label'=>'Senior',     'labelFr'=>'Sénior'],
                    ]],
                ['key'=>'salary_range','label'=>'Salary range', 'labelFr'=>'Salaire',       'type'=>'text', 'icon'=>'💰', 'help'=>'200,000 – 350,000 XAF / month'],
            ],

            'electronics' => [
                ['key'=>'brand',   'label'=>'Brand',  'labelFr'=>'Marque',  'type'=>'text',  'icon'=>'🏷️', 'filter'=>true, 'help'=>'Samsung, Apple, …'],
                ['key'=>'model',   'label'=>'Model',  'labelFr'=>'Modèle',  'type'=>'text',  'icon'=>'📱', 'help'=>'Galaxy S22, iPhone 14, …'],
                ['key'=>'storage', 'label'=>'Storage','labelFr'=>'Stockage','type'=>'text',  'icon'=>'💾', 'help'=>'128 GB'],
                ['key'=>'ram',     'label'=>'RAM',    'labelFr'=>'RAM',     'type'=>'text',  'icon'=>'🧠', 'help'=>'8 GB'],
                ['key'=>'warranty_months','label'=>'Warranty', 'labelFr'=>'Garantie', 'type'=>'number','suffix'=>'months','icon'=>'🛡️','help'=>'6'],
            ],
        ];
    }

    /**
     * Resolve the schema for a category id (or slug), walking up the parent
     * chain until we hit a root we have rules for. Returns [] if none.
     */
    public static function forCategory(int|string|null $categoryIdOrSlug): array
    {
        if (! $categoryIdOrSlug) { return []; }
        $map = self::map();

        $cat = is_int($categoryIdOrSlug) || ctype_digit((string) $categoryIdOrSlug)
            ? MarketplaceCategory::find((int) $categoryIdOrSlug)
            : MarketplaceCategory::where('slug', (string) $categoryIdOrSlug)->first();

        if (! $cat) { return []; }

        $seen = [];
        while ($cat && ! isset($seen[$cat->id])) {
            $seen[$cat->id] = true;
            if (isset($map[$cat->slug])) {
                return $map[$cat->slug];
            }
            $cat = $cat->parent_id ? MarketplaceCategory::find($cat->parent_id) : null;
        }
        return [];
    }

    /** Limit a raw attributes array to keys that exist in the category schema. */
    public static function sanitize(array $raw, int|string|null $categoryIdOrSlug): array
    {
        $schema = self::forCategory($categoryIdOrSlug);
        if ($schema === []) { return []; }
        $allowed = array_column($schema, 'key');
        $out = [];
        foreach ($allowed as $k) {
            if (! array_key_exists($k, $raw)) { continue; }
            $v = $raw[$k];
            if ($v === '' || $v === null) { continue; }
            $out[$k] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }

    /** Validation rules for a schema, keyed under attrs.* for Livewire. */
    public static function validationRules(array $schema, string $prefix = 'attrs'): array
    {
        $rules = [];
        foreach ($schema as $f) {
            $rule = [];
            $rule[] = 'nullable';
            $rule[] = match ($f['type']) {
                'number' => 'numeric',
                'toggle' => 'boolean',
                'select' => 'string|in:' . implode(',', array_column($f['options'] ?? [], 'value')),
                default  => 'string|max:160',
            };
            $rules["$prefix.$f[key]"] = implode('|', $rule);
        }
        return $rules;
    }

    /** Human-readable resolution of a stored value (for pills). */
    public static function displayValue(array $field, mixed $value, string $locale = 'en'): string
    {
        if ($value === null || $value === '') { return ''; }
        if ($field['type'] === 'toggle') {
            return $value ? ($locale === 'fr' ? 'Oui' : 'Yes') : ($locale === 'fr' ? 'Non' : 'No');
        }
        if ($field['type'] === 'select') {
            foreach (($field['options'] ?? []) as $opt) {
                if ((string) $opt['value'] === (string) $value) {
                    return $locale === 'fr' ? ($opt['labelFr'] ?? $opt['label']) : $opt['label'];
                }
            }
            return (string) $value;
        }
        $suffix = isset($field['suffix']) ? ' ' . $field['suffix'] : '';
        if ($field['type'] === 'number') {
            return number_format((float) $value) . $suffix;
        }
        return (string) $value . $suffix;
    }
}
