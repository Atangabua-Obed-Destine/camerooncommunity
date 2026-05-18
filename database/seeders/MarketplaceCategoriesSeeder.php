<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MarketplaceCategoriesSeeder extends Seeder
{
    /**
     * Seed FB-Marketplace-style categories tuned for Cameroon Network:
     * combines familiar global categories (Electronics, Vehicles…) with
     * Cameroon-specific verticals (Diaspora Shopping, Solidarity, Land/Plots,
     * Foodstuff, Tontine/Njangi services).
     */
    public function run(): void
    {
        $tenant = Tenant::query()->where('domain', 'camerooncommunity.net')->first()
            ?? Tenant::query()->first();

        if (! $tenant) {
            $this->command?->warn('No tenant found — skipping marketplace categories seed.');
            return;
        }

        app()->instance('currentTenant', $tenant);

        $tree = [
            ['vehicles', '🚗', 'Vehicles', 'Véhicules', [
                ['cars',          '🚙', 'Cars',                 'Voitures'],
                ['motorcycles',   '🏍️', 'Motorcycles',          'Motos'],
                ['trucks',        '🚚', 'Trucks & Vans',        'Camions & Utilitaires'],
                ['auto-parts',    '🔧', 'Auto Parts',           'Pièces auto'],
                ['boats',         '⛵', 'Boats',                 'Bateaux'],
            ]],
            ['real-estate', '🏠', 'Property & Land', 'Immobilier & Terrains', [
                ['apartments-rent', '🛏️', 'Apartments for Rent', 'Appartements à louer'],
                ['houses-sale',     '🏡', 'Houses for Sale',     'Maisons à vendre'],
                ['land-plots',      '🌱', 'Land & Plots',        'Terrains à vendre'],
                ['short-stay',      '🛎️', 'Short Stay / Airbnb', 'Location courte durée'],
                ['commercial',      '🏢', 'Commercial Space',    'Espace commercial'],
            ]],
            ['electronics', '💻', 'Electronics', 'Électronique', [
                ['laptops',     '💻', 'Laptops & Computers', 'Ordinateurs'],
                ['tvs',         '📺', 'TVs & Audio',         'TV & Audio'],
                ['gaming',      '🎮', 'Gaming',              'Jeux vidéo'],
                ['accessories', '🎧', 'Accessories',         'Accessoires'],
            ]],
            ['phones-tablets', '📱', 'Phones & Tablets', 'Téléphones & Tablettes', [
                ['smartphones',     '📱', 'Smartphones',          'Smartphones'],
                ['tablets',         '📲', 'Tablets',              'Tablettes'],
                ['phone-accessories','🔌','Accessories & Cases', 'Accessoires & Étuis'],
            ]],
            ['home-garden', '🪴', 'Home & Garden', 'Maison & Jardin', [
                ['furniture',  '🛋️', 'Furniture',     'Meubles'],
                ['appliances', '🧊', 'Appliances',    'Électroménager'],
                ['decor',      '🖼️', 'Home Decor',    'Décoration'],
                ['garden',     '🌿', 'Garden & Tools','Jardin & Outils'],
            ]],
            ['fashion', '👗', 'Fashion & Beauty', 'Mode & Beauté', [
                ['womens',       '👗', "Women's Fashion", 'Mode femme'],
                ['mens',         '👔', "Men's Fashion",   'Mode homme'],
                ['kids-fashion', '🧒', 'Kids Fashion',     'Mode enfants'],
                ['shoes',        '👟', 'Shoes',            'Chaussures'],
                ['bags',         '👜', 'Bags & Accessories','Sacs & Accessoires'],
                ['beauty',       '💄', 'Beauty & Cosmetics','Beauté & Cosmétiques'],
                ['traditional',  '🪡', 'Traditional Wear (Toghu, Kaba)', 'Habits traditionnels'],
            ]],
            ['foodstuff', '🍌', 'Foodstuff & Drinks', 'Alimentation', [
                ['fresh-produce','🥬', 'Fresh Produce',         'Produits frais'],
                ['groceries',    '🛒', 'Groceries',             'Épicerie'],
                ['drinks',       '🥤', 'Drinks',                'Boissons'],
                ['cm-specialty', '🌶️', 'CM Specialty (Eru, Egusi, Achu)', 'Spécialités camerounaises'],
            ]],
            ['baby-kids', '🍼', 'Baby & Kids', 'Bébés & Enfants', [
                ['baby-gear', '👶', 'Baby Gear',  'Équipement bébé'],
                ['toys',      '🧸', 'Toys',       'Jouets'],
                ['strollers', '🛒', 'Strollers',  'Poussettes'],
            ]],
            ['health-wellness', '💊', 'Health & Wellness', 'Santé & Bien-être', [
                ['fitness',    '🏋️', 'Fitness Equipment',  'Équipement fitness'],
                ['supplements','💊', 'Supplements',         'Compléments'],
                ['medical',    '🩺', 'Medical Equipment',   'Équipement médical'],
            ]],
            ['sports-leisure', '⚽', 'Sports & Leisure', 'Sport & Loisirs', [
                ['sportswear', '🥋', 'Sportswear',          'Tenues sport'],
                ['outdoors',   '🏕️', 'Outdoors & Camping',  'Camping & Plein air'],
                ['bicycles',   '🚲', 'Bicycles',            'Vélos'],
            ]],
            ['books-music', '📚', 'Books, Music & Hobbies', 'Livres, Musique & Hobbies', [
                ['books',         '📖', 'Books',                  'Livres'],
                ['music-vinyl',   '🎵', 'Music & Vinyl',          'Musique & Vinyles'],
                ['art-crafts',    '🎨', 'Art & Crafts',           'Art & Artisanat'],
                ['musical-instr', '🎸', 'Musical Instruments',    'Instruments de musique'],
            ]],
            ['business-industrial', '🏭', 'Business & Industrial', 'Entreprise & Industrie', [
                ['office',     '🖨️', 'Office Equipment',      'Équipement bureau'],
                ['restaurant', '🍽️', 'Restaurant & Catering', 'Restauration'],
                ['agriculture','🌾', 'Agriculture',           'Agriculture'],
            ]],
            ['services', '🛠️', 'Services', 'Services', [
                ['home-services',  '🔨', 'Home Services',          'Services à domicile'],
                ['repair',         '🔧', 'Repairs & Maintenance',  'Réparations'],
                ['tutoring',       '📚', 'Tutoring & Lessons',     'Cours particuliers'],
                ['events',         '🎉', 'Event Planning',         'Événementiel'],
                ['transport',      '🚐', 'Transport & Moving',     'Transport & Déménagement'],
                ['photography',    '📸', 'Photography & Video',    'Photo & Vidéo'],
                ['tailor',         '🪡', 'Tailor & Couture',       'Couture sur mesure'],
            ]],
            ['jobs', '💼', 'Jobs', 'Emplois', [
                ['full-time', '📝', 'Full-time',         'Temps plein'],
                ['part-time', '⏰', 'Part-time',         'Temps partiel'],
                ['freelance', '💻', 'Freelance / Gigs',  'Freelance'],
                ['domestic',  '🏠', 'Domestic Help',     'Aide ménagère'],
            ]],
            ['diaspora-shopping', '✈️', 'Diaspora Shopping', 'Achats Diaspora', [
                ['from-diaspora', '🌍', 'From Diaspora to CM', 'De la diaspora vers le Cameroun'],
                ['to-diaspora',   '📦', 'From CM to Diaspora', 'Du Cameroun vers la diaspora'],
            ]],
            ['solidarity-free', '🤝', 'Free & Solidarity', 'Gratuit & Solidarité', [
                ['free-stuff', '🎁', 'Free Stuff', 'Gratuit'],
                ['donations',  '❤️', 'Donations',  'Dons'],
            ]],
            ['pets', '🐾', 'Pets & Animals', 'Animaux', [
                ['pet-supplies', '🦴', 'Pet Supplies', 'Accessoires pour animaux'],
                ['livestock',    '🐓', 'Livestock',    'Bétail'],
            ]],
            ['miscellaneous', '📦', 'Miscellaneous', 'Divers', []],
        ];

        $position = 0;
        foreach ($tree as $root) {
            [$slug, $icon, $en, $fr, $kids] = [
                $root[0], $root[1], $root[2], $root[3], $root[4] ?? [],
            ];
            $parent = MarketplaceCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                [
                    'name_en' => $en,
                    'name_fr' => $fr,
                    'icon' => $icon,
                    'position' => $position++,
                    'is_active' => true,
                ],
            );

            $childPos = 0;
            foreach ($kids as $kid) {
                MarketplaceCategory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $kid[0]],
                    [
                        'parent_id' => $parent->id,
                        'name_en' => $kid[2],
                        'name_fr' => $kid[3],
                        'icon' => $kid[1],
                        'position' => $childPos++,
                        'is_active' => true,
                    ],
                );
            }
        }

        $this->command?->info('Marketplace categories seeded.');
    }
}
